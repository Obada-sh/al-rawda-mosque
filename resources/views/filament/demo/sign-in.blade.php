{{-- Above the sign-in form of the demo: what it is, and how to get in. --}}
<div class="rawda-demo-sign-in" role="note">
    <p class="rawda-demo-sign-in__title">نسخة تجريبية</p>

    @if ($account)
        <p>كل ما فيها بيانات وهمية للتجربة والتدريب، ولا علاقة لها ببيانات الجامع. ادخل بهذا الحساب:</p>
        <dl>
            <dt>اسم المستخدم</dt>
            <dd><bdi dir="ltr">{{ $account['username'] }}</bdi></dd>
            <dt>كلمة المرور</dt>
            <dd><bdi dir="ltr">{{ $account['password'] }}</bdi></dd>
        </dl>
    @else
        <p>كل ما فيها بيانات وهمية للتجربة والتدريب، ولا علاقة لها ببيانات الجامع.</p>
        <p>حساب الدخول التجريبي غُيّر من صفحة «حسابي». ادخل بالحساب الجديد، أو احذف قاعدة البيانات التجريبية ثم شغّل <bdi dir="ltr">demo.bat</bdi> ليُبنى كل شيء من جديد.</p>
    @endif
</div>
