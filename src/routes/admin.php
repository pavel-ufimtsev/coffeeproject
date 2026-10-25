<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\Settings\ProfileController;
use App\Http\Controllers\Admin\Settings\SecurityController;
use App\Http\Controllers\Admin\UserController;
/* @chisel-password-confirmation */
use Illuminate\Auth\Middleware\RequirePassword;
/* @end-chisel-password-confirmation */
use Illuminate\Support\Facades\Route;

/*
| Admin area (Inertia). Loaded from bootstrap/app.php with the "/admin"
| prefix, the "admin." name prefix and the "admin" middleware group.
*/

Route::get('/', DashboardController::class)->name('dashboard');

Route::resource('users', UserController::class)->except('show');

Route::redirect('settings', '/admin/settings/profile')->name('settings');

Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');

Route::get('settings/security', [SecurityController::class, 'edit'])
    /* @chisel-password-confirmation */
    ->middleware(RequirePassword::class)
    /* @end-chisel-password-confirmation */
    ->name('security.edit');

Route::put('settings/password', [SecurityController::class, 'update'])
    ->middleware('throttle:6,1')
    ->name('password.update');

Route::inertia('settings/appearance', 'admin/settings/Appearance')->name('appearance.edit');
