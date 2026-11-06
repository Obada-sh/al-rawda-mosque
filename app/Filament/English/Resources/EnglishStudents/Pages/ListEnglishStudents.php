<?php

namespace App\Filament\English\Resources\EnglishStudents\Pages;

use App\Enums\Section;
use App\Filament\English\Resources\EnglishStudents\EnglishStudentResource;
use App\Models\Student;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListEnglishStudents extends ListRecords
{
    protected static string $resource = EnglishStudentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'all' => Tab::make('الطلاب'),
            'incomplete' => Tab::make('ملفات غير مكتملة')
                ->badge(fn (): ?int => Student::query()->inEnglish()->incompleteProfile(Section::English)->count() ?: null)
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->incompleteProfile(Section::English)),
        ];
    }
}
