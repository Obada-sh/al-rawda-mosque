<?php

namespace App\Filament\Resources\Courses\RelationManagers;

use App\Actions\Circles\DeleteCircle;
use App\Actions\Circles\SaveCircle;
use App\Filament\Support\Attempt;
use App\Filament\Support\Cards\CardPrintActions;
use App\Filament\Support\Documents\DocumentActions;
use App\Filament\Support\Options;
use App\Filament\Support\Presenters\CirclePresenter;
use App\Http\Requests\Courses\CircleRequest;
use App\Models\Circle;
use App\Models\Course;
use App\Support\FileSize;
use App\Support\UploadLimit;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\ImageEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The circles of the course. The admin creates them; a circle needs only a
 * name. Its supervisor and assistant are picked from the teachers list.
 */
class CirclesRelationManager extends RelationManager
{
    protected static string $relationship = 'circles';

    protected static ?string $title = 'الحلقات';

    protected static ?string $modelLabel = 'حلقة';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('اسم الحلقة')
                    ->markAsRequired()
                    ->autofocus()
                    ->columnSpanFull(),
                Select::make('supervisor_teacher_id')
                    ->label('المشرف')
                    ->options(fn (?Circle $record): array => Options::teachers($record?->supervisor_teacher_id))
                    ->searchable(),
                Select::make('assistant_teacher_id')
                    ->label('المساعد')
                    ->options(fn (?Circle $record): array => Options::teachers($record?->assistant_teacher_id))
                    ->searchable(),
                Toggle::make('is_active')
                    ->label('نشطة')
                    ->helperText('الحلقة غير النشطة لا يُسجَّل فيها طلاب جدد ولا يُنقل إليها أحد.')
                    ->default(true)
                    ->columnSpanFull(),
                Fieldset::make('شعار الحلقة')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        ImageEntry::make('current_logo')
                            ->label('الشعار الحالي')
                            ->state(fn (?Circle $record): ?string => CirclePresenter::logoUrl($record))
                            ->imageHeight(96)
                            ->visible(fn (?Circle $record): bool => filled($record?->logo_path)),
                        // Kept as it is (no cropping): checked and stored by the SaveCircle action.
                        FileUpload::make('logo')
                            ->label(fn (?Circle $record): string => filled($record?->logo_path) ? 'شعار جديد بدل الحالي' : 'الشعار')
                            ->image()
                            ->storeFiles(false)
                            ->helperText(fn (): string => 'اختياري. يُطبع على بطاقات طلاب الحلقة وأساتذتها. الأفضل PNG بخلفية شفافة. أقصى حجم: '
                                .FileSize::arabic(UploadLimit::kilobytes(CircleRequest::LOGO_MAX_KILOBYTES) * 1024).'.'),
                        Toggle::make('remove_logo')
                            ->label('إزالة الشعار الحالي')
                            ->visible(fn (?Circle $record): bool => filled($record?->logo_path)),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['supervisor', 'assistant']))
            ->columns([
                ImageColumn::make('logo')
                    ->label('الشعار')
                    ->state(fn (Circle $record): ?string => CirclePresenter::logoUrl($record))
                    ->imageHeight(36)
                    ->placeholder('—'),
                TextColumn::make('name')
                    ->label('الحلقة')
                    ->searchable()
                    ->weight('medium'),
                TextColumn::make('supervisor.full_name')
                    ->label('المشرف')
                    ->placeholder('—'),
                TextColumn::make('assistant.full_name')
                    ->label('المساعد')
                    ->placeholder('—'),
                TextColumn::make('enrollments_count')
                    ->label('الطلاب')
                    ->counts(['enrollments' => fn (Builder $query) => $query->whereNull('left_on')])
                    ->alignCenter(),
                IconColumn::make('is_active')
                    ->label('نشطة')
                    ->boolean()
                    ->alignCenter(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(fn (array $data, Action $action, SaveCircle $saveCircle): Circle => Attempt::action(
                        $action,
                        fn (): Circle => $saveCircle->handle($this->course(), $data),
                    )),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('cards')
                        ->label('بطاقات الحلقة')
                        ->icon(Heroicon::OutlinedIdentification)
                        ->color('gray')
                        ->url(fn (Circle $record): string => CardPrintActions::url($record->course_id, $record->getKey()))
                        ->openUrlInNewTab(),
                    DocumentActions::circleStudents(),
                    DocumentActions::circleCodes(),
                ])
                    ->label('طباعة')
                    ->icon(Heroicon::OutlinedPrinter)
                    ->color('gray')
                    ->button(),
                EditAction::make()
                    ->using(fn (Circle $record, array $data, Action $action, SaveCircle $saveCircle): Circle => Attempt::action(
                        $action,
                        fn (): Circle => $saveCircle->handle($this->course(), $data, $record),
                    )),
                DeleteAction::make()
                    ->modalDescription('تُحذف فقط الحلقة التي لم يدخلها أي طالب. الحلقة التي استُعملت يمكن تعطيلها.')
                    ->using(fn (Circle $record, DeleteCircle $deleteCircle): bool => Attempt::confirmed(function () use ($record, $deleteCircle): bool {
                        $deleteCircle->handle($record);

                        return true;
                    })),
            ])
            ->defaultSort('name')
            ->emptyStateHeading('لا توجد حلقات بعد')
            ->emptyStateDescription('أضف حلقات الدورة أولاً، ثم سجّل الطلاب فيها.')
            ->paginated(false);
    }

    private function course(): Course
    {
        /** @var Course */
        return $this->getOwnerRecord();
    }
}
