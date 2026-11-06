<?php

namespace App\Filament\English\Resources\EnglishLevels;

use App\Actions\English\Levels\DeleteEnglishLevel;
use App\Actions\English\Levels\SaveEnglishLevel;
use App\Filament\English\EnglishPanelGroup;
use App\Filament\English\Resources\EnglishLevels\Pages\ManageEnglishLevels;
use App\Filament\English\Support\EnglishPresenter;
use App\Filament\Support\Attempt;
use App\Http\Requests\English\EnglishLevelRequest;
use App\Models\English\EnglishLevel;
use App\Support\FileSize;
use App\Support\UploadLimit;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\ImageEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * The levels of the English section (A1 ... C2), each with the design of
 * its students' attendance card.
 */
class EnglishLevelResource extends Resource
{
    protected static ?string $model = EnglishLevel::class;

    protected static ?string $slug = 'levels';

    protected static string|UnitEnum|null $navigationGroup = EnglishPanelGroup::Settings;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedListBullet;

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'مستوى';

    protected static ?string $pluralModelLabel = 'المستويات وتصاميم البطاقات';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('اسم المستوى')
                    ->markAsRequired()
                    ->autofocus()
                    ->extraInputAttributes(['dir' => 'ltr']),
                TextInput::make('sort_order')
                    ->label('الترتيب')
                    ->helperText('المستويات تُعرض من الرقم الأصغر إلى الأكبر.')
                    ->inputMode('numeric')
                    ->default(0),
                Fieldset::make('تصميم البطاقة')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        ImageEntry::make('current_background')
                            ->label('التصميم الحالي')
                            ->state(fn (?EnglishLevel $record): ?string => EnglishPresenter::cardBackgroundUrl($record))
                            ->imageHeight(172)
                            ->visible(fn (?EnglishLevel $record): bool => filled($record?->card_background_path)),
                        // Kept as it is (no cropping): checked and stored by the SaveEnglishLevel action.
                        FileUpload::make('card_background')
                            ->label(fn (?EnglishLevel $record): string => filled($record?->card_background_path) ? 'تصميم جديد بدل الحالي' : 'التصميم')
                            ->image()
                            ->storeFiles(false)
                            ->helperText(fn (): string => 'صورة وجه البطاقة كاملاً بالطول، بقياس 54 × 86 ملم (مثلاً 638 × 1016 بكسل)، والإطار حول الحواف. '
                                .'يكتب النظام فوقها صورة الطالب في الأعلى، واسمه وقاعته في الوسط، والرمز في الأسفل، فاترك الوسط فاتحاً. JPG أو PNG أو WebP، وأقصى حجم: '
                                .FileSize::arabic(UploadLimit::kilobytes(EnglishLevelRequest::BACKGROUND_MAX_KILOBYTES) * 1024).'.'),
                        Toggle::make('remove_card_background')
                            ->label('إزالة التصميم الحالي')
                            ->helperText('تُطبع البطاقات عندها بالشكل العادي.')
                            ->visible(fn (?EnglishLevel $record): bool => filled($record?->card_background_path)),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sort_order')
                    ->label('الترتيب')
                    ->sortable(),
                TextColumn::make('name')
                    ->label('المستوى')
                    ->searchable()
                    ->weight('medium'),
                ImageColumn::make('card_background')
                    ->label('تصميم البطاقة')
                    ->state(fn (EnglishLevel $record): ?string => EnglishPresenter::cardBackgroundUrl($record))
                    ->imageHeight(56)
                    ->placeholder('بلا تصميم'),
                TextColumn::make('courses_count')
                    ->label('الدورات')
                    ->counts('courses')
                    ->alignCenter(),
                TextColumn::make('students_count')
                    ->label('الطلاب بهذا المستوى')
                    ->counts('students')
                    ->alignCenter(),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(fn (EnglishLevel $record, array $data, Action $action, SaveEnglishLevel $saveLevel): EnglishLevel => Attempt::action(
                        $action,
                        fn (): EnglishLevel => $saveLevel->handle($data, $record),
                    )),
                DeleteAction::make()
                    ->modalDescription('يُحذف فقط المستوى الذي ليس له دورات ولا طلاب.')
                    ->using(fn (EnglishLevel $record, DeleteEnglishLevel $deleteLevel): bool => Attempt::confirmed(function () use ($record, $deleteLevel): bool {
                        $deleteLevel->handle($record);

                        return true;
                    })),
            ])
            ->defaultSort('sort_order')
            ->paginated(false);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageEnglishLevels::route('/'),
        ];
    }
}
