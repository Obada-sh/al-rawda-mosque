{{--
    The table of a sheet of students: their names, and the columns the
    admin chose ($options, a SheetOptions): phones, the pages memorized
    (how many and which), the juz exams and the attendance; in one course
    or in every course of each student.

    $lines (list of SheetLine), $options, $newPages and $examsPassed (the
    totals of the sheet), and optionally $circleHeading: the heading of a
    column with each student's circle (the mosque's sheet), none when null.
--}}
@use('App\Actions\Documents\SheetExam')
@use('App\Filament\Support\Presenters\ReportPresenter')
@use('App\Services\Statistics\Rates')
@use('App\Support\ArabicNoun')

@php
    $where = $options->allCourses ? 'في كل الدورات' : 'في الدورة';
    $circleHeading ??= null;
@endphp

<table class="table sheet-table">
    <thead>
        <tr>
            <th class="is-number">#</th>
            <th>اسم الطالب</th>
            @if ($circleHeading !== null)
                <th>{{ $circleHeading }}</th>
            @endif
            @if ($options->phones)
                <th>رقم الطالب</th>
                <th>رقم ولي الأمر</th>
            @endif
            @if ($options->pages)
                <th>الصفحات المحفوظة {{ $where }}</th>
            @endif
            @if ($options->exams)
                <th>السبر {{ $where }}</th>
            @endif
            @if ($options->attendance)
                <th class="is-center">الحضور {{ $where }}</th>
            @endif
        </tr>
    </thead>
    <tbody>
        @foreach ($lines as $line)
            <tr>
                <td class="is-number">{{ $loop->iteration }}</td>
                <td class="sheet-table__name">
                    {{ $line->name }}
                    @if ($line->archived)
                        <span class="sheet-table__detail">ملفه مؤرشف</span>
                    @endif
                </td>
                @if ($circleHeading !== null)
                    <td class="sheet-table__circle">@if (filled($line->circle)){{ $line->circle }}@else<span class="muted">—</span>@endif</td>
                @endif
                @if ($options->phones)
                    <td class="sheet-table__phone">@if (filled($line->phone))<span class="ltr">{{ $line->phone }}</span>@else<span class="muted">—</span>@endif</td>
                    <td class="sheet-table__phone">@if (filled($line->guardianPhone))<span class="ltr">{{ $line->guardianPhone }}</span>@else<span class="muted">—</span>@endif</td>
                @endif
                @if ($options->pages)
                    <td class="sheet-table__pages">
                        <strong>{{ ArabicNoun::page()->count($line->newPages) }}</strong>
                        @if ($line->pageRanges !== '')
                            <span class="sheet-table__detail">{{ $line->pageRanges }}</span>
                        @endif
                        @if ($line->priorPages)
                            <span class="sheet-table__detail">وقبل دخول النظام: {{ ArabicNoun::page()->count($line->priorPages) }}</span>
                        @endif
                    </td>
                @endif
                @if ($options->exams)
                    <td class="sheet-table__exams">
                        @if ($line->exams === [])
                            <span class="muted">—</span>
                        @else
                            {{ implode('، ', array_map(fn (SheetExam $exam): string => $exam->label(), $line->exams)) }}
                        @endif
                    </td>
                @endif
                @if ($options->attendance)
                    <td class="is-center">
                        @if ($line->sessions === 0)
                            <span class="muted">لا جلسات</span>
                        @else
                            {{ $line->attended }} من {{ $line->sessions }}
                            <span class="sheet-table__detail">{{ ReportPresenter::percent(Rates::attendance($line->attended, $line->sessions)) }}</span>
                        @endif
                    </td>
                @endif
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="{{ 2 + ($circleHeading !== null ? 1 : 0) + ($options->phones ? 2 : 0) }}">عدد الطلاب: {{ count($lines) }}</td>
            @if ($options->pages)
                <td>{{ ArabicNoun::page()->count($newPages) }}</td>
            @endif
            @if ($options->exams)
                <td>أجزاء نجحوا فيها: {{ $examsPassed }}</td>
            @endif
            @if ($options->attendance)
                <td></td>
            @endif
        </tr>
    </tfoot>
</table>
@if ($options->exams)
    <p class="sheet-note">السبر: رقم الجزء وتقدير آخر سبر فيه.</p>
@endif
