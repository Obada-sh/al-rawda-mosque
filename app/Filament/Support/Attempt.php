<?php

namespace App\Filament\Support;

use App\Exceptions\BusinessRuleViolation;
use Closure;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Cancel;
use Filament\Support\Exceptions\Halt;
use Illuminate\Validation\ValidationException;

/**
 * The single doorway from the admin panel into the application layer.
 *
 * Screens never validate input and never apply business rules: they hand
 * the form data to an Action. This class turns what an Action can throw
 * into what the screen should show:
 *
 *  - ValidationException (raised by a Request class): the messages are
 *    placed under the fields of the form, which stays open;
 *  - BusinessRuleViolation: its message is shown as a red notification and
 *    nothing is saved.
 */
final class Attempt
{
    /**
     * For the form of a page. A broken business rule leaves the form open
     * so the admin can correct the input.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @param  Schema|null  $form  the form whose fields receive the validation messages
     * @param  (Closure(string): string)|null  $fieldOf  maps a Request field name to the form field name, when they differ
     * @return T
     *
     * @throws ValidationException
     * @throws Halt
     */
    public static function form(Closure $callback, ?Schema $form = null, ?Closure $fieldOf = null): mixed
    {
        try {
            return $callback();
        } catch (ValidationException $exception) {
            throw self::underFields($exception, $form, $fieldOf);
        } catch (BusinessRuleViolation $violation) {
            self::notify($violation);

            throw new Halt;
        }
    }

    /**
     * For a button that opens a form in a window (create, edit, transfer...).
     * The messages go under the fields of that window, which stays open.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @param  (Closure(string): string)|null  $fieldOf
     * @return T
     *
     * @throws ValidationException
     * @throws Halt
     */
    public static function action(Action $action, Closure $callback, ?Closure $fieldOf = null): mixed
    {
        $livewire = $action->getLivewire();
        $formName = $livewire?->getMountedActionSchemaName();

        return self::form($callback, filled($formName) ? $livewire->getSchema($formName) : null, $fieldOf);
    }

    /**
     * For a button that only asks "are you sure?". A broken business rule
     * closes the confirmation and explains why nothing happened.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     *
     * @throws Cancel
     */
    public static function confirmed(Closure $callback): mixed
    {
        try {
            return $callback();
        } catch (BusinessRuleViolation $violation) {
            self::notify($violation);

            throw new Cancel;
        }
    }

    /**
     * For a button of a page that has neither a form nor a confirmation.
     * A broken business rule is explained in a red notification, and the
     * page carries on.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T|null null when a business rule stopped the callback
     */
    public static function button(Closure $callback): mixed
    {
        try {
            return $callback();
        } catch (BusinessRuleViolation $violation) {
            self::notify($violation);

            return null;
        }
    }

    /**
     * Re-keys the messages so each one appears under its form field
     * ("full_name" becomes "data.full_name"), and asks the page to scroll
     * to the first of them.
     */
    private static function underFields(ValidationException $exception, ?Schema $form, ?Closure $fieldOf): ValidationException
    {
        $statePath = $form?->getStatePath();
        $messages = [];

        foreach ($exception->errors() as $field => $fieldMessages) {
            $field = $fieldOf === null ? $field : $fieldOf($field);
            $key = filled($statePath) ? "{$statePath}.{$field}" : $field;

            $messages[$key] = array_merge($messages[$key] ?? [], $fieldMessages);
        }

        if ($form !== null) {
            $livewire = $form->getLivewire();
            $livewire->dispatch('form-validation-error', livewireId: $livewire->getId());
        }

        return ValidationException::withMessages($messages);
    }

    private static function notify(BusinessRuleViolation $violation): void
    {
        Notification::make()
            ->title($violation->getMessage())
            ->danger()
            ->send();
    }
}
