<?php

namespace App\Filament\Resources\Expenses\Schemas;

use App\Enums\ExpenseKind;
use App\Exceptions\BusinessRuleViolation;
use App\Filament\Support\Options;
use App\Models\Expense;
use App\Support\Dates;
use App\Support\Money;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * An expense, in Syrian pounds: an item with its quantity and unit price,
 * or only the amount paid with a description.
 */
class ExpenseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                ToggleButtons::make('kind')
                    ->label('طريقة التسجيل')
                    ->options(ExpenseKind::options())
                    ->default(ExpenseKind::Itemized->value)
                    ->inline()
                    ->live()
                    ->markAsRequired()
                    ->helperText(fn (Get $get): string => self::isLumpSum($get)
                        ? 'لمشتريات متعددة لا تُعرف أصنافها وكمياتها: الوصف والمبلغ المدفوع فقط.'
                        : 'صنف واحد: الكمية × سعر الواحدة.')
                    ->columnSpanFull(),
                Select::make('department_id')
                    ->label('القسم')
                    ->options(fn (?Expense $record): array => Options::departments($record?->department_id))
                    ->markAsRequired(),
                DatePicker::make('expense_date')
                    ->label('تاريخ المصروف')
                    ->default(fn (): string => Dates::today()->toDateString())
                    ->maxDate(fn () => Dates::today())
                    ->markAsRequired(),
                TextInput::make('item_name')
                    ->label(fn (Get $get): string => self::isLumpSum($get) ? 'الوصف' : 'الصنف')
                    ->placeholder(fn (Get $get): ?string => self::isLumpSum($get) ? 'مثلاً: مشتريات متنوعة لضيافة الحفل' : null)
                    ->markAsRequired()
                    ->columnSpanFull(),
                TextInput::make('quantity')
                    ->label('الكمية')
                    ->inputMode('decimal')
                    ->default('1')
                    ->live(onBlur: true)
                    ->visible(fn (Get $get): bool => ! self::isLumpSum($get))
                    ->markAsRequired(),
                TextInput::make('unit_price')
                    ->label('سعر الواحدة')
                    ->inputMode('decimal')
                    ->suffix(Money::CURRENCY)
                    ->live(onBlur: true)
                    ->visible(fn (Get $get): bool => ! self::isLumpSum($get))
                    ->markAsRequired(),
                TextEntry::make('total_preview')
                    ->label('الإجمالي')
                    ->helperText('يُحسب تلقائياً: الكمية × سعر الواحدة.')
                    ->state(fn (Get $get): string => self::totalPreview($get('quantity'), $get('unit_price')))
                    ->visible(fn (Get $get): bool => ! self::isLumpSum($get))
                    ->columnSpanFull(),
                TextInput::make('amount')
                    ->label('المبلغ المدفوع')
                    ->inputMode('decimal')
                    ->suffix(Money::CURRENCY)
                    ->visible(fn (Get $get): bool => self::isLumpSum($get))
                    ->markAsRequired()
                    ->columnSpanFull(),
                Textarea::make('notes')
                    ->label('ملاحظات')
                    ->rows(2)
                    ->columnSpanFull(),
            ]);
    }

    /**
     * What the form shows when an expense is corrected: how it was
     * written, and for an amount paid as a whole, that amount.
     *
     * @return array<string, mixed>
     */
    public static function fill(Expense $expense): array
    {
        return [
            ...$expense->attributesToArray(),
            'kind' => $expense->kind->value,
            'amount' => $expense->kind === ExpenseKind::LumpSum ? $expense->total : null,
        ];
    }

    private static function isLumpSum(Get $get): bool
    {
        return $get('kind') === ExpenseKind::LumpSum->value;
    }

    /**
     * What the total will be, shown while the admin types. The stored total
     * is always computed again by the SaveExpense action.
     */
    private static function totalPreview(mixed $quantity, mixed $unitPrice): string
    {
        if (blank($quantity) || blank($unitPrice)) {
            return '—';
        }

        try {
            return Money::pounds(Money::lineTotal((string) $quantity, (string) $unitPrice));
        } catch (BusinessRuleViolation) {
            return '—';
        }
    }
}
