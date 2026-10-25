<?php

use App\Http\Controllers\Account\AccountController;
use App\Http\Controllers\Account\PasswordController;
use App\Http\Controllers\Account\ProfileController;
use Illuminate\Support\Facades\Route;

/*
| Storefront and customer account pages (Blade). Login, registration and
| password reset routes are registered by Fortify and shared with admins.
| The admin area lives in routes/admin.php.
*/

Route::view('/', 'shop.home')->name('home');

Route::middleware('auth')->prefix('account')->name('account.')->group(function () {
    Route::get('/', [AccountController::class, 'index'])->name('index');
    Route::get('settings', [AccountController::class, 'edit'])->name('settings');
    Route::delete('/', [AccountController::class, 'destroy'])->name('destroy');

    Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::put('password', [PasswordController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('password.update');
});

/* @chisel-passkeys */
Route::get('.well-known/passkey-endpoints', function () {
    return response()->json([
        'enroll' => route('admin.security.edit'),
        'manage' => route('admin.security.edit'),
    ]);
})->name('well-known.passkeys');
/* @end-chisel-passkeys */
