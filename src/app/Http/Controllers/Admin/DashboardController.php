<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProductStatus;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\RetailCrmSyncRun;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Show the admin dashboard.
     */
    public function __invoke(): Response
    {
        return Inertia::render('admin/Dashboard', [
            'stats' => [
                'users' => User::where('role', Role::User)->count(),
                'admins' => User::where('role', Role::Admin)->count(),
                'newUsersThisWeek' => User::where('created_at', '>=', now()->subWeek())->count(),
            ],
            'catalog' => [
                'active' => Product::where('status', ProductStatus::Active)->count(),
                'drafts' => Product::where('status', ProductStatus::Draft)->count(),
                'needsAttention' => Product::needsAttention()->count(),
            ],
            'lastSync' => ($run = RetailCrmSyncRun::latest('id')->first()) ? [
                'started_at' => $run->started_at->toDateTimeString(),
                'error' => $run->error,
                'issues' => count($run->issues ?? []),
                'changed' => $run->variants_changed,
            ] : null,
        ]);
    }
}
