<?php

namespace App\Services\Cdek;

/**
 * What CDEK charges for a tariff and how many working days it takes.
 */
class CdekRate
{
    public function __construct(
        public readonly int $tariff,
        public readonly float $price,
        public readonly int $daysMin,
        public readonly int $daysMax,
    ) {}

    /**
     * "2-4 дня" or "3 дня"; null when CDEK gave no estimate.
     */
    public function days(): ?string
    {
        if ($this->daysMax <= 0) {
            return null;
        }

        $days = $this->daysMin > 0 && $this->daysMin < $this->daysMax ? "{$this->daysMin}-{$this->daysMax}" : (string) $this->daysMax;

        return trans_choice(':days day|:days days', $this->daysMax, ['days' => $days]);
    }
}
