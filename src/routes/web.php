<?php

use App\Http\Controllers\Account\AccountController;
use App\Http\Controllers\Account\PasswordController;
use App\Http\Controllers\Account\ProfileController;
use App\Http\Controllers\Shop\CatalogController;
use App\Http\Controllers\Shop\HomeController;
use App\Http\Controllers\Shop\SitemapController;
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
