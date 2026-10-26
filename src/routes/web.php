<?php

use App\Http\Controllers\Account\AccountController;
use App\Http\Controllers\Account\AddressController;
use App\Http\Controllers\Account\OrderController;
use App\Http\Controllers\Account\PasswordController;
use App\Http\Controllers\Account\ProfileController;
use App\Http\Controllers\Shop\CartController;
use App\Http\Controllers\Shop\CatalogController;
use App\Http\Controllers\Shop\CdekWidgetController;
use App\Http\Controllers\Shop\CheckoutController;
use App\Http\Controllers\Shop\ContactController;
use App\Http\Controllers\Shop\CookieConsentController;
use App\Http\Controllers\Shop\HelpController;
use App\Http\Controllers\Shop\HomeController;
use App\Http\Controllers\Shop\PaymentController;
use App\Http\Controllers\Shop\QuizController;
use App\Http\Controllers\Shop\ReviewController;
use App\Http\Controllers\Shop\SitemapController;
use App\Http\Controllers\Shop\WholesaleController;
use App\Http\Controllers\Shop\YandexFeedController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

/*
| Storefront and customer account pages (Blade). Login, registration and
| password reset routes are registered by Fortify and shared with admins.
| The admin area lives in routes/admin.php.
*/

Route::get('/', HomeController::class)->name('home');

Route::get('sitemap.xml', SitemapController::class)->name('sitemap');
Route::get('feeds/yandex.xml', YandexFeedController::class)->name('feeds.yandex');

// /privacy-policy is the old site's address.
Route::view('privacy-policy', 'shop.legal.privacy')->name('legal.privacy');
Route::view('terms', 'shop.legal.terms')->name('legal.terms');
Route::view('about', 'shop.about')->name('about');
Route::get('contacts', [ContactController::class, 'show'])->name('contacts');
Route::post('contacts', [ContactController::class, 'store'])->middleware('throttle:5,1')->name('contacts.store');
Route::prefix('help')->name('help')->controller(HelpController::class)->group(function () {
    Route::get('/', 'index');
    Route::get('delivery', 'delivery')->name('.delivery');
    Route::get('delivery/{city}', 'city')->where('city', '[a-z-]+')->name('.delivery.city');
    Route::get('returns', 'returns')->name('.returns');
    Route::get('faq', 'faq')->name('.faq');
});

Route::get('quiz', QuizController::class)->name('quiz');
Route::get('wholesale', [WholesaleController::class, 'show'])->name('wholesale');
Route::post('wholesale', [WholesaleController::class, 'store'])->middleware('throttle:5,1')->name('wholesale.store');
Route::post('reviews/{product}', [ReviewController::class, 'store'])->whereNumber('product')->middleware('throttle:5,1')->name('reviews.store');

// The old site's contact and press pages.
Route::permanentRedirect('more/contact', '/contacts');
Route::permanentRedirect('press', '/about');

Route::post('cookie-consent', CookieConsentController::class)->middleware('throttle:30,1')->name('cookie-consent');

Route::prefix('cart')->name('cart.')->group(function () {
    Route::get('/', [CartController::class, 'show'])->name('show');
    Route::post('items', [CartController::class, 'store'])->middleware('throttle:60,1')->name('store');
    Route::patch('items/{key}', [CartController::class, 'update'])->where('key', '\d+-[a-z]+')->name('update');
    Route::delete('items/{key}', [CartController::class, 'destroy'])->where('key', '\d+-[a-z]+')->name('destroy');
});

Route::prefix('checkout')->name('checkout.')->controller(CheckoutController::class)->group(function () {
    Route::get('/', 'show')->name('show');
    Route::post('/', 'store')->middleware('throttle:10,1')->name('store');
    Route::get('summary', 'summary')->name('summary');
    // The CDEK pickup point map asks for points and prices here.
    Route::match(['get', 'post'], 'cdek', CdekWidgetController::class)->middleware('throttle:120,1')->name('cdek');
    Route::get('geocode', [CdekWidgetController::class, 'geocode'])->middleware('throttle:60,1')->name('geocode');
    Route::post('promo', 'applyPromo')->middleware('throttle:20,1')->name('promo.apply');
    Route::delete('promo', 'removePromo')->name('promo.remove');
    Route::get('login', 'login')->name('login');
    Route::get('complete/{order:token}', 'complete')->name('complete');
});

// T-Bank online payment. These three addresses are also set in the terminal settings.
Route::post('api/tinkoff/webhook', [PaymentController::class, 'webhook'])->name('payment.webhook');
Route::get('payment/success', [PaymentController::class, 'success'])->name('payment.success');
Route::get('payment/failed', [PaymentController::class, 'failed'])->name('payment.failed');
Route::post('payment/{order:token}/retry', [PaymentController::class, 'retry'])->middleware('throttle:10,1')->name('payment.retry');
Route::post('payment/{order:token}/cash', [PaymentController::class, 'payOnDelivery'])->middleware('throttle:10,1')->name('payment.cash');

Route::middleware('auth')->prefix('account')->name('account.')->group(function () {
    Route::get('/', [AccountController::class, 'index'])->name('index');
    Route::get('settings', [AccountController::class, 'edit'])->name('settings');
    Route::delete('/', [AccountController::class, 'destroy'])->name('destroy');

    Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');

    Route::patch('addresses/{address}/default', [AddressController::class, 'makeDefault'])->name('addresses.default');
    Route::resource('addresses', AddressController::class)->except('show');

    Route::put('password', [PasswordController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('password.update');
});

/* @chisel-passkeys */
if (Features::canManagePasskeys()) {
    Route::get('.well-known/passkey-endpoints', function () {
        return response()->json([
            'enroll' => route('admin.security.edit'),
            'manage' => route('admin.security.edit'),
        ]);
    })->name('well-known.passkeys');
}
/* @end-chisel-passkeys */

/*
| Catalog: /coffee, /coffee/{category|filter|collection|product}. Kept last
| because the second segment matches anything under a root category.
*/

Route::get('{root}', [CatalogController::class, 'root'])
    ->whereIn('root', config('catalog.roots'))
    ->name('catalog.root');

Route::get('{root}/{slug}', [CatalogController::class, 'show'])
    ->whereIn('root', config('catalog.roots'))
    ->where('slug', '[A-Za-z0-9_-]+')
    ->name('catalog.show');
