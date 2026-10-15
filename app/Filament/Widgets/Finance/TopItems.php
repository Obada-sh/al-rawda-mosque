<?php

namespace App\Filament\Widgets\Finance;

use App\Filament\Support\Presenters\ReportPresenter;
use App\Filament\Widgets\Finance\Concerns\ReadsExpenseFilter;
use App\Services\Finance\ExpenseReport;
use App\Services\Finance\ItemSpending;
use App\Support\Money;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Collection;

/**
 * The items the most money was spent on.
 */
class TopItems extends TableWidget
{
    use ReadsExpenseFilter;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('أكثر الأصناف صرفاً')
            ->description('أعلى '.ExpenseReport::TOP_ITEMS.' أصناف. المصاريف المكتوبة بالاسم نفسه تُجمع في صنف واحد، والمبالغ الإجمالية لا تدخل هنا لأنها ليست أصنافاً.')
            ->records(fn (): Collection => $this->rows())
            ->columns([
                TextColumn::make('item')
                    ->label('الصنف')
                    ->weight('medium')
                    ->wrap(),
                TextColumn::make('times')
                    ->label('مرات الشراء')
                    ->numeric()
                    ->alignCenter(),
                TextColumn::make('quantity')
                    ->label('مجموع الكمية')
                    ->alignCenter(),
                TextColumn::make('total')
                    ->label('الإجمالي')
                    ->alignEnd()
                    ->weight('bold'),
            ])
            ->paginated(false)
            ->emptyStateHeading('لا توجد مصاريف في هذه الفترة');
    }

    /**
     * @return Collection<string, array<string, mixed>> the most expensive item first
     */
    private function rows(): Collection
    {
        $filter = $this->expenseFilter();

        if ($filter === null) {
            return collect();
        }

        return app(ExpenseReport::class)->topItems($filter)->values()->mapWithKeys(fn (ItemSpending $item, int $index): array => [
            'item-'.($index + 1) => [
                'item' => $item->itemName,
                'times' => $item->times,
                'quantity' => ReportPresenter::quantity($item->quantity),
                'total' => Money::pounds($item->total),
            ],
        ]);
    }
}
