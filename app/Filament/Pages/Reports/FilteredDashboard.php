<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Support\Attempt;
use App\Filament\Widgets\Reports\Letterhead;
use Filament\Actions\Action;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;

/**
 * Base of the report pages: the letterhead (the mosque's logo and name,
 * the report and what it covers), a filter, and under it the parts of the
 * report, which all read that filter. A report prints as it is shown,
 * without the menu and the filter.
 */
abstract class FilteredDashboard extends BaseDashboard
{
    use HasFiltersForm;

    /**
     * Checks the choices made in the filter (through a Request class).
     *
     * @param  array<string, mixed>  $filters
     *
     * @throws ValidationException
     */
    abstract protected function checkFilters(array $filters): void;

    public function mount(): void
    {
        // Choices that arrive with the address of the page and are not
        // valid (a course that no longer exists...) are dropped.
        try {
            $this->checkFilters($this->filters ?? []);
        } catch (ValidationException) {
            $this->filters = null;
        }
    }

    /**
     * A report starts from its defaults on every visit, so yesterday's
     * period is never shown by surprise.
     */
    public function persistsFiltersInSession(): bool
    {
        return false;
    }

    /**
     * Checks every change of the filter; a choice that is not valid is
     * explained under its field.
     */
    public function updatedFilters(): void
    {
        $this->resetErrorBag();

        Attempt::form(
            fn () => $this->checkFilters($this->filters ?? []),
            $this->getFiltersForm(),
        );
    }

    protected function getHeaderWidgets(): array
    {
        return [
            Letterhead::make(['heading' => (string) $this->getTitle()]),
        ];
    }

    /**
     * Prints the report as it stands on the screen (see the print rules of
     * public/css/panel.css).
     */
    protected function printAction(): Action
    {
        return Action::make('print')
            ->label('طباعة')
            ->icon(Heroicon::OutlinedPrinter)
            ->color('gray')
            ->alpineClickHandler('window.print()');
    }

    /**
     * The parts of a report are stacked, each across the whole page.
     */
    public function getColumns(): int|array
    {
        return 1;
    }
}
