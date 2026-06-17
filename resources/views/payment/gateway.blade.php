<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>درگاه پرداخت آزمایشی</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet" />
    <style>body { font-family: Vazirmatn, sans-serif; }</style>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">

    <div class="bg-white p-8 rounded-xl shadow-md w-full max-w-md text-center">
        <div class="mb-6">
            <h2 class="text-2xl font-bold text-blue-600 mb-2">درگاه شبیه‌ساز بانک</h2>
            <p class="text-sm text-gray-500">محیط تست محلی (Localhost)</p>
        </div>

        <div class="bg-gray-50 p-4 rounded-lg mb-8 text-right space-y-2 text-sm border border-gray-200">
            <div class="flex justify-between"><span>پرداخت‌کننده:</span> <span class="font-bold">{{ $user->name }}</span></div>
            <div class="flex justify-between"><span>مبلغ قابل پرداخت:</span> <span class="font-bold text-green-600">{{ $amount }} تومان</span></div>
            <div class="flex justify-between"><span>شماره پیگیری:</span> <span class="font-bold">{{ $transactionId }}</span></div>
        </div>

        <div class="flex flex-col gap-3">
            <a href="{{ route('payment.verify', ['status' => 'success']) }}" class="bg-green-500 text-white font-bold py-3 rounded-lg hover:bg-green-600 transition">
                شبیه‌سازی: پرداخت موفق
            </a>
            
            <a href="{{ route('payment.verify', ['status' => 'failed']) }}" class="bg-red-500 text-white font-bold py-3 rounded-lg hover:bg-red-600 transition">
                شبیه‌سازی: انصراف از پرداخت
            </a>
        </div>
    </div>

</body>
</html>