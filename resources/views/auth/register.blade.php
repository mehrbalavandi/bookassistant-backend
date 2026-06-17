<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ثبت نام در آکادمی</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet" type="text/css" />
    <style>body { font-family: Vazirmatn, sans-serif; }</style>
</head>
<body class="bg-slate-50 flex items-center justify-center min-h-screen">

    <div class="bg-white p-8 rounded-2xl shadow-sm border border-slate-100 w-full max-w-md">
        <h2 class="text-2xl font-bold text-center text-slate-900 mb-6">ساخت حساب کاربری</h2>

        <form action="{{ route('register') }}" method="POST" class="space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">نام و نام خانوادگی</label>
                <input type="text" name="name" required class="w-full px-4 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-blue-600 focus:outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">ایمیل</label>
                <input type="email" name="email" required class="w-full px-4 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-blue-600 focus:outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">رمز عبور</label>
                <input type="password" name="password" required class="w-full px-4 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-blue-600 focus:outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">تکرار رمز عبور</label>
                <input type="password" name="password_confirmation" required class="w-full px-4 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-blue-600 focus:outline-none">
            </div>
            <button type="submit" class="w-full bg-blue-600 text-white font-bold py-3 rounded-xl hover:bg-blue-700 transition">ثبت نام</button>
        </form>

        <p class="text-center text-sm text-slate-500 mt-6">
            حساب کاربری دارید؟ <a href="{{ route('login') }}" class="text-blue-600 font-bold">وارد شوید</a>
        </p>
    </div>

</body>
</html>