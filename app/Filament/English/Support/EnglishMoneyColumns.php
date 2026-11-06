<?php

namespace App\Filament\English\Support;

use App\Enums\English\PaymentState;
use App\Models\English\EnglishEnrollment;
use App\Services\English\EnglishBalance;
use App\Support\Money;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;

/**
 * The money columns of a table of enrollments (a course's students, a
 * student's courses): the price due, what was paid, what remains and where
 * the student stands. The table's query is passed through withMoney().
 */
final class EnglishMoneyColumns
{
    /**
     * @param  Builder<EnglishEnrollment>  $query
     * @return Builder<EnglishEnrollment>
     */
    public static function withMoney(Builder $query): Builder
    {
        return $query->with('course')->withSum('payments', 'amount');
    }

    public static function balance(EnglishEnrollment $enrollment): EnglishBalance
    {
        return new EnglishBalance(
            due: $enrollment->duePrice(),
            paid: (int) ($enrollment->getAttribute('payments_sum_amount') ?? 0),
        );
    }

    /**
     * @return list<TextColumn>
     */
    public static function make(): array
    {
        return [
            TextColumn::make('due')
                ->label('السعر')
                ->state(fn (EnglishEnrollment $record): string => Money::pounds($record->duePrice()))
                ->description(fn (EnglishEnrollment $record): ?string => $record->hasOwnPrice() ? "سعر خاص: {$record->price_note}" : null)
                ->alignCenter(),
            TextColumn::make('payments_sum_amount')
                ->label('المدفوع')
                ->state(fn (EnglishEnrollment $record): string => Money::pounds(self::balance($record)->paid))
                ->alignCenter(),
            TextColumn::make('remaining')
                ->label('المتبقي')
                ->state(fn (EnglishEnrollment $record): string => Money::pounds(self::balance($record)->remaining()))
                ->weight('bold')
                ->alignCenter(),
            TextColumn::make('payment_state')
                ->label('الدفع')
                ->state(fn (EnglishEnrollment $record): PaymentState => self::balance($record)->state())
                ->formatStateUsing(fn (PaymentState $state): string => $state->label())
                ->badge()
                ->color(fn (PaymentState $state): string => $state->color()),
        ];
    }
}
