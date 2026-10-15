<?php

namespace App\Filament\Resources\Markets\RelationManagers;

use App\Actions\Market\DeleteMarketItem;
use App\Actions\Market\SaveMarketItem;
use App\Filament\Support\Attempt;
use App\Filament\Support\Presenters\MarketPresenter;
use App\Http\Requests\Market\MarketItemRequest;
use App\Models\Market;
use App\Models\MarketItem;
use App\Support\FileSize;
use App\Support\UploadLimit;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\ImageEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * What the market sells: each item with its picture, its description, its
 * price in points and what is left of it.
 */
class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'البضاعة';

    protected static ?string $modelLabel = 'بضاعة';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('اسم البضاعة')
                    ->markAsRequired()
                    ->autofocus()
                    ->columnSpanFull(),
                TextInput::make('price')
                    ->label('السعر بالنقاط')
                    ->inputMode('numeric')
                    ->suffix('نقطة')
                    ->markAsRequired(),
                TextInput::make('stock')
                    ->label('الكمية المتبقية')
                    ->inputMode('numeric')
                    ->helperText('اختيارية. اتركها فارغة إن لم يكن لها حد؛ وإن كتبتها نقصت مع كل بيعة وتوقف البيع حين تنفد.'),
                Textarea::make('description')
                    ->label('الوصف')
                    ->rows(2)
                    ->columnSpanFull(),
                Toggle::make('is_available')
                    ->label('معروضة في نافذة البيع')
                    ->default(true)
                    ->columnSpanFull(),
                Fieldset::make('صورة البضاعة')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        ImageEntry::make('current_image')
                            ->label('الصورة الحالية')
                            ->state(fn (?MarketItem $record): ?string => MarketPresenter::pictureUrl($record))
                            ->imageHeight(96)
                            ->visible(fn (?MarketItem $record): bool => filled($record?->image_path)),
                        // Kept as it is (no cropping): checked and stored by the SaveMarketItem action.
                        FileUpload::make('image')
                            ->label(fn (?MarketItem $record): string => filled($record?->image_path) ? 'صورة جديدة بدل الحالية' : 'الصورة')
                            ->image()
                            ->storeFiles(false)
                            ->helperText(fn (): string => 'اختيارية. JPG أو PNG أو WebP، وأقصى حجم: '
                                .FileSize::arabic(UploadLimit::kilobytes(MarketItemRequest::PICTURE_MAX_KILOBYTES) * 1024).'.'),
                        Toggle::make('remove_image')
                            ->label('إزالة الصورة الحالية')
                            ->visible(fn (?MarketItem $record): bool => filled($record?->image_path)),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withSum('saleLines', 'quantity'))
            ->columns([
                ImageColumn::make('picture')
                    ->label('الصورة')
                    ->state(fn (MarketItem $record): ?string => MarketPresenter::pictureUrl($record))
                    ->imageHeight(44)
                    ->placeholder('—'),
                TextColumn::make('name')
                    ->label('البضاعة')
                    ->description(fn (MarketItem $record): ?string => $record->description)
                    ->weight('medium')
                    ->wrap()
                    ->searchable(),
                TextColumn::make('price')
                    ->label('السعر')
                    ->formatStateUsing(fn (int $state): string => MarketPresenter::points($state))
                    ->sortable()
                    ->alignCenter(),
                TextColumn::make('stock')
                    ->label('المتبقي')
                    ->formatStateUsing(fn (MarketItem $record): ?string => MarketPresenter::stock($record))
                    ->placeholder('بلا حد')
                    ->badge()
                    ->color(fn (MarketItem $record): string => $record->stock === 0 ? 'danger' : 'gray')
                    ->alignCenter(),
                TextColumn::make('sale_lines_sum_quantity')
                    ->label('بيع منها')
                    ->placeholder('0')
                    ->alignCenter(),
                IconColumn::make('is_available')
                    ->label('معروضة')
                    ->boolean()
                    ->alignCenter(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('إضافة بضاعة')
                    ->using(fn (array $data, Action $action, SaveMarketItem $saveMarketItem): MarketItem => Attempt::action(
                        $action,
                        fn (): MarketItem => $saveMarketItem->handle($this->market(), $data),
                    )),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(fn (MarketItem $record, array $data, Action $action, SaveMarketItem $saveMarketItem): MarketItem => Attempt::action(
                        $action,
                        fn (): MarketItem => $saveMarketItem->handle($this->market(), $data, $record),
                    )),
                DeleteAction::make()
                    ->modalDescription('تُحذف فقط البضاعة التي لم يُبع منها شيء. البضاعة التي بيعت يوقَف عرضها بدلاً من ذلك.')
                    ->using(fn (MarketItem $record, DeleteMarketItem $deleteMarketItem): bool => Attempt::confirmed(function () use ($record, $deleteMarketItem): bool {
                        $deleteMarketItem->handle($record);

                        return true;
                    })),
            ])
            ->defaultSort('price')
            ->emptyStateHeading('لا توجد بضاعة بعد')
            ->emptyStateDescription('أضف ما يبيعه السوق: الاسم والصورة والوصف والسعر بالنقاط.')
            ->paginated(false);
    }

    private function market(): Market
    {
        /** @var Market */
        return $this->getOwnerRecord();
    }
}
