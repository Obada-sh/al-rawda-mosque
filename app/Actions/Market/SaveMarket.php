<?php

namespace App\Actions\Market;

use App\Http\Requests\Market\MarketRequest;
use App\Models\Market;
use Illuminate\Validation\ValidationException;

/**
 * Opens a points market for a course, or renames, opens or closes one.
 * A closed market keeps its items and its sales; it only stops selling.
 */
final class SaveMarket
{
    /**
     * @param  array<string, mixed>  $input  course_id (when opening), name, is_open
     *
     * @throws ValidationException
     */
    public function handle(array $input, ?Market $market = null): Market
    {
        $chosenCourse = $input['course_id'] ?? null;

        $data = MarketRequest::validateData($input, [
            'opening' => $market === null,
            'course_id' => $market?->course_id ?? (is_numeric($chosenCourse) ? (int) $chosenCourse : 0),
            'ignore_id' => $market?->getKey(),
        ]);

        $market ??= new Market(['course_id' => $data['course_id']]);

        $market->fill([
            'name' => $data['name'],
            'is_open' => (bool) ($data['is_open'] ?? $market->is_open),
        ])->save();

        return $market;
    }
}
