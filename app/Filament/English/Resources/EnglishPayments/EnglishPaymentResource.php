<?php

namespace App\Filament\English\Resources\EnglishPayments;

use App\Actions\English\Payments\DeleteEnglishPayment;
use App\Actions\English\Payments\RecordEnglishPayment;
use App\Filament\English\EnglishPanelGroup;
use App\Filament\English\Resources\EnglishPayments\Pages\ManageEnglishPayments;
use App\Filament\English\Support\EnglishEnrollmentActions;
use App\Filament\English\Support\EnglishOptions;
use App\Filament\Support\Attempt;
use App\Filament\Support\Filters\DateRangeFilter;
use App\Models\English\EnglishPayment;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Every payment of the students, the latest first: who paid how much, on
 * which day, for which course. The total of what the list shows is at its
 * foot.
 */
class EnglishPaymentResource extends Resource
{
    protected static ?string $model = EnglishPayment::class;

    protected static ?string $slug = 'payments';

    protected static string|UnitEnum|null $navigationGroup = EnglishPanelGroup::Finance;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'دفعة';

    protected static ?string $pluralModelLabel = 'دفعات الطلاب';

    public static function form(Schema $schema): Schema
    {
        return $schema->components(EnglishEnrollmentActions::paymentFields());
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['enrollment.student', 'enrollment.course', 'enrollment.hall']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('paid_on')
                    ->label('التاريخ')
                    ->date()
                    ->sortable(),
                TextColumn::make('enrollment.student.full_name')
                    ->label('الطالب')
                    ->searchable()
                    ->weight('medium'),
                TextColumn::make('enrollment.course.name')
                    ->label('الدورة'),
                TextColumn::make('enrollment.hall.name')
                    ->label('القاعة')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('amount')
                    ->label('المبلغ')
                    ->formatStateUsing(fn (int $state): string => Money::pounds($state))
                    ->alignEnd()
                    ->sortable()
                    ->weight('bold')
                    ->summarize(Sum::make()
                        ->label('المجموع')
                        ->formatStateUsing(fn (mixed $state): string => Money::pounds(is_numeric($state) ? (int) $state : 0))),
                TextColumn::make('notes')
                    ->label('ملاحظات')
                    ->placeholder('—')
                    ->wrap()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('course')
                    ->label('الدورة')
                    ->options(fn (): array => EnglishOptions::courses())
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, mixed $courseId) => $query->whereHas('enrollment', fn (Builder $query) => $query->where('english_course_id', $courseId)),
                    )),
                DateRangeFilter::make('paid_on', 'التاريخ'),
            ])
            ->recordActions([
                EditAction::make()
                    ->modalDescription(fn (EnglishPayment $record): string => "{$record->enrollment->student->full_name} — {$record->enrollment->course->name}")
                    ->using(fn (EnglishPayment $record, array $data, Action $action, RecordEnglishPayment $recordPayment): EnglishPayment => Attempt::action(
                        $action,
                        fn (): EnglishPayment => $recordPayment->handle([...$data, 'english_enrollment_id' => $record->english_enrollment_id], $record),
                    )),
                DeleteAction::make()
                    ->modalHeading(fn (EnglishPayment $record): string => 'حذف دفعة '.Money::pounds($record->amount)." من {$record->enrollment->student->full_name}")
                    ->modalDescription('يعود المبلغ ديناً على الطالب.')
                    ->using(function (EnglishPayment $record, DeleteEnglishPayment $deletePayment): bool {
                        $deletePayment->handle($record);

                        return true;
                    }),
            ])
            ->defaultSort('paid_on', 'desc')
            ->emptyStateHeading('لا توجد دفعات بعد');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageEnglishPayments::route('/'),
        ];
    }
}
