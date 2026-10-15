<?php

namespace App\Filament\Support\Fields;

use App\Models\Student;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Builder;

/**
 * Picks a student by typing part of the name. There can be hundreds of
 * students, so the list is searched instead of loaded whole.
 */
final class StudentSelect
{
    private const LIMIT = 50;

    /**
     * @param  (callable(Builder): mixed)|null  $scope  narrows which students are offered
     */
    public static function make(string $name = 'student_id', ?callable $scope = null): Select
    {
        return Select::make($name)
            ->label('الطالب')
            ->searchable()
            ->options(fn (): array => self::search(null, $scope))
            ->getSearchResultsUsing(fn (string $search): array => self::search($search, $scope))
            ->getOptionLabelUsing(fn (mixed $value): ?string => Student::query()->whereKey($value)->value('full_name'))
            ->searchPrompt('اكتب جزءاً من اسم الطالب')
            ->markAsRequired();
    }

    /**
     * @return array<int, string> id => name
     */
    private static function search(?string $search, ?callable $scope): array
    {
        return Student::query()
            ->active()
            ->when($scope, fn (Builder $query) => $scope($query))
            ->when(filled($search), fn (Builder $query) => $query->where('full_name', 'like', '%'.Like::escape((string) $search).'%'))
            ->orderBy('full_name')
            ->limit(self::LIMIT)
            ->pluck('full_name', 'id')
            ->all();
    }
}
