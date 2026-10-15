<?php

namespace App\Filament\Resources\Markets\Pages;

use App\Actions\Market\SaveMarket;
use App\Filament\Resources\Markets\MarketResource;
use App\Filament\Resources\Markets\Schemas\MarketForm;
use App\Filament\Support\Attempt;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ListMarkets extends ListRecords
{
    protected static string $resource = MarketResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // A market is opened here, then filled with its items on its own page.
            Action::make('open')
                ->label('فتح سوق جديد')
                ->icon(Heroicon::OutlinedPlus)
                ->modalHeading('فتح سوق نقاط')
                ->modalSubmitActionLabel('فتح السوق')
                ->modalWidth('lg')
                ->schema(fn (Schema $schema): Schema => MarketForm::configure($schema))
                ->action(function (array $data, Action $action, SaveMarket $saveMarket): void {
                    $market = Attempt::action($action, fn () => $saveMarket->handle($data));

                    $this->redirect(MarketResource::getUrl('edit', ['record' => $market]));
                }),
        ];
    }
}
