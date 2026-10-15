<?php

namespace App\Filament\Resources\Markets\Tables;

use App\Filament\Pages\Market\SalesDesk;
use App\Filament\Resources\Markets\MarketResource;
use App\Filament\Support\Options;
use App\Models\Market;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MarketsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with('course')
                ->withCount(['items', 'sales'])
                ->withSum('sales', 'total_points'))
            ->columns([
                TextColumn::make('name')
                    ->label('السوق')
                    ->weight('medium')
                    ->searchable(),
                TextColumn::make('course.name')
                    ->label('الدورة'),
                IconColumn::make('is_open')
                    ->label('مفتوح')
                    ->boolean()
                    ->alignCenter(),
                TextColumn::make('items_count')
                    ->label('البضاعة')
                    ->alignCenter(),
                TextColumn::make('sales_count')
                    ->label('عمليات الشراء')
                    ->alignCenter(),
                TextColumn::make('sales_sum_total_points')
                    ->label('النقاط المصروفة')
                    ->placeholder('0')
                    ->alignCenter(),
            ])
            ->filters([
                SelectFilter::make('course_id')
                    ->label('الدورة')
                    ->options(fn (): array => Options::courses()),
                TernaryFilter::make('is_open')
                    ->label('الحالة')
                    ->trueLabel('المفتوحة')
                    ->falseLabel('المغلقة'),
            ])
            ->recordActions([
                Action::make('desk')
                    ->label('نافذة البيع')
                    ->icon(Heroicon::OutlinedShoppingCart)
                    ->color('primary')
                    ->visible(fn (Market $record): bool => $record->is_open)
                    ->url(fn (Market $record): string => SalesDesk::getUrl(['market' => $record->getKey()])),
                EditAction::make()
                    ->label('البضاعة والمبيعات')
                    ->url(fn (Market $record): string => MarketResource::getUrl('edit', ['record' => $record])),
            ])
            ->defaultSort('id', 'desc')
            ->emptyStateHeading('لا توجد أسواق بعد')
            ->emptyStateDescription('افتح سوقاً لدورة، ثم أضف بضاعته وأسعارها بالنقاط.');
    }
}
