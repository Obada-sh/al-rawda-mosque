{{--
    The files of many students ($files, a StudentFiles), by name, each
    starting on a page of its own: every student, the students of a course
    or of a circle, or those ticked in the list.
--}}
@extends('documents.layout')

@use('App\Support\ArabicNoun')

@section('count', ArabicNoun::student()->count(count($files->files)))

@section('content')
    @if ($files->onlyCourse !== null)
        <p class="advice">في كل ملف: {{ $files->onlyCourse }} وحدها.</p>
    @endif

    @forelse ($files->files as $file)
        @include('documents.partials.student-file', ['file' => $file])
    @empty
        <p class="empty">لا يوجد طلاب هنا.</p>
    @endforelse
@endsection
