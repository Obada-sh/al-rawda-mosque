{{--
    The students of the mosque on one sheet ($sheet, a MosqueSheet): every
    student in the system with their records in every course, or the
    students of one course with what they did in it; with the columns the
    admin chose. In one table by name, each student with their circle; or
    circle by circle, each circle under its name with its team (and, with
    every course, its course), the students in no circle last.
--}}
@extends('documents.layout')

@use('App\Filament\Support\Presenters\CirclePresenter')
@use('App\Support\ArabicNoun')
@use('App\Support\Brand')

@section('count', ArabicNoun::student()->count(count($sheet->lines)))

@section('content')
    <article class="document">
        @include('documents.partials.letterhead', [
            'title' => 'طلاب '.app(Brand::class)->name(),
            'lines' => $sheet->course === null
                ? [
                    $sheet->withArchived ? 'كل الطلاب المسجّلين في النظام، والمؤرشفون معهم' : 'كل الطلاب المسجّلين في النظام',
                    ['سجلّ كل طالب في كل الدورات منذ دخوله النظام'],
                    [$sheet->byCircle ? 'حسب حلقاتهم الآن' : ''],
                ]
                : [
                    $sheet->course->name,
                    ['طلاب الدورة من كل حلقاتها، وما فعلوه فيها'],
                    [$sheet->byCircle ? 'حلقةً بعد حلقة' : ''],
                ],
        ])

        @if ($sheet->isEmpty())
            <p class="empty">{{ $sheet->course === null ? 'لا يوجد طلاب في النظام بعد.' : 'لا يوجد طلاب في هذه الدورة الآن.' }}</p>
        @elseif ($sheet->byCircle)
            @foreach ($sheet->groups as $group)
                @php
                    $about = $group->circle === null
                        ? ['طلاب ليسوا في حلقة في دورة جارية الآن']
                        : [$sheet->course === null ? $group->circle->course->name : null, CirclePresenter::team($group->circle)];
                    $about = implode(' · ', array_filter($about));
                @endphp
                <section class="block sheet-group">
                    <h2 class="block__title">{{ $group->circle?->name ?? 'بلا حلقة الآن' }}</h2>
                    @if ($about !== '')
                        <p class="block__note">{{ $about }}</p>
                    @endif
                    @include('documents.partials.students-table', [
                        'lines' => $group->lines,
                        'options' => $sheet->options,
                        'newPages' => $group->newPages(),
                        'examsPassed' => $group->examsPassed(),
                        'examsNote' => false,
                    ])
                </section>
            @endforeach
            <p class="sheet-total">عدد الطلاب في الكشف: {{ count($sheet->lines) }}</p>
            @if ($sheet->options->exams)
                <p class="sheet-note">السبر: رقم الجزء وتقدير آخر سبر فيه.</p>
            @endif
        @else
            @include('documents.partials.students-table', [
                'lines' => $sheet->lines,
                'options' => $sheet->options,
                'newPages' => $sheet->newPages(),
                'examsPassed' => $sheet->examsPassed(),
                'circleHeading' => $sheet->course === null ? 'الحلقة الآن' : 'الحلقة',
            ])
        @endif
    </article>
@endsection
