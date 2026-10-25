<?php

namespace App\Http\Requests\Account;

use App\Concerns\PasswordValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AccountDeleteRequest extends FormRequest
{
    use PasswordValidationRules;

    /**
     * Get the error bag the validation errors are stored in.
     *
     * @var string
     */
    protected $errorBag = 'deleteAccount';

    /**
     * Determine if the user is authorized to make this request.
     *
     * Admin accounts are removed by another admin, so the shop can never be
     * left without one.
     */
    public function authorize(): bool
    {
        return ! $this->user()->isAdmin();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'password' => $this->currentPasswordRules(),
        ];
    }
}
