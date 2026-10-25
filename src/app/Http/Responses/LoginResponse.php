<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;
use Laravel\Passkeys\Contracts\PasskeyLoginResponse as PasskeyLoginResponseContract;

/**
 * Sends admins to the admin area and everyone else to their account,
 * unless they were on their way to another page before logging in.
 *
 * Used for password, two-factor and passkey logins alike.
 */
class LoginResponse implements LoginResponseContract, PasskeyLoginResponseContract, TwoFactorLoginResponseContract
{
    /**
     * Create an HTTP response that represents the object.
     *
     * @param  Request  $request
     */
    public function toResponse($request): JsonResponse|RedirectResponse
    {
        $redirect = redirect()->intended(self::home($request));

        if ($request->wantsJson()) {
            return new JsonResponse(['two_factor' => false, 'redirect' => $redirect->getTargetUrl()]);
        }

        return $redirect;
    }

    /**
     * Get the default landing page for the authenticated user.
     */
    public static function home(Request $request): string
    {
        return $request->user()?->isAdmin()
            ? route('admin.dashboard')
            : route('account.index');
    }
}
