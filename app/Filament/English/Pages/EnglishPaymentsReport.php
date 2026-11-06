<?php

namespace App\Filament\English\Pages;

use App\Enums\English\PaymentState;
use App\Filament\English\EnglishPanelGroup;
use App\Filament\English\Resources\EnglishPayments\EnglishPaymentResource;
use App\Filament\English\Support\EnglishOptions;
use App\Filament\English\Widgets\EnglishLetterhead;
use App\Filament\English\Widgets\Money\BalanceStats;
use App\Filament\English\Widgets\Money\CoursesMoneyTable;
use App\Filament\English\Widgets\Money\StudentsMoneyTable;
use App\Filament\Pages\Reports\FilteredDashboard;
use App\Services\English\EnglishBalanceFilter;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Who paid what: for every course, or one course, or one hall, each
 * student's price, what they paid and what remains, added up by course
 * and for everything. "Who has not paid" is a choice of the filter.
 */
class EnglishPaymentsReport extends FilteredDashboard
{
    protected static string $routePath = 'payments-report';

    protected static ?string $title = 'تقرير الأقساط';

    protected static string|UnitEnum|null $navigationGroup = EnglishPanelGroup::Finance;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?int $navigationSort = 11;

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    // The filter is for the screen: on paper the letterhead says what was chosen.
                    ->extraAttributes(['class' => 'rawda-screen-only'])
                    ->columns(['md' => 4])
                    ->columnSpanFull()
                    ->schema([
                        Select::make('english_course_id')
                            ->label('الدورة')
                            ->placeholder('كل الدورات')
                            ->options(fn (): array => EnglishOptions::courses())
                            ->searchable()
                            ->afterStateUpdated(fn (Set $set) => $set('english_hall_id', null)),
                        Select::make('english_hall_id')
                            ->label('القاعة')
                            ->placeholder(fn (Get $get): string => blank($get('english_course_id')) ? 'اختر الدورة أولاً' : 'كل القاعات')
                            ->options(fn (Get $get): array => EnglishOptions::hallsOfCourse($get('english_course_id')))
                            ->disabled(fn (Get $get): bool => blank($get('english_course_id'))),
                        Select::make('state')
                            ->label('الدفع')
                            ->placeholder('الكل')
                            ->options(PaymentState::options()),
                        Toggle::make('with_left')
                            ->label('مع من خرجوا من الدورة')
                            ->default(true)
                            ->inline(false),
                    ]),
            ]);
    }

    protected function getHeaderWidgets(): array
    {
        return [
            EnglishLetterhead::make(['heading' => (string) $this->getTitle()]),
        ];
    }

    public function getWidgets(): array
    {
        return [
            BalanceStats::class,
            CoursesMoneyTable::class,
            StudentsMoneyTable::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->printAction(),
            Action::make('payments')
                ->label('قائمة الدفعات')
                ->icon(Heroicon::OutlinedBanknotes)
                ->color('gray')
                ->url(fn (): string => EnglishPaymentResource::getUrl()),
        ];
    }

    protected function checkFilters(array $filters): void
    {
        EnglishBalanceFilter::fromInput($filters);
    }
}
