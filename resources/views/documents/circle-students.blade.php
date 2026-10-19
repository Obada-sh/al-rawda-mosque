{{--
    The list of a circle's students: their names, and the columns the
    admin chose ($options, a SheetOptions): phones, the pages memorized
    (how many and which), the juz exams and the attendance; in the course
    of the circle, or in every course of each student. One document per
    circle.
--}}
@extends('documents.layout')

@use('App\Filament\Support\Presenters\CirclePresenter')
@use('App\Support\ArabicNoun')

@section('count', ArabicNoun::circle()->count($sheets->count()))

@section('content')
    @forelse ($sheets as $sheet)
        <article class="document">
            @include('documents.partials.letterhead', [
                'title' => $sheet->circle->name,
                'lines' => [
                    $sheet->circle->course->name,
                    [CirclePresenter::team($sheet->circle) ?? ''],
                    [$options->allCourses ? 'سجلّ كل طالب في كل الدورات منذ دخوله النظام' : ''],
                ],
            ])

            @if ($sheet->isEmpty())
                <p class="empty">لا يوجد طلاب في هذه الحلقة الآن.</p>
            @else
                @include('documents.partials.students-table', [
                    'lines' => $sheet->lines,
                    'options' => $options,
                    'newPages' => $sheet->newPages(),
                    'examsPassed' => $sheet->examsPassed(),
                ])
            @endif
        </article>
    @empty
        <p class="empty">لا يوجد طلاب في حلقات هذه الدورة.</p>
    @endforelse
@endsection
