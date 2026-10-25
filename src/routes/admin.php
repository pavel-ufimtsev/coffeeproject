<?php

use App\Http\Controllers\Admin\Catalog\CategoryController;
use App\Http\Controllers\Admin\Catalog\CollectionController;
use App\Http\Controllers\Admin\Catalog\FilterGroupController;
use App\Http\Controllers\Admin\Catalog\FilterValueController;
use App\Http\Controllers\Admin\Catalog\LocationController;
use App\Http\Controllers\Admin\Catalog\ProductController;
use App\Http\Controllers\Admin\Catalog\ProductImageController;
use App\Http\Controllers\Admin\Catalog\ProductRetailCrmController;
use App\Http\Controllers\Admin\Catalog\RedirectController;
use App\Http\Controllers\Admin\Catalog\RetailCrmController;
use App\Http\Controllers\Admin\Catalog\RoasterController;
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

// Catalog. Reorder routes come before the resources so "order" is not taken for an ID.
Route::resource('products', ProductController::class)->except('show');
Route::post('products/{product}/images', [ProductImageController::class, 'store'])->name('products.images.store');
Route::put('products/{product}/images/order', [ProductImageController::class, 'reorder'])->name('products.images.reorder');
Route::patch('product-images/{image}', [ProductImageController::class, 'update'])->name('product-images.update');
Route::delete('product-images/{image}', [ProductImageController::class, 'destroy'])->name('product-images.destroy');
Route::post('products/{product}/retailcrm', [ProductRetailCrmController::class, 'store'])->name('products.retailcrm.store');
Route::put('products/{product}/retailcrm', [ProductRetailCrmController::class, 'update'])->name('products.retailcrm.update');

Route::put('categories/order', [CategoryController::class, 'reorder'])->name('categories.reorder');
Route::resource('categories', CategoryController::class)->except('show');

Route::put('filter-groups/order', [FilterGroupController::class, 'reorder'])->name('filter-groups.reorder');
Route::resource('filter-groups', FilterGroupController::class)->except('show');
Route::put('filter-groups/{filter_group}/filter-values/order', [FilterValueController::class, 'reorder'])->name('filter-groups.filter-values.reorder');
Route::resource('filter-groups.filter-values', FilterValueController::class)->shallow()->except(['index', 'show']);

Route::put('collections/order', [CollectionController::class, 'reorder'])->name('collections.reorder');
Route::resource('collections', CollectionController::class)->except('show');

Route::put('roasters/order', [RoasterController::class, 'reorder'])->name('roasters.reorder');
Route::resource('roasters', RoasterController::class)->except('show');
Route::resource('roasters.locations', LocationController::class)->shallow()->except(['index', 'show']);

Route::resource('redirects', RedirectController::class)->except('show');

Route::get('retailcrm', [RetailCrmController::class, 'index'])->name('retailcrm.index');
Route::post('retailcrm/sync', [RetailCrmController::class, 'sync'])->middleware('throttle:6,1')->name('retailcrm.sync');
Route::get('retailcrm/search', [RetailCrmController::class, 'search'])->name('retailcrm.search');
Route::get('retailcrm/unlinked', [RetailCrmController::class, 'unlinked'])->name('retailcrm.unlinked');
Route::post('retailcrm/ignored', [RetailCrmController::class, 'ignore'])->name('retailcrm.ignored.store');
Route::delete('retailcrm/ignored/{ignored}', [RetailCrmController::class, 'unignore'])->name('retailcrm.ignored.destroy');

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
