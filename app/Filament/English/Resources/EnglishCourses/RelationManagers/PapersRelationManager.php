<?php

namespace App\Filament\English\Resources\EnglishCourses\RelationManagers;

use App\Actions\English\Papers\DeleteEnglishPaper;
use App\Actions\English\Papers\SaveEnglishPaper;
use App\Filament\Support\Attempt;
use App\Http\Requests\English\EnglishPaperRequest;
use App\Models\English\EnglishCourse;
use App\Models\English\EnglishPaper;
use App\Support\FileSize;
use App\Support\UploadLimit;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * The papers of the course: sheets and pictures the admin adds, for the
 * students to see later. For now they are kept here, opened and printed
 * from here.
 */
class PapersRelationManager extends RelationManager
{
    protected static string $relationship = 'papers';

    protected static ?string $title = 'الأوراق';

    protected static ?string $modelLabel = 'ورقة';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('العنوان')
                    ->placeholder('مثلاً: كلمات الدرس الثالث')
                    ->markAsRequired()
                    ->autofocus()
                    ->columnSpanFull(),
                // Kept as it is: checked and stored by the SaveEnglishPaper action.
                FileUpload::make('file')
                    ->label(fn (?EnglishPaper $record): string => $record === null ? 'الملف' : 'ملف جديد بدل الحالي (اختياري)')
                    ->storeFiles(false)
                    ->markAsRequired(fn (?EnglishPaper $record): bool => $record === null)
                    ->helperText(fn (): string => 'PDF أو صورة أو ملف Word أو Excel أو PowerPoint. أقصى حجم: '
                        .FileSize::arabic(UploadLimit::kilobytes(EnglishPaperRequest::MAX_KILOBYTES) * 1024).'.')
                    ->columnSpanFull(),
                Textarea::make('notes')
                    ->label('ملاحظات')
                    ->rows(2)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                TextColumn::make('title')
                    ->label('العنوان')
                    ->description(fn (EnglishPaper $record): ?string => $record->notes)
                    ->searchable()
                    ->weight('medium'),
                TextColumn::make('original_name')
                    ->label('الملف')
                    ->icon(fn (EnglishPaper $record): Heroicon => $record->isImage() ? Heroicon::OutlinedPhoto : Heroicon::OutlinedDocumentText)
                    ->wrap(),
                TextColumn::make('size')
                    ->label('الحجم')
                    ->formatStateUsing(fn (int $state): string => FileSize::arabic($state))
                    ->alignCenter(),
                TextColumn::make('created_at')
                    ->label('أُضيفت')
                    ->date()
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('إضافة ورقة')
                    ->using(fn (array $data, Action $action, SaveEnglishPaper $savePaper): EnglishPaper => Attempt::action(
                        $action,
                        fn (): EnglishPaper => $savePaper->handle($this->course(), $data),
                    )),
            ])
            ->recordActions([
                Action::make('open')
                    ->label('فتح')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->color('gray')
                    ->url(fn (EnglishPaper $record): string => route('filament.english.papers.file', ['paper' => $record]))
                    ->openUrlInNewTab(),
                EditAction::make()
                    ->using(fn (EnglishPaper $record, array $data, Action $action, SaveEnglishPaper $savePaper): EnglishPaper => Attempt::action(
                        $action,
                        fn (): EnglishPaper => $savePaper->handle($this->course(), $data, $record),
                    )),
                DeleteAction::make()
                    ->using(function (EnglishPaper $record, DeleteEnglishPaper $deletePaper): bool {
                        $deletePaper->handle($record);

                        return true;
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('لا توجد أوراق بعد');
    }

    private function course(): EnglishCourse
    {
        /** @var EnglishCourse */
        return $this->getOwnerRecord();
    }
}
