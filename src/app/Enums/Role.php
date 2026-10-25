<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case User = 'user';

    /**
     * Get the human-readable name of the role.
     */
    public function label(): string
    {
        return match ($this) {
            self::Admin => __('Admin'),
            self::User => __('User'),
        };
    }

    /**
     * Get the roles as value/label pairs for select inputs.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $role) => ['value' => $role->value, 'label' => $role->label()],
            self::cases(),
        );
    }
}
