<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Enums\PanelGroup;
use App\Filament\Support\Reports\ReportFilterFields;
use App\Filament\Widgets\Reports\KnightRankingTable;
use App\Support\Dates;
use BackedEnum;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * The Quran Knight: the admin picks a period and sees who recited the
 * most new pages in it, in the whole mosque or in one course or circle.
 */
class KnightRanking extends ReportDashboard
{
    protected static string $routePath = 'quran-knight';

    protected static ?string $title = 'الفارس القرآني';

    protected static string|UnitEnum|null $navigationGroup = PanelGroup::Reports;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTrophy;

    protected static ?int $navigationSort = 20;

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    // The filter is for the screen: on paper the letterhead says what was chosen.
                    ->extraAttributes(['class' => 'rawda-screen-only'])
                    ->columns(['md' => 2, 'xl' => 4])
                    ->columnSpanFull()
                    ->schema([
                        // The period starts on the first day of this month.
                        ReportFilterFields::from()
                            ->default(fn (): string => Dates::today()->startOfMonth()->toDateString()),
                        ReportFilterFields::until()
                            ->default(fn (): string => Dates::today()->toDateString()),
                        ReportFilterFields::course(),
                        ReportFilterFields::circle(),
                    ]),
            ]);
    }

    public function getWidgets(): array
    {
        return [
            KnightRankingTable::class,
        ];
    }
}
