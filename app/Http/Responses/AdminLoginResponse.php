<?php

namespace App\Http\Responses;

use App\Support\LoginRedirect;
use Filament\Facades\Filament;
use Filament\Http\Responses\Auth\Contracts\LoginResponse as Responsable;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

/**
 * 🌟 SITE-10: ورود از صفحه‌ی ورودِ خودِ پنل (‎/admin/login‎) هم همان قاعده‌ی سایت را دارد:
 * همان کاربر ← همان صفحه، کاربرِ دیگر ← داشبورد (LoginRedirect).
 */
class AdminLoginResponse implements Responsable
{
    public function toResponse($request): RedirectResponse|Redirector
    {
        $user = $request->user();
        if ($user instanceof \App\Models\User) {
            LoginRedirect::prepare($request, $user);
        }

        return redirect()->intended(Filament::getUrl());
    }
}
