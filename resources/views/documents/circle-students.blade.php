{{--
    The list of a circle's students: name, phone, guardian's phone and the
    pages memorized in the course. One document per circle.
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
                'lines' => [$sheet->circle->course->name, [CirclePresenter::team($sheet->circle) ?? '']],
            ])

            @if ($sheet->isEmpty())
                <p class="empty">لا يوجد طلاب في هذه الحلقة الآن.</p>
            @else
                <table class="table">
                    <thead>
                        <tr>
                            <th class="is-number">#</th>
                            <th>اسم الطالب</th>
                            <th>رقم الطالب</th>
                            <th>رقم ولي الأمر</th>
                            <th class="is-center">الصفحات المحفوظة في الدورة</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($sheet->lines as $line)
                            <tr>
                                <td class="is-number">{{ $loop->iteration }}</td>
                                <td>{{ $line->name }}</td>
                                <td>@if (filled($line->phone))<span class="ltr">{{ $line->phone }}</span>@else<span class="muted">—</span>@endif</td>
                                <td>@if (filled($line->guardianPhone))<span class="ltr">{{ $line->guardianPhone }}</span>@else<span class="muted">—</span>@endif</td>
                                <td class="is-center">{{ $line->newPages }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="4">عدد الطلاب: {{ count($sheet->lines) }}</td>
                            <td class="is-center">{{ $sheet->newPages() }}</td>
                        </tr>
                    </tfoot>
                </table>
            @endif
        </article>
    @empty
        <p class="empty">لا يوجد طلاب في حلقات هذه الدورة.</p>
    @endforelse
@endsection
