<?php

namespace App\Services\Memorization;

use App\Models\QuranJuz;
use Illuminate\Support\Collection;

/**
 * The 30 juz and their page ranges, read once per request.
 */
final class JuzMap
{
    /** @var Collection<int, QuranJuz>|null */
    private ?Collection $juz = null;

    /**
     * @return Collection<int, QuranJuz> ordered by juz number
     */
    public function all(): Collection
    {
        return $this->juz ??= QuranJuz::query()->orderBy('juz_no')->get();
    }

    public function find(int $juzNo): ?QuranJuz
    {
        return $this->all()->firstWhere('juz_no', $juzNo);
    }
}
