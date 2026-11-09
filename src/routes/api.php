<?php

use App\Http\Controllers\Api\Blog\ImageController;
use App\Http\Controllers\Api\Blog\LookupController;
use App\Http\Controllers\Api\Blog\PostController;
use App\Http\Controllers\Api\Blog\RevisionController;
use App\Http\Controllers\Api\Blog\TaxonomyController;
use App\Http\Middleware\EnsureActiveApiAgent;
use App\Models\ApiAgent;
use Illuminate\Support\Facades\Route;

/*
| JSON API for AI agents, under /api. Agents send a bearer token issued with
| `php artisan api:agent-token`. See docs/BLOG_API.md.
*/

Route::prefix('blog/v1')
    ->name('api.blog.')
    ->middleware(['auth:sanctum', EnsureActiveApiAgent::class, 'abilities:'.ApiAgent::BLOG_ABILITY, 'throttle:blog-api'])
    ->group(function () {
        Route::get('schema', [LookupController::class, 'schema'])->name('schema');

        Route::get('posts', [PostController::class, 'index'])->name('posts.index');
        Route::post('posts', [PostController::class, 'store'])->name('posts.store');
        Route::get('posts/{post}', [PostController::class, 'show'])->name('posts.show');
        Route::patch('posts/{post}', [PostController::class, 'update'])->name('posts.update');
        Route::delete('posts/{post}', [PostController::class, 'destroy'])->name('posts.destroy');
        Route::post('posts/{post}/publish', [PostController::class, 'publish'])->name('posts.publish');
        Route::post('posts/{post}/unpublish', [PostController::class, 'unpublish'])->name('posts.unpublish');
        Route::post('posts/{post}/archive', [PostController::class, 'archive'])->name('posts.archive');

        Route::get('posts/{post}/revisions', [RevisionController::class, 'index'])->name('revisions.index');
        Route::get('posts/{post}/revisions/{revision}', [RevisionController::class, 'show'])->whereNumber('revision')->name('revisions.show');
        Route::post('posts/{post}/revisions/{revision}/restore', [RevisionController::class, 'restore'])->whereNumber('revision')->name('revisions.restore');

        Route::apiResource('images', ImageController::class);

        Route::get('categories', [TaxonomyController::class, 'categories'])->name('categories.index');
        Route::post('categories', [TaxonomyController::class, 'storeCategory'])->name('categories.store');
        Route::patch('categories/{category}', [TaxonomyController::class, 'updateCategory'])->name('categories.update');
        Route::delete('categories/{category}', [TaxonomyController::class, 'destroyCategory'])->name('categories.destroy');

        Route::get('tags', [TaxonomyController::class, 'tags'])->name('tags.index');
        Route::post('tags', [TaxonomyController::class, 'storeTag'])->name('tags.store');
        Route::patch('tags/{tag}', [TaxonomyController::class, 'updateTag'])->name('tags.update');
        Route::delete('tags/{tag}', [TaxonomyController::class, 'destroyTag'])->name('tags.destroy');

        Route::get('products', [LookupController::class, 'products'])->name('products');

        Route::get('redirects', [LookupController::class, 'redirects'])->name('redirects.index');
        Route::post('redirects', [LookupController::class, 'storeRedirect'])->name('redirects.store');
        Route::delete('redirects/{redirect}', [LookupController::class, 'destroyRedirect'])->name('redirects.destroy');
    });
