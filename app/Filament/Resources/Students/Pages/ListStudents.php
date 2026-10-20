<?php

namespace App\Filament\Resources\Students\Pages;

use App\Filament\Resources\Students\StudentResource;
use App\Filament\Support\Cards\CardPrintActions;
use App\Filament\Support\Documents\DocumentActions;
use App\Models\Student;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListStudents extends ListRecords
{
    protected static string $resource = StudentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DocumentActions::studentFiles(),
            CardPrintActions::choose(),
            CreateAction::make(),
        ];
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'active' => Tab::make('الطلاب')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->active()),
            'incomplete' => Tab::make('ملفات غير مكتملة')
                ->badge(fn (): ?int => Student::query()->active()->incompleteProfile()->count() ?: null)
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->active()->incompleteProfile()),
            'archived' => Tab::make('المؤرشفون')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->archived()),
        ];
    }
}
