<?php

namespace App\Services\Finance;

/**
 * What was spent on one item (every expense written with the same name).
 */
final readonly class ItemSpending
{
    /**
     * @param  string  $quantity  decimal string such as "12.500"
     * @param  string  $total  decimal string such as "1250.50"
     * @param  int  $times  how many times the item was bought
     */
    public function __construct(
        public string $itemName,
        public string $quantity,
        public string $total,
        public int $times,
    ) {}
}
