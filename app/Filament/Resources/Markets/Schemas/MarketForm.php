<?php

namespace App\Filament\Resources\Markets\Schemas;

use App\Filament\Support\Options;
use App\Models\Market;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

/**
 * A market: its course (chosen when it is opened, then fixed), its name,
 * and whether it sells.
 */
class MarketForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('course_id')
                    ->label('الدورة')
                    ->helperText('يشتري طلاب هذه الدورة بنقاطهم فيها.')
                    ->options(fn (): array => Options::courses())
                    ->default(fn (): ?int => Options::soleActiveCourseId())
                    ->searchable()
                    ->markAsRequired()
                    ->hiddenOn('edit'),
                TextEntry::make('course_name')
                    ->label('الدورة')
                    ->state(fn (?Market $record): ?string => $record?->course?->name)
                    ->visibleOn('edit'),
                TextInput::make('name')
                    ->label('اسم السوق')
                    ->placeholder('مثلاً: سوق نهاية الشهر')
                    ->markAsRequired()
                    ->autofocus(),
                Toggle::make('is_open')
                    ->label('السوق مفتوح للبيع')
                    ->helperText('السوق المغلق لا يبيع، وتبقى بضاعته ومبيعاته محفوظة.')
                    ->default(true),
            ]);
    }
}
