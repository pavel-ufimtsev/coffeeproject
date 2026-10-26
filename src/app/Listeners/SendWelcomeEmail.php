<?php

namespace App\Listeners;

use App\Mail\Welcome;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Mail;

/**
 * Greets customers who sign up on the site. Accounts an admin creates do
 * not fire Registered, so they get nothing.
 */
class SendWelcomeEmail
{
    public function handle(Registered $event): void
    {
        if ($event->user instanceof User) {
            Mail::to($event->user)->queue(new Welcome($event->user));
        }
    }
}
