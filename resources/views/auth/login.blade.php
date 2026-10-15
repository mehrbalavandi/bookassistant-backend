<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    @include('partials.favicon')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ورود به آکادمی</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link href="{{ asset('css/persian-font.css') }}" rel="stylesheet" type="text/css" />
    <style>body { font-family: var(--font-fa); }</style>
</head>
<body class="bg-slate-50 flex items-center justify-center min-h-screen p-4">

    <div class="bg-white p-6 sm:p-8 rounded-2xl shadow-sm border border-slate-100 w-full max-w-md">
        <h2 class="text-2xl font-bold text-center text-slate-900 mb-6">ورود به حساب کاربری</h2>

        @if($errors->any())
            <div class="bg-red-50 text-red-600 p-3 rounded-lg text-sm mb-4">
                {{ $errors->first() }}
            </div>
        @endif

        <form action="{{ route('login') }}" method="POST" class="space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">ایمیل</label>
                <input type="email" name="email" required class="w-full px-4 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-blue-600 focus:outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">رمز عبور</label>
                <input type="password" name="password" required class="w-full px-4 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-blue-600 focus:outline-none">
            </div>
            <button type="submit" class="w-full bg-blue-600 text-white font-bold py-3 rounded-xl hover:bg-blue-700 transition">ورود</button>
        </form>

        <a href="{{ route('login.otp') }}" class="block text-center w-full mt-4 border border-blue-600 text-blue-700 font-bold py-3 rounded-xl hover:bg-blue-50 transition">ورود یا ثبت‌نام با شماره‌ی موبایل</a>

        <p class="text-center text-sm text-slate-500 mt-6">
            حساب کاربری ندارید؟ <a href="{{ route('login.otp') }}" class="text-blue-600 font-bold">با موبایل ثبت‌نام کنید</a>
        </p>
    </div>

</body>
</html>