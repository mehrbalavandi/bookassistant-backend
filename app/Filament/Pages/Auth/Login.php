<?php

namespace App\Filament\Pages\Auth;

use App\Support\AuthMethods;
use Filament\Facades\Filament;
use Filament\Pages\Auth\Login as BaseLogin;

/**
 * 🌟 SITE-14: ‎/admin/login‎ دیگر فرمِ خودش را نشان نمی‌دهد و به صفحه‌ی ورودِ سایت می‌رود.
 *
 * فرمِ فیلامنت با Livewire فرستاده می‌شود (بی‌name، بی‌ارسالِ واقعی)، و مدیرِ رمزِ مرورگر (Google
 * Password Manager و مانندش) آن را «ورودِ موفق» تشخیص نمی‌داد: رمزِ تازه ذخیره نمی‌شد و «به‌روزرسانیِ
 * رمز؟» نمی‌آمد. فرمِ سایت یک فرمِ HTMLِ معمولی (username + current-password، POST و redirect) است.
 * ادمین همیشه با ایمیل وارد می‌شود، حتی وقتی ورودِ ایمیلی برای کاربران خاموش است (‎/login?for=admin‎).
 */
class Login extends BaseLogin
{
    public function mount(): void
    {
        if (Filament::auth()->check()) {
            $this->redirect(Filament::getUrl());

            return;
        }

        $this->redirect(AuthMethods::adminLoginUrl());
    }
}
