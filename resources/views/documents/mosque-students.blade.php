{{--
    The students of the mosque on one sheet, by name ($sheet, a
    MosqueSheet): every student in the system with their records in every
    course, or the students of one course with what they did in it; each
    with their circle, and the columns the admin chose.
--}}
@extends('documents.layout')

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
                ]
                : [
                    $sheet->course->name,
                    ['طلاب الدورة من كل حلقاتها، وما فعلوه فيها'],
                ],
        ])

        @if ($sheet->isEmpty())
            <p class="empty">{{ $sheet->course === null ? 'لا يوجد طلاب في النظام بعد.' : 'لا يوجد طلاب في هذه الدورة الآن.' }}</p>
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
