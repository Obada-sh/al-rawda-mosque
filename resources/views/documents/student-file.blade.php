{{--
    A student's file: their details, then for each of their courses (the
    newest first) the figures, every recitation and every session.
--}}
@extends('documents.layout')

@use('App\Support\ArabicNoun')

@section('count', ArabicNoun::course()->count(count($file->courses)))

@section('content')
    @include('documents.partials.student-file', ['file' => $file])
@endsection
