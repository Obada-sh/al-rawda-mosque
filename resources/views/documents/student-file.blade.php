{{--
    A student's file: their details, then for each of their courses (the
    newest first) the figures, every recitation and every session.
--}}
@extends('documents.layout')

@use('App\Enums\Weekday')
@use('App\Filament\Support\Presenters\ReportPresenter')
@use('App\Filament\Support\Presenters\StudentPresenter')
@use('App\Support\ArabicNoun')
@use('App\Support\Brand')
@use('App\Support\Dates')

@php
    $student = $file->student;
    $photo = StudentPresenter::photoUrl($student);
    $blank = '—';
@endphp

@section('count', ArabicNoun::course()->count(count($file->courses)))

@section('content')
    <article class="document">
        @include('documents.partials.letterhead', [
            'kicker' => app(Brand::class)->name(),
            'title' => $student->full_name,
            'lines' => [$student->isArchived() ? 'ملف الطالب (مؤرشف)' : 'ملف الطالب'],
        ])

        <section class="block">
            <h2 class="block__title">البيانات الأساسية</h2>
            <div class="profile">
                @if ($photo !== null)
                    <img src="{{ $photo }}" alt="صورة {{ $student->full_name }}" class="profile__photo">
                @endif
                <dl class="facts">
                    <div><dt>الاسم</dt><dd>{{ $student->full_name }}</dd></div>
                    <div><dt>تاريخ الميلاد</dt><dd>{{ $student->birth_date === null ? $blank : Dates::show($student->birth_date) }}</dd></div>
                    <div><dt>العمر</dt><dd>{{ $student->age === null ? $blank : ArabicNoun::year()->count($student->age) }}</dd></div>
                    <div><dt>رقم الطالب</dt><dd>@if (filled($student->phone))<span class="ltr">{{ $student->phone }}</span>@else{{ $blank }}@endif</dd></div>
                    <div><dt>رقم ولي الأمر</dt><dd>@if (filled($student->guardian_phone))<span class="ltr">{{ $student->guardian_phone }}</span>@else{{ $blank }}@endif</dd></div>
                    <div><dt>مكان السكن</dt><dd>{{ $student->address ?: $blank }}</dd></div>
                    <div><dt>مستوى التجويد</dt><dd>{{ $student->tajweedLevel?->name ?? $blank }}</dd></div>
                    <div><dt>المحفوظ السابق</dt><dd>{{ $student->prior_memorized_pages === null ? 'غير محدد' : ArabicNoun::page()->count($student->prior_memorized_pages) }}</dd></div>
                    <div><dt>سمّع في النظام</dt><dd>{{ ArabicNoun::page()->count($student->recited_pages_count) }}</dd></div>
                    <div><dt>مجموع المحفوظ</dt><dd>{{ ArabicNoun::page()->count($student->memorized_pages_total) }}</dd></div>
                    @if (filled($student->notes))
                        <div class="is-wide"><dt>ملاحظات</dt><dd>{{ $student->notes }}</dd></div>
                    @endif
                </dl>
            </div>
        </section>

        @forelse ($file->courses as $course)
            @php
                $enrollment = $course->enrollment;
                $standing = $course->standing;
                $until = $enrollment->left_on !== null
                    ? Dates::show($enrollment->left_on).' (غادر الدورة)'
                    : ($enrollment->course->end_date !== null ? Dates::show($enrollment->course->end_date) : 'اليوم');
            @endphp

            <section class="block">
                <h2 class="block__title">{{ $enrollment->course->name }} — {{ $enrollment->circle->name }}</h2>
                <p class="block__note">من {{ Dates::show($enrollment->enrolled_on) }} إلى {{ $until }}</p>

                <div class="figures">
                    <div class="figure"><span class="figure__value">{{ $standing->attended }} / {{ $standing->sessions }}</span><span class="figure__label">الحضور</span></div>
                    <div class="figure"><span class="figure__value">{{ ReportPresenter::percent($standing->attendanceRate()) ?? $blank }}</span><span class="figure__label">نسبة الحضور</span></div>
                    <div class="figure"><span class="figure__value">{{ $standing->newPages }}</span><span class="figure__label">صفحات محفوظة</span></div>
                    <div class="figure"><span class="figure__value">{{ $standing->recitedPages }}</span><span class="figure__label">صفحات مسمَّعة</span></div>
                    <div class="figure"><span class="figure__value">{{ $standing->points }}</span><span class="figure__label">النقاط</span></div>
                </div>

                <h3 class="block__subtitle">الحفظ والتسميع</h3>
                @if ($course->recitations === [])
                    <p class="block__note">لم يسمّع الطالب شيئاً في هذه الدورة.</p>
                @else
                    <table class="table table--compact">
                        <thead>
                            <tr>
                                <th class="is-number">#</th>
                                <th>التاريخ</th>
                                <th class="is-center">الصفحات</th>
                                <th class="is-center">العدد</th>
                                <th class="is-center">جديدة</th>
                                <th>الأستاذ</th>
                                <th>ملاحظات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($course->recitations as $recitation)
                                <tr>
                                    <td class="is-number">{{ $loop->iteration }}</td>
                                    <td>{{ Dates::show($recitation->recited_on) }}</td>
                                    <td class="is-center">{{ $recitation->from_page === $recitation->to_page ? $recitation->from_page : $recitation->from_page.' – '.$recitation->to_page }}</td>
                                    <td class="is-center">{{ $recitation->pages_count }}</td>
                                    <td class="is-center">{{ $recitation->new_pages_count }}</td>
                                    <td>{{ $recitation->teacher?->full_name ?? $blank }}</td>
                                    <td>{{ $recitation->notes }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3">المجموع</td>
                                <td class="is-center">{{ array_sum(array_map(fn ($recitation) => $recitation->pages_count, $course->recitations)) }}</td>
                                <td class="is-center">{{ array_sum(array_map(fn ($recitation) => $recitation->new_pages_count, $course->recitations)) }}</td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    </table>
                @endif

                <h3 class="block__subtitle">الحضور</h3>
                @if ($course->days === [])
                    <p class="block__note">لم تُعقد جلسات في هذه الدورة بعد.</p>
                @else
                    <table class="table table--compact">
                        <thead>
                            <tr>
                                <th class="is-number">#</th>
                                <th>التاريخ</th>
                                <th>اليوم</th>
                                <th class="is-center">الحالة</th>
                                <th>طريقة التسجيل</th>
                                <th class="is-center">الوقت</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($course->days as $day)
                                <tr>
                                    <td class="is-number">{{ $loop->iteration }}</td>
                                    <td>{{ Dates::show($day->session->session_date) }}</td>
                                    <td>{{ Weekday::fromDate($day->session->session_date)->label() }}</td>
                                    <td class="is-center">
                                        @if ($day->wasPresent())
                                            <span class="status status--present">حاضر</span>
                                        @else
                                            <span class="status status--absent">غائب</span>
                                        @endif
                                    </td>
                                    <td>{{ $day->attendance?->method->label() ?? '' }}</td>
                                    <td class="is-center">{{ $day->attendance?->scanned_at?->format('H:i') ?? '' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="6">الجلسات: {{ $standing->sessions }} · حضر: {{ $standing->attended }} · غاب: {{ max(0, $standing->sessions - $standing->attended) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                @endif
            </section>
        @empty
            <p class="block__note">الطالب غير مسجَّل في أي دورة بعد.</p>
        @endforelse
    </article>
@endsection
