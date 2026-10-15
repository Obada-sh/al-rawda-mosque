@extends('layouts.standalone')

@section('title', 'إعداد النظام')

@section('content')
    <h1>إعداد النظام لأول مرة</h1>
    <p class="lead">أنشئ حساب المدير الذي ستدخل به إلى النظام. احفظ كلمة المرور في مكان آمن.</p>

    <form method="POST" action="{{ route('setup.store') }}" novalidate>
        @csrf

        <div class="field">
            <label for="name">الاسم</label>
            <input id="name" name="name" type="text" value="{{ old('name', 'المدير') }}" autocomplete="name" autofocus>
            @error('name') <p class="error">{{ $message }}</p> @enderror
        </div>

        <div class="field">
            <label for="username">اسم المستخدم</label>
            <input id="username" name="username" type="text" value="{{ old('username', 'admin') }}" dir="ltr" autocomplete="username">
            <p class="hint">أحرف لاتينية وأرقام فقط، مثل admin.</p>
            @error('username') <p class="error">{{ $message }}</p> @enderror
        </div>

        <div class="field">
            <label for="password">كلمة المرور</label>
            <input id="password" name="password" type="password" dir="ltr" autocomplete="new-password">
            <p class="hint">ثمانية أحرف على الأقل.</p>
            @error('password') <p class="error">{{ $message }}</p> @enderror
        </div>

        <div class="field">
            <label for="password_confirmation">تأكيد كلمة المرور</label>
            <input id="password_confirmation" name="password_confirmation" type="password" dir="ltr" autocomplete="new-password">
        </div>

        <button type="submit">إنشاء الحساب والدخول</button>
    </form>
@endsection
