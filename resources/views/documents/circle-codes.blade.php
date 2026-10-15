{{--
    The attendance codes of a circle's students: each one's name above the
    QR of their card of the course (and its number, to type it in when a
    code does not scan). Fifteen to a page, to cut along the dashed lines.
--}}
@extends('documents.layout')

@use('App\Filament\Support\Presenters\CardPresenter')
@use('App\Support\ArabicNoun')

@php
    $perPage = 15;
@endphp

@section('count', ArabicNoun::circle()->count($sheets->count()))

@section('content')
    @forelse ($sheets as $sheet)
        @foreach ($sheet->isEmpty() ? [[]] : array_chunk($sheet->lines, $perPage) as $page)
            <article class="document codes-page">
                @include('documents.partials.letterhead', [
                    'title' => $sheet->circle->name,
                    'lines' => [$sheet->circle->course->name, ['رموز الحضور']],
                ])

                @if ($page === [])
                    <p class="empty">لا يوجد طلاب في هذه الحلقة الآن.</p>
                @else
                    <div class="codes">
                        @foreach ($page as $line)
                            <div class="code">
                                <p class="code__name">{{ $line->name }}</p>
                                <img class="code__qr" src="{{ CardPresenter::svgDataUri($line->qrSvg) }}" alt="رمز حضور {{ $line->name }}" data-code="{{ $line->cardCode }}">
                                <p class="code__number ltr">{{ $line->cardCode }}</p>
                            </div>
                        @endforeach
                    </div>
                @endif
            </article>
        @endforeach
    @empty
        <p class="empty">لا يوجد طلاب في حلقات هذه الدورة.</p>
    @endforelse
@endsection
