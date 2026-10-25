<?php

namespace App\Providers;

use App\Models\Order;
use App\Models\User;
use App\Services\Cart\Cart;
use App\Services\RetailCrm\RetailCrm;
use App\Services\TBank\TBank;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(RetailCrm::class, fn () => new RetailCrm(
            config('services.retailcrm.url'),
            config('services.retailcrm.api_key'),
            config('services.retailcrm.site'),
        ));

        $this->app->singleton(TBank::class, fn () => TBank::fromConfig());

        // One cart per request, so the header badge and the cart endpoints
        // share the lines they have loaded.
        $this->app->scoped(Cart::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAuthorization();
        $this->configureEvents();
    }

    /**
     * React to things happening elsewhere in the app.
     */
    protected function configureEvents(): void
    {
        // Orders placed as a guest join the account once its owner proves the email is theirs.
        Event::listen(function (Verified $event) {
            if ($event->user instanceof User) {
                Order::whereNull('user_id')->where('email', mb_strtolower($event->user->email))->update(['user_id' => $event->user->id]);
            }
        });
    }

    /**
     * Define the abilities that are not tied to a model policy.
     */
    protected function configureAuthorization(): void
    {
        Gate::define('access-admin', fn (User $user): bool => $user->isAdmin());
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
