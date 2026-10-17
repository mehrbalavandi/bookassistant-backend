<?php

namespace App\Http\Controllers;

/**
 * 🌟 SITE-5: صفحه‌های ثابتِ سایت. متنِ «درباره‌ی ما»، «قوانین» و «حریمِ خصوصی» در پنل
 * («تنظیماتِ سایت» ← «صفحه‌ها») نوشته می‌شود؛ «تماس با ما» از اطلاعاتِ پشتیبانی ساخته می‌شود.
 */
class PageController extends Controller
{
    public const PAGES = [
        'about' => 'درباره‌ی ما',
        'terms' => 'قوانین و مقررات',
        'privacy' => 'حریمِ خصوصی',
    ];

    public function show(string $slug)
    {
        abort_unless(isset(self::PAGES[$slug]), 404);

        return view('pages.show', [
            'title' => self::PAGES[$slug],
            'html' => (string) config('site.pages.'.$slug, ''),
        ]);
    }

    public function contact()
    {
        return view('pages.contact', ['support' => (array) config('site.support', [])]);
    }
}
