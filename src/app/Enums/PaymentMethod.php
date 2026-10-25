<?php

namespace App\Enums;

use App\Services\TBank\TBank;

enum PaymentMethod: string
{
    /** Card, SBP or T-Pay on T-Bank's payment page, before delivery. */
    case TBank = 'tbank';

    /** Cash or card, paid to the courier on delivery. */
    case Cash = 'cash';

    /**
     * Get the human-readable name of the method.
     */
    public function label(): string
    {
        return match ($this) {
            self::TBank => __('Card online'),
            self::Cash => __('Cash or card on delivery'),
        };
    }

    /**
     * Get the payment type code in retailCRM.
     */
    public function retailCrmCode(): string
    {
        return match ($this) {
            self::TBank => 'tinkoff',
            self::Cash => 'cash',
        };
    }

    /**
     * Whether the customer pays on the bank's page before the order counts as placed.
     */
    public function isOnline(): bool
    {
        return $this === self::TBank;
    }

    /**
     * Determine whether the method is switched on in config/shop.php and,
     * for T-Bank, whether the terminal of the current mode is filled in.
     */
    public function isEnabled(): bool
    {
        return (bool) config("shop.payments.{$this->value}")
            && ($this !== self::TBank || app(TBank::class)->isConfigured());
    }

    /**
     * Get the methods customers can choose from right now.
     *
     * @return list<self>
     */
    public static function enabled(): array
    {
        return array_values(array_filter(self::cases(), fn (self $method) => $method->isEnabled()));
    }
}
