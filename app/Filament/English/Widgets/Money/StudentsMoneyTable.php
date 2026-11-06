<?php

namespace App\Filament\English\Widgets\Money;

use App\Enums\English\PaymentState;
use App\Filament\English\Widgets\Money\Concerns\ReadsMoneyFilters;
use App\Services\English\EnglishMoneyReport;
use App\Services\English\EnglishStudentBalance;
use App\Support\Money;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Collection;

/**
 * Student by student: the price due, what was paid, what remains.
 */
class StudentsMoneyTable extends TableWidget
{
    use ReadsMoneyFilters;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('لكل طالب')
            ->records(fn (): Collection => $this->rows())
            ->columns([
                TextColumn::make('student')->label('الطالب')->weight('medium')
                    ->description(fn (array $record): ?string => $record['note']),
                TextColumn::make('course')->label('الدورة'),
                TextColumn::make('hall')->label('القاعة'),
                TextColumn::make('due')->label('السعر')->alignEnd(),
                TextColumn::make('paid')->label('المدفوع')->alignEnd(),
                TextColumn::make('remaining')->label('المتبقي')->alignEnd()->weight('bold'),
                TextColumn::make('state')->label('الدفع')->badge()
                    ->formatStateUsing(fn (PaymentState $state): string => $state->label())
                    ->color(fn (PaymentState $state): string => $state->color()),
            ])
            ->paginated(false)
            ->emptyStateHeading('لا يوجد طلاب بهذا الاختيار');
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function rows(): Collection
    {
        $filter = $this->balanceFilter();

        if ($filter === null) {
            return collect();
        }

        return app(EnglishMoneyReport::class)->students($filter)->mapWithKeys(fn (EnglishStudentBalance $line): array => [
            $line->enrollmentId => [
                'student' => $line->studentName,
                'note' => collect([
                    $line->priceNote === null ? null : "سعر خاص: {$line->priceNote}",
                    $line->hasLeft ? 'خرج من الدورة' : null,
                ])->filter()->implode(' · ') ?: null,
                'course' => $line->courseName,
                'hall' => $line->hallName,
                'due' => Money::pounds($line->balance->due),
                'paid' => Money::pounds($line->balance->paid),
                'remaining' => Money::pounds($line->balance->remaining()),
                'state' => $line->balance->state(),
            ],
        ]);
    }
}
