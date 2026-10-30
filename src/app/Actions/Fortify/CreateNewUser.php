<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Session key the register view stamps with the time the form was shown.
     */
    public const SHOWN_AT = 'register.shown_at';

    /**
     * People need longer than this to fill in four fields; bots post within a second.
     */
    private const MIN_FILL_SECONDS = 3;

    private const MAX_PER_IP_PER_HOUR = 5;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        $this->rejectBots($input);

        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        return User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
        ]);
    }

    /**
     * Bots sign victims' addresses up to send them our emails, so turn away
     * honeypot hits, forms posted too fast or without being shown, and floods
     * from one IP.
     *
     * @param  array<string, string>  $input
     */
    private function rejectBots(array $input): void
    {
        $shownAt = session()->pull(self::SHOWN_AT);
        $tooFast = ! $shownAt || now()->timestamp - $shownAt < self::MIN_FILL_SECONDS;
        $key = 'register:'.request()->ip();

        if (filled($input['website'] ?? null) || $tooFast || RateLimiter::tooManyAttempts($key, self::MAX_PER_IP_PER_HOUR)) {
            throw ValidationException::withMessages([
                'email' => __('We could not create the account. Please try again in a few minutes.'),
            ]);
        }

        RateLimiter::hit($key, 3600);
    }
}
