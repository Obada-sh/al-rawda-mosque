<?php

namespace App\Filament\Resources\Markets\Pages;

use App\Actions\Market\DeleteMarket;
use App\Actions\Market\SaveMarket;
use App\Filament\Pages\Market\SalesDesk;
use App\Filament\Resources\Markets\MarketResource;
use App\Filament\Support\Attempt;
use App\Models\Market;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

/**
 * A market: its name and whether it sells, then its items and its sales.
 */
class EditMarket extends EditRecord
{
    protected static string $resource = MarketResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('desk')
                ->label('نافذة البيع')
                ->icon(Heroicon::OutlinedShoppingCart)
                ->visible(fn (Market $record): bool => $record->is_open)
                ->url(fn (Market $record): string => SalesDesk::getUrl(['market' => $record->getKey()])),
            DeleteAction::make()
                ->modalDescription('يُحذف فقط السوق الذي لم يُبع فيه شيء، مع بضاعته. السوق الذي باع يُغلق بدلاً من ذلك.')
                ->using(fn (Market $record, DeleteMarket $deleteMarket): bool => Attempt::confirmed(function () use ($record, $deleteMarket): bool {
                    $deleteMarket->handle($record);

                    return true;
                })),
        ];
    }

    /**
     * @param  Market  $record
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return Attempt::form(
            fn (): Market => app(SaveMarket::class)->handle($data, $record),
            $this->form,
        );
    }
}
