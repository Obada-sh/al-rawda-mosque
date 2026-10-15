<?php

namespace App\Filament\Support\Filters;

use App\Support\Dates;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Illuminate\Database\Eloquent\Builder;

/**
 * A "from date – to date" table filter on a date column, or on a date
 * column of a related record (for example the date of an attendance, which
 * is stored on its session).
 */
final class DateRangeFilter
{
    public static function make(string $column, string $label, ?string $relationship = null): Filter
    {
        return Filter::make($column.'_range')
            ->schema([
                DatePicker::make('from')->label($label.' من'),
                DatePicker::make('until')->label($label.' إلى'),
            ])
            ->columns(2)
            ->query(function (Builder $query, array $data) use ($column, $relationship): Builder {
                $from = filled($data['from'] ?? null) ? Dates::day($data['from']) : null;
                $until = filled($data['until'] ?? null) ? Dates::day($data['until']) : null;

                if ($from === null && $until === null) {
                    return $query;
                }

                $between = fn (Builder $query): Builder => $query
                    ->when($from, fn (Builder $query, string $day) => $query->where($column, '>=', $day))
                    ->when($until, fn (Builder $query, string $day) => $query->where($column, '<=', $day));

                return $relationship === null ? $between($query) : $query->whereHas($relationship, $between);
            })
            ->indicateUsing(function (array $data) use ($label): array {
                $indicators = [];

                if (filled($data['from'] ?? null)) {
                    $indicators[] = Indicator::make($label.' من '.Dates::show($data['from']))
                        ->removeField('from');
                }

                if (filled($data['until'] ?? null)) {
                    $indicators[] = Indicator::make($label.' إلى '.Dates::show($data['until']))
                        ->removeField('until');
                }

                return $indicators;
            });
    }
}
