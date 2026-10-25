<?php

namespace App\Services\TBank;

use RuntimeException;

class TBankException extends RuntimeException
{
    public static function notConfigured(string $mode): self
    {
        return new self("T-Bank {$mode} terminal is not set up: fill in TBANK_".strtoupper($mode).'_TERMINAL_KEY and TBANK_'.strtoupper($mode).'_PASSWORD.');
    }
}
