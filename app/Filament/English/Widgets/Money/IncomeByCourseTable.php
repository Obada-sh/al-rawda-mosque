<?php

namespace App\Filament\English\Widgets\Money;

use App\Filament\English\Widgets\Money\Concerns\ReadsMoneyFilters;
use App\Services\English\EnglishFinanceReport;
use App\Support\Money;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Collection;

/**
 * What the students of each course paid in the period.
 */
class IncomeByCourseTable extends TableWidget
{
    use ReadsMoneyFilters;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('الإيرادات لكل دورة')
            ->records(fn (): Collection => $this->rows())
            ->columns([
                TextColumn::make('course')->label('الدورة')->weight('medium'),
                TextColumn::make('payments')->label('عدد الدفعات')->alignCenter(),
                TextColumn::make('income')->label('المدفوع')->alignEnd()->weight('bold'),
            ])
            ->paginated(false)
            ->emptyStateHeading('لا توجد دفعات في هذه الفترة');
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function rows(): Collection
    {
        $filter = $this->financeFilter();

        if ($filter === null) {
            return collect();
        }

        return app(EnglishFinanceReport::class)->incomeByCourse($filter)->map(fn (array $row): array => [
            'course' => $row['course'],
            'payments' => $row['payments'],
            'income' => Money::pounds($row['income']),
        ]);
    }
}
