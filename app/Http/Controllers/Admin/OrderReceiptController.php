<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/** 🌟 PAY-3: تصویرِ رسیدِ کارت‌به‌کارت فقط برای ادمین (فایل در دیسکِ خصوصی است). */
class OrderReceiptController extends Controller
{
    public function show(Order $order)
    {
        abort_unless((bool) (Auth::user()?->is_admin ?? false), 403);
        abort_unless($order->receipt_path && Storage::disk('local')->exists($order->receipt_path), 404);

        return Storage::disk('local')->response($order->receipt_path);
    }
}
