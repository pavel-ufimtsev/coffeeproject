<?php

namespace App\Http\Controllers\Shop;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Checkout\ConfirmOrder;
use App\Services\TBank\CardPayment;
use App\Services\TBank\TBank;
use App\Services\TBank\TBankException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * T-Bank online payment: the bank's notifications, the pages it sends the
 * customer back to, and trying again (or paying on delivery) after a failure.
 * The addresses match what is set in the terminal settings.
 */
class PaymentController extends Controller
{
    /**
     * Status notifications from T-Bank. Answer "OK", or the bank keeps resending.
     */
    public function webhook(Request $request, TBank $bank, CardPayment $payment): Response
    {
        $notification = $request->json()->all();

        if (! $bank->verify($notification)) {
            Log::warning('T-Bank notification with a bad token', ['order' => $notification['OrderId'] ?? null]);

            return response('Bad token', 403);
        }

        $paymentId = (string) ($notification['PaymentId'] ?? '');
        $order = Order::where('payment_id', $paymentId)->first()
            // OrderId is the order number, with "-2", "-3"… on retries: CP-10001-2.
            ?? Order::where('number', preg_replace('/^(CP-\d+)-\d+$/', '$1', (string) ($notification['OrderId'] ?? '')))->first();

        if ($order === null) {
            Log::warning('T-Bank notification for an unknown order', ['order' => $notification['OrderId'] ?? null, 'payment' => $paymentId]);

            return response('OK');
        }

        $payment->apply($order, (string) ($notification['Status'] ?? ''), isset($notification['Amount']) ? (int) $notification['Amount'] : null, $paymentId);

        return response('OK');
    }

    /**
     * The bank sends the customer here after paying. The notification may not
     * have arrived yet, so ask the bank directly.
     */
    public function success(Request $request, CardPayment $payment): RedirectResponse
    {
        $order = $this->order($request);
        $this->refresh($payment, $order);

        return to_route('checkout.complete', $order->token);
    }

    /**
     * Payment declined or abandoned: offer another try, or paying on delivery.
     */
    public function failed(Request $request, CardPayment $payment): View|RedirectResponse
    {
        $order = $this->order($request);
        $this->refresh($payment, $order);

        if (! $order->awaitsPayment()) {
            return to_route('checkout.complete', $order->token);
        }

        return view('shop.payment.failed', [
            'order' => $order->load('items.product.mainImage'),
            'canPayOnDelivery' => PaymentMethod::Cash->isEnabled(),
        ]);
    }

    /**
     * Start a new attempt and go to the bank's page.
     */
    public function retry(Order $order, CardPayment $payment): SymfonyResponse
    {
        if (! $order->awaitsPayment()) {
            return to_route('checkout.complete', $order->token);
        }

        return $this->pay($order, $payment);
    }

    /**
     * Give up on paying online and pay the courier instead.
     */
    public function payOnDelivery(Order $order, ConfirmOrder $confirm): RedirectResponse
    {
        if ($order->awaitsPayment() && PaymentMethod::Cash->isEnabled()) {
            $order->forceFill(['payment_method' => PaymentMethod::Cash])->save();
            $confirm->handle($order);
        }

        return to_route('checkout.complete', $order->token);
    }

    /**
     * Send the customer to the bank's payment page, or back with the reason it failed.
     */
    public static function pay(Order $order, CardPayment $payment): SymfonyResponse
    {
        try {
            $url = $payment->start($order);
        } catch (TBankException $e) {
            report($e);

            return to_route('payment.failed', ['order' => $order->token])
                ->with('payment_error', __('The bank could not start the payment. Please try again in a minute or choose another way to pay.'));
        }

        // A full-page redirect even from fetch(), since the bank's page is another site.
        return redirect()->away($url);
    }

    private function order(Request $request): Order
    {
        return Order::where('token', (string) $request->query('order'))->firstOrFail();
    }

    private function refresh(CardPayment $payment, Order $order): void
    {
        try {
            $payment->refresh($order);
        } catch (TBankException $e) {
            report($e);
        }
    }
}
