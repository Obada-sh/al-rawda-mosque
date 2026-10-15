<?php

namespace App\Filament\Widgets\Reports;

use App\Filament\Support\Presenters\ReportPresenter;
use App\Filament\Support\Reports\ReportRows;
use App\Filament\Widgets\Reports\Concerns\ReadsReportFilter;
use App\Services\Statistics\KnightEntry;
use App\Services\Statistics\QuranKnight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\PaginationMode;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * The Quran Knight ranking: who recited the most new pages in the period.
 */
class KnightRankingTable extends TableWidget
{
    use ReadsReportFilter;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->queryStringIdentifier('knight')
            ->heading('الفارس القرآني')
            ->description('الترتيب بعدد الصفحات التي سمّعها الطالب لأول مرة في الفترة. الصفحة المكررة لا تُحسب، والمتساوون يتشاركون المرتبة.')
            ->records(fn (int|string $page, int|string|null $recordsPerPage, ?string $search): LengthAwarePaginator => ReportRows::page(
                ReportRows::search($this->rows(), $search, ['student']),
                $page,
                $recordsPerPage,
                $this->getTablePaginationPageName(),
            ))
            ->columns([
                TextColumn::make('place')
                    ->label('المرتبة')
                    ->badge()
                    ->color(fn (int $state): string => ReportPresenter::placeColor($state))
                    ->alignCenter(),
                TextColumn::make('student')
                    ->label('الطالب')
                    ->weight('bold'),
                TextColumn::make('circles')
                    ->label('الحلقة')
                    ->listWithLineBreaks(),
                TextColumn::make('new_pages')
                    ->label('صفحات جديدة')
                    ->numeric()
                    ->alignCenter()
                    ->weight('bold'),
            ])
            ->searchable()
            ->paginationMode(PaginationMode::Default)
            ->emptyStateHeading('لا يوجد تسميع جديد في هذه الفترة');
    }

    /**
     * @return Collection<int, array<string, mixed>> keyed by student id, the first place first
     */
    private function rows(): Collection
    {
        $filter = $this->reportFilter();

        if ($filter === null) {
            return collect();
        }

        return app(QuranKnight::class)->ranking($filter)->mapWithKeys(fn (KnightEntry $entry): array => [
            $entry->studentId => [
                'place' => $entry->place,
                'student' => $entry->studentName,
                'circles' => $entry->circles,
                'new_pages' => $entry->newPages,
            ],
        ]);
    }
}
