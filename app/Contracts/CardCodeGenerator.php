<?php

namespace App\Contracts;

/**
 * Produces the code printed (as QR) on an attendance card.
 *
 * Codes must be digits only: the scanner types like a keyboard, and digits
 * are the only characters that survive an Arabic keyboard layout.
 */
interface CardCodeGenerator
{
    /**
     * A code that no enrollment is using yet.
     */
    public function generate(): string;
}
