<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
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
        ]);
    }
}
