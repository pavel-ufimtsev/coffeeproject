<?php

namespace App\Http\Requests\Admin;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $model = $this->managedUser();

        if (! $this->user()->can('update', $model)) {
            return false;
        }

        return $this->input('role') === $model->role->value
            || $this->user()->can('changeRole', $model);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * A blank password leaves the current one unchanged.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->profileRules($this->managedUser()->id),
            'role' => ['required', Rule::enum(Role::class)],
            'password' => ['nullable', ...array_slice($this->passwordRules(), 1)],
        ];
    }

    /**
     * Get the user being updated.
     */
    private function managedUser(): User
    {
        /** @var User */
        return $this->route('user');
    }
}
