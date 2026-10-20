# معماری bookassistant-backend (بک‌اندِ لاراول)

> برای وقتی که مدتی از پروژه دور بوده‌اید. وضعیت مخزن: کامیت `ec6e797`.
> لاراول ۱۲ + Filament (پنلِ ادمین) + Sanctum (توکنِ اپ).
> **راه‌اندازی روی کامپیوترِ تازه، انتقالِ داده و رفعِ خطاهای رایج:** `SETUP-backend.md`.

## این پروژه چه‌کار می‌کند

سه کارِ کاملاً جدا که در یک اپلیکیشن جمع شده‌اند:

1. **انبارِ محتوا** — پوشه‌ی JSONی که استخراج‌کننده‌ی سی‌شارپ می‌سازد را
   نگه می‌دارد و به اپِ فلاتر می‌دهد (فایل‌به‌فایل یا یک ZIPِ قابلِ ادامه).
2. **فروش و مالکیت** — کاربر، خرید، و اینکه چه کسی حق دارد چه کتابی را
   دانلود کند (DRMِ ساده).
3. **پنلِ ادمین** — آپلود/بروزرسانیِ محتوا، مدیریتِ فایل‌های صوتی و تصویری.

جای این پروژه در زنجیره:

```
CustomLayoutGenerator ──► پوشه‌ی JSON ──► [آپلودِ ZIP/RAR در پنلِ ادمین]
                                              │
                                    storage/app/private/books/{folder_name}/
                                              │
                                     API + نسخه‌بندی + مجوزِ دسترسی
                                              ▼
                                      اپِ فلاتر (ielts_assistant)
```

## چیدمانِ فایل‌ها روی دیسک

**نکته‌ی کلیدی:** محتوا در `storage/app/private/` است، نه `public/` — یعنی
هیچ فایلی مستقیماً از طریقِ وب قابلِ دسترسی نیست و **هر دانلود باید از یک
کنترلر رد شود که مالکیت را چک کند**. این خودِ مکانیزمِ DRM است.

```
storage/app/private/books/{folder_name}/
  index.json          فهرستِ صفحات + نسخه‌ها
  pages/              یک JSON به‌ازای هر صفحه
  audio/  images/  audio_scripts/
  archive/            ZIPهای ساخته‌شده برای دانلودِ یکجا
  sample/             همان ساختار، برای نسخه‌ی نمونه‌ی رایگان
    audio/  images/  pages/
```

`sample/` عمداً **داخلِ** پوشه‌ی کتاب است؛ هر جا کدی پوشه را پاک می‌کند باید
حواسش به این باشد (مثلاً `wipeScopeContent`).

**نمونه‌ی همراهِ آرشیوِ اصلی (SMP-1):** اکسترکتور نمونه را هم‌زمان در `sample/`ِ خروجی می‌سازد.
اگر آرشیوِ «محتوای اصلی» پوشه‌ی `sample/` داشته باشد، `App\Support\BookSampleSync` (با مقایسه‌ی
هَشِ `sample/index.json` پیش و پس از استخراج، برای ZIP و RAR یکسان) نمونه را هم ثبت می‌کند:
فایل‌های بی‌ارجاع در index پاک، ستون‌های `sample_*` پر، `sample_version` یک واحد بالا و ZIPِ
نمونه از نو ساخته می‌شود. چنین آپلودی مستقیم آرشیوِ اصلی نمی‌شود (`ensureZip` نمونه را کنار
می‌گذارد). آپلودِ بی‌نمونه به نمونه‌ی موجود دست نمی‌زند.

## نقشه‌ی کد

### مسیرها (`routes/`)

| فایل | محتوا |
|---|---|
| `api.php` | مصرف‌کننده: اپِ فلاتر. عمومی: `/auth/otp/request`، `/auth/otp/verify` (ورود با موبایل، با سقفِ درخواست)، `/register`، `/login` (حساب‌های ایمیلیِ قدیمی)، `/books`، `/books/{book}/cover`، `/books/{book}/download`، `/books/{book}/zip-info`، `/books/{book}/download-zip`، `/app/latest`، `/shop/options`. `/auth/device/replace` (جایگزینیِ دستگاه با بلیت)، `/study/shared` (مرورِ آماده). پشتِ Sanctum: `/logout`، `/me` (GET/PATCH)، `/me/mobile`، `/backup/study/sync`، `/backup/settings` (GET/PUT)، `/devices` (GET/POST)، `/devices/{device}` (DELETE)، `/license`، `/orders` (GET/POST)، `/orders/{order}/receipt`، `/feedback` (GET/POST)، `/feedback/seen`. |
| `web.php` | سایت: صفحه‌ی اصلی، ثبت‌نام، ورود (ایمیل و `/login/otp`)، `checkout/{book}` (سفارش می‌سازد)، `/pay/{order}` (لینکِ امضاشده ← درگاه)، `/payment/callback/{order}` (برگشت از درگاه، بی‌CSRF)، `admin.book-asset` و `admin.order-receipt` (فقط ادمین)، `/feedback` و `feedback.attachment` (FB-1)، و `/app` + `/app/download/{platform}`. |

### API (`app/Http/Controllers/Api/`)

**`BookController.php` (317 خط) — مهم‌ترین فایلِ این پروژه.**

- `index` — فهرستِ کتاب‌ها + نسخه‌ها + وضعیتِ خریده‌شدن.
- `authorizeScope` — **قلبِ DRM.** تصمیم می‌گیرد این کاربر به این scope
  (`main` یا `sample`) دسترسی دارد یا نه. هر مسیرِ دانلودی از این‌جا رد
  می‌شود.
- `download` — سرو کردنِ یک فایلِ تکی (مسیرِ قدیمی، برای سازگاری مانده).
- `ensureZip` (public/static) — **تنها** جایی که آرشیو ساخته می‌شود. اگر
  ZIP وجود نداشته باشد از رویِ محتوایِ دیسک می‌سازدش. صفحه‌ی ویرایشِ فیلامنت
  هم همین را صدا می‌زند، تا دو پیاده‌سازیِ جدا به‌وجود نیاید.
- `zipRelativePath` — نامِ فایلِ آرشیو **شماره‌های نسخه را در خودش دارد**.
  همین یک تصمیم باعث می‌شود آپلودِ جدیدِ ادمین خودبه‌خود آرشیوِ تازه بسازد و
  اپ‌ها بفهمند چیزی عوض شده. `pruneOldZips` قدیمی‌ها را پاک می‌کند.
- `zipInfo` — حجم را می‌دهد (و در همین حین ZIP را می‌سازد اگر نباشد؛ برای
  کتابِ بزرگ این می‌تواند طول بکشد، به همین دلیل اپ در این فاصله اسپینرِ
  نامعین نشان می‌دهد).
- `downloadZip` — استریمِ آرشیو با پشتیبانی از هدرِ `Range`، یعنی
  pause/resume در اپ ممکن است.

**`AuthController.php`** — ثبت‌نام/ورود/خروجِ اپ با ایمیل و رمز (حساب‌های قدیمی).

**`OtpAuthController.php`** — ورود/ثبت‌نام با موبایل و کدِ پیامکی (AUTH-1، AUTH-2):
`request` کد می‌فرستد و می‌گوید شماره ثبت‌شده هست یا نه؛ `verify` کد را می‌سنجد و
برای کاربرِ تازه نام و نام خانوادگی می‌خواهد (۴۲۲ با `needs_profile` پیش از مصرفِ
کد). `me`، `updateProfile`، و `linkMobile` (حسابِ ایمیلی شماره می‌گیرد و خریدهایش
می‌ماند). منطقِ کد در `app/Services/OtpService.php`:
- کد با `random_int` ساخته می‌شود و فقط هشش ذخیره می‌شود (`otp_codes`).
- اعتبار ۱۸۰ ثانیه است و ارسالِ دوباره ۱۲۰ ثانیه فاصله می‌خواهد.
- بعد از ۵ تلاشِ اشتباه کد باطل می‌شود.

سقفِ درخواست به‌ازای IP و شماره در `AppServiceProvider` تعریف شده است
(`otp-request`، `otp-verify`).

پیامک (`app/Services/Sms/`): رابطِ `SmsSender` با سه پیاده‌سازی، که از روی `SMS_DRIVER`
انتخاب می‌شود (`config/sms.php`):
- `LogSmsSender`: فقط لاگ. در حالتِ debug کد را در پاسخ هم برمی‌گرداند تا اپ بدونِ
  پیامک تست شود.
- `KavenegarSmsSender`: verify/lookup.
- `SmsIrSmsSender`: send/verify.

همه با قالبِ تأییدشده کار می‌کنند. `App\Support\Mobile::normalize` هر شکلِ شماره
(ارقامِ فارسی، ‎+98، …) را `09xxxxxxxxx` می‌کند.

**`BackupController.php`** — پشتیبانِ داده‌های کاربر (BK-1..3):
- **`syncStudy`:** همگام‌سازیِ دوطرفه و افزایشیِ «مرورِ من» (`study_items`).
  - قاعده‌ی «آخرین تغییر برنده است» با `client_updated_ms` اعمال می‌شود.
  - حذف = سنگِ قبر (`deleted`).
  - پاسخ = تغییرهای از `since_ms` به بعد (`server_ms`)، با ۱۰ ثانیه هم‌پوشانی.
  - `payload` همان JSONِ اپ است؛ ستون‌های جدا برای گزارش.
- **`getSettings` / `putSettings`:** یک JSON برای تنظیمات (`user_settings`).
- ثبتِ دستگاه (`POST /devices`) به `DeviceController::touch` رفت؛ پایین‌تر، «دستگاه‌ها».

**«تومانِ شناسه» (PAY-5):**
- **مبلغ:** به مبلغِ هر سفارشِ کارت‌به‌کارت عددی تصادفی از ۱ تا `CARD_UNIQUE_MAX` (پیش‌فرض ۹۹۹)
  تومان اضافه می‌شود (`OrderService::uniqueSuffix`). مبلغِ نهایی میانِ سفارش‌های کارت‌به‌کارتِ باز
  (در انتظارِ پرداخت یا بررسی) تکراری نیست.
- **ذخیره:** `amount` = مبلغِ نهایی و `amount_suffix` = همان عددِ اضافه.
- **پنل:** شناسه زیرِ مبلغ دیده می‌شود. جستجوی جدولِ سفارش‌ها با مبلغِ صورت‌حساب (ارقامِ فارسی و
  جداکننده هم پذیرفته می‌شود) همان سفارش را پیدا می‌کند.
- **اپ:** مبلغِ دقیق، دکمه‌ی کپی و توضیح را نشان می‌دهد.

**ویرایش و حذفِ سفارش (ADM-4):**
- `OrderService::adminUpdate`:
  - تغییرِ وضعیت به «پرداخت‌شده» `grant` را صدا می‌زند؛ پیامک اختیاری است.
  - برگرداندن از «پرداخت‌شده» فقط با `revoke` کتاب را پس می‌گیرد.
- `OrderService::adminDelete` فایلِ رسید را هم پاک می‌کند.
- `OrderService::revokeBook` کتاب را نگه می‌دارد اگر سفارشِ پرداخت‌شده‌ی دیگری برای همان کتاب
  باشد.
- **پنل:** کنش‌های «ویرایش»/«حذف» در «سفارش‌ها» (`OrderResource::editAction/deleteAction`) و در
  زبانه‌ی «سفارش‌ها»ی صفحه‌ی ویرایشِ کاربر (`OrdersRelationManager`).

**`OrderController.php`** — خرید از اپ (PAY-2/3):
- `options`: روش‌های فعال و مشخصاتِ کارت.
- `store`: سفارش می‌سازد.
  - درگاه ← `pay_url` (لینکِ امضاشده‌ی ۳۰ دقیقه‌ای).
  - کارت ← مشخصاتِ کارت.
  - کتابِ رایگان همان‌جا فعال می‌شود.
- `index`.
- `receipt`: رسیدِ کارت‌به‌کارت، فقط برای صاحبِ سفارش. تصویر به دیسکِ خصوصی
  (`receipts/`) می‌رود.

همه‌ی منطقِ سفارش در `app/Services/OrderService.php` است: `create`، `payUrl`،
`startGateway`، `verifyGateway`، `submitCardReceipt`، `approve`/`reject` (با پیامکِ
قالب‌دارِ اختیاری)، و **`grant` — تنها جایی که کتاب به کاربر داده می‌شود**.

### وب (`app/Http/Controllers/`)

- `WebController.php` — صفحه‌ی اصلی و `checkout`. `checkout` سفارش می‌سازد و
  به همان لینکِ امضاشده‌ای می‌رود که اپ استفاده می‌کند.
- 🌟 SITE-2/5/6: `WebAuthController.php` (ورودِ `/login` با دو تبِ موبایل و ایمیل، ثبت‌نامِ ایمیلیِ
  `/register`)، `PasswordResetController.php` (‎`/forgot-password`‎، ‎`/reset-password/{token}`‎ و
  ‎`POST /api/password/forgot`‎؛ پاسخ همیشه یکسان)، `PageController.php` (‎`/page/{about|terms|privacy}`‎
  و ‎`/contact`‎). نماها روی `layouts/site.blade.php` (Tailwindِ محلی: `public/js/tailwind-browser-4.3.3.js`،
  بی‌وابستگی به CDN) با `partials/announcement` و `partials/footer`.
- 🌟 PAY-6..8: `WebShopController.php` — خرید در سایت: ‎`/checkout/{book}`‎ (انتخابِ روش؛ فقط روش‌های
  روشن: `OrderService::methods()`)، سفارش‌های من ‎`/orders`‎ و ‎`/orders/{order}`‎ (برای کارت‌به‌کارت:
  مبلغِ دقیق با «تومانِ شناسه»، مشخصاتِ کارت و فرمِ رسید). `OrderService::cardInfo()` مشترکِ API و سایت.
  `OrderService::expireStale()` سفارشِ «در انتظارِ پرداخت» را بعد از `shop.pending_expire_hours` با
  `Order::AUTO_CANCEL_NOTE` لغو می‌کند (با هر سفارشِ تازه و فرمانِ ساعتیِ `orders:expire`)؛ کارت‌به‌کارتِ
  خودکار-لغوشده هنوز رسید می‌پذیرد (`Order::canSubmitReceipt`). رسیدِ تازه ← اعلانِ پایگاه‌داده‌ایِ
  Filament برای همه‌ی ادمین‌ها (زنگوله؛ جدولِ `notifications`) و اختیاری ایمیل به `shop.notify_email`.
- `PaymentController.php` — `start` (لینکِ امضاشده ← فرمِ درگاه) و `callback`
  (برگشت از درگاه). در `callback` شناسه‌ی تراکنشِ برگشتی (Authority) باید با سفارش جور
  باشد؛ نتیجه در `resources/views/payment/result.blade.php` نمایش داده می‌شود. هیچ‌کدام
  نشستِ وب نمی‌خواهند. درگاه: `PAYMENT_GATEWAY` (`config/shop.php`) و تنظیماتش در
  `config/payment.php` (حالتِ زرین‌پال با `ZARINPAL_MODE`).
- `WebOtpController.php` — ورود/ثبت‌نام با موبایل در سایت (`auth/login-otp.blade.php`).
- `Admin/OrderReceiptController.php` — تصویرِ رسیدِ کارت‌به‌کارت، فقط برای ادمین. صفحه‌ی اصلی فهرستِ
  **دسته‌بندی‌شده** است: کارت‌های دسته‌ها ← `/?category={id|other}` کتاب‌های همان
  دسته. با فقط یک دسته، مستقیم کتاب‌ها. نما در `resources/views/partials/catalog.blade.php`
  (مشترکِ `welcome` برای مهمان و `dashboard` برای کاربرِ واردشده).
- `AppReleaseController.php` — **توزیعِ خودِ اپ از سایت** (اپ در فروشگاه نیست):
  `page` صفحه‌ی `/app` (`resources/views/app.blade.php`، کارتِ هر سکو + راهنمای
  نصب)، `download` فایلِ آخرین نسخه با `response()->download` (Range دارد)، و
  `latest` برای `GET /api/app/latest?platform=` که اپ با آن نسخه‌ی تازه را تشخیص
  می‌دهد (`build_number`، `min_supported_build`، `page_url`). قانون‌های U-1 تا U-4
  در `RULES-rendering.md`ِ اپ.
- `WebAuthController.php` — ورود/خروجِ سایت. بعد از ورودِ موفق
  `redirect()->intended(...)` می‌کند تا کاربر به همان صفحه‌ای برگردد که
  جلسه‌اش منقضی شده بود.
- `Admin/BookAssetController.php` — سرو کردنِ فایلِ صوتی/تصویری برای
  پیش‌نمایش در پنلِ ادمین. **خودش ادمین‌بودن را چک می‌کند**؛ `auth` روی
  مسیر فقط لایه‌ی اول است.

### تنظیماتِ سایت (SITE-1..9)

- **`app/Support/SiteSettings.php`:** فهرستِ سفیدِ کلیدهای config که از پنل عوض می‌شوند (`FIELDS`).
  مقدارها در جدولِ `site_settings` (کلید = کلیدِ config، مقدار = JSON؛ کلیدهای `secret` با `Crypt`)
  ذخیره و در `AppServiceProvider::boot` با `config([...])` روی config نوشته می‌شوند. پس کدِ قدیمی
  (`config('shop.card.enabled')`، `config('sms.driver')`، …) بی‌تغییر از پنل پیروی می‌کند. ردیف‌ها
  (هنوز رمزنگاری‌شده) کش می‌شوند و با هر ذخیره پاک. پیش‌فرض‌ها: `config/site.php` و بقیه‌ی configها.
- **`app/Support/AuthMethods.php`:** روش‌های ورود. موبایل خاموش ← `otp/request|verify` (API و وب)
  ‎403 `reason=method_disabled`‎ و پیامکی فرستاده نمی‌شود. ایمیل خاموش ← `/api/login`، `/api/register`
  و وب ‎403/خطا‎، **جز ادمین‌ها**. ثبت‌نام بسته ← کاربرِ تازه با هیچ روشی ساخته نمی‌شود
  (‎`reason=registration_closed`‎). `AdminAuthenticate` وقتی ایمیل خاموش است به ‎`/admin/login`‎ می‌فرستد.
- **`PasswordRules`:** قانونِ رمز از `site.auth.password_mode` (strong/medium/lax) و پیام‌های فارسیِ
  فرم‌ها (`messages()`، `problem()`).
- **`Api/AppConfigController`:** ‎`GET /api/app/config`‎ برای اپ: روش‌ها و پیام‌ها، اطلاعیه، تعمیر،
  پشتیبانی، پیوندِ صفحه‌ها. در حالتِ تعمیر هم جواب می‌دهد.
- **میدل‌ویرها:** `SiteMaintenance` (گروه‌های web و api؛ ‎503‎ برای غیرِ ادمین؛ ورود، پنل، برگشتِ درگاه
  و ‎`/api/app/*`‎ باز) و `SecurityHeaders` (همه‌ی پاسخ‌ها).
- **محدودیتِ درخواست:** `throttle:login` (ورود/ثبت‌نامِ ایمیلی، ۵ در دقیقه به‌ازای ایمیل+IP) و
  `throttle:password-reset`.
- **پنل:** `Filament/Pages/SiteSettingsPage.php` (تب‌ها، پیامک/ایمیلِ آزمایشی، بازگردانی) و
  `Filament/Widgets/SiteOverview.php` (داشبورد).
- **تست‌ها:** `tests/Feature/SiteSettingsTest.php`.

### پنلِ ادمین (`app/Filament/`)

- `BookCategoryResource.php` — دسته‌های کتاب (عنوان، توضیح، **شماره‌ی ردیفِ
  یکتا**). دسته‌ای که کتاب دارد حذف نمی‌شود.
- `BookResource.php` — فرمِ کتاب و جدولِ فهرست. بخشِ «دسته و ترتیبِ نمایش»:
  دسته (با امکانِ ساختِ دسته‌ی تازه همان‌جا) و **شماره‌ی ردیف در دسته** (یکتا در
  هر دسته؛ با انتخابِ دسته «یکی بعد از آخرین» پیشنهاد می‌شود). جدول بر اساسِ دسته
  گروه‌بندی و به ترتیبِ ردیف مرتب است.
- **`BookResource/Pages/EditBook.php` (568 خط)** — بیشترِ کارِ ادمین این‌جاست:
  - `uploadArchiveAction` — آپلودِ ZIP یا RAR. **قبل از هر تخریبی**
    `probeArchive` چک می‌کند که فایل اصلاً قابلِ استخراج هست یا نه (وگرنه
    در حالتِ «جایگزینی» محتوا پاک می‌شد و بعد استخراج شکست می‌خورد).
  - `wipeScopeContent` — خالی‌کردنِ محتوای یک scope؛ `sample/` و `archive/`
    را عمداً نگه می‌دارد.
  - `extractZip` / `extractRar` / `rarTools` — PHP هیچ پشتیبانیِ داخلی از
    RAR ندارد، پس به‌ترتیب افزونه‌ی PECL، `unrar`، و `7z` امتحان می‌شوند
    (روی ویندوز مسیرهای متداولِ 7-Zip و WinRAR هم).
  - `deleteGroupAction` / `manageAssetsAction` / `assetRows` /
    `deleteAssetFile` — حذفِ گروهی و مدیریتِ تکیِ فایل‌ها.
  - `columnFor` / `versionColumnFor` — نگاشتِ (scope, kind) به نامِ ستونِ
    دیتابیس. **اگر ستونِ جدیدی اضافه کردید، این دو تا را هم به‌روز کنید.**
- `AppReleaseResource.php` — «نسخه‌های اپ»: سکو، نسخه، **build (یکتا در هر سکو؛
  همان عددِ بعد از `+` در pubspec)**، فایلِ نصبی (دیسکِ `local`، `app-releases/`)،
  تغییرات، «اجباری»، «منتشرشده».
- `OrderResource.php` — «سفارش‌ها»:
  - زبانه‌های «منتظرِ بررسی / پرداخت‌شده / همه» و نشانِ تعدادِ منتظرها روی منو.
  - کنشِ «رسید»: کدِ پیگیری، ۴ رقمِ کارت و تصویر.
  - «تأیید و فعال‌سازی» با `OrderService::approve`.
  - «رد» با دلیل، که کاربر در اپ می‌بیند.
- `UserResource.php` — «کاربران»:
  - **فهرست:** موبایل، تعدادِ خرید، تعدادِ موردهای مرور، دستگاه‌ها، نسخه‌ی اپ، آخرین بازدید.
    فیلترِ «غیرفعال‌ها» هم دارد.
  - **ساخت و ویرایش (ADM-1):** نام و نام خانوادگی (نامِ نمایشی خودکار ساخته می‌شود)،
    موبایل (هر شکلی، با `Mobile::normalize`)، ایمیل، ادمین‌بودن، و کتاب‌های خریداری‌شده
    برای فعال‌سازیِ دستی.
  - **رمز (ADM-2):** ادمین هر رمزی می‌گذارد، حتی یک‌رقمی؛ خالی یعنی بدونِ تغییر. ثبت‌نامِ
    خودِ کاربران در اپ و سایت از `App\Support\PasswordRules` پیروی می‌کند.
  - **«حذف» (ADM-3):** هر بار می‌پرسد داده‌ها نگه داشته شوند یا پاک (`UserDeletionService`).
    - **نگه‌داشتن:** soft delete؛ توکن‌ها و نشست‌ها باطل می‌شوند و ورودِ موبایلی ۴۰۳ می‌دهد.
      «فعال‌سازیِ دوباره» حساب را برمی‌گرداند.
    - **پاک‌کردن:** حساب، خریدها، سفارش‌ها و فایلِ رسیدها، مرور، تنظیمات، دستگاه‌ها، توکن‌ها
      و کدهای ورود حذف می‌شوند.
    - حذفِ خودِ ادمینِ واردشده ممکن نیست.
- **نشانِ پروژه:** `AdminPanelProvider` به‌جای نام و ویجتِ Filament، نامِ «آکادمی زبان و
  آیلتس» و آیکونِ اپ (`public/images/logo-192.png`، `public/favicon.ico`) را نشان
  می‌دهد. صفحه‌های سایت آیکون را از `partials/favicon.blade.php` می‌گیرند.
- `AdminPanelProvider.php` — رنگ، فونتِ فارسی، CSSِ تزریقی برای دکمه‌های
  شناور و راست‌به‌چپ، و میدل‌ویرهای سفارشیِ ورود.

### مدل‌ها و دیتابیس

`Book` ستون‌های محتوایی + **یک ستونِ نسخه به‌ازای هر نوع محتوا** دارد:
`json_version`, `audio_version`, `images_version`, `sample_version`. این
شماره‌ها با هر آپلود زیاد می‌شوند و اپ از رویِ همین‌ها می‌فهمد چه چیزی را
دوباره دانلود کند.

مهم: `Book::booted()` یک هوکِ حذف دارد که پوشه‌ی محتوا را هم پاک می‌کند.
عمداً در **مدل** است نه در صفحه‌ی ویرایش، تا هر سه مسیرِ حذف (دکمه‌ی
Delete، حذف از ردیفِ جدول، حذفِ گروهی) پوشش داده شوند.

جدولِ واسطِ `book_user` مالکیت را نگه می‌دارد — همان چیزی که
`authorizeScope` می‌خواند.

**دسته‌ها و ترتیبِ نمایش:** `book_categories` (`title`، `description`،
`sort_order` یکتا) و در `books`: `category_id` (null = «سایر کتاب‌ها»؛ کلیدِ
خارجیِ restrict، پس دسته‌ی پُر حذف نمی‌شود) و `sort_order` با ایندکسِ یکتای
`(category_id, sort_order)`. **ترتیبِ نمایش فقط از `Book::sortForCatalog` می‌آید**
(سایت و API هر دو؛ پنل هم همین ترتیب را دارد): دسته‌ها به `sort_order`ِ دسته،
بی‌دسته‌ها آخر، داخلِ دسته به `sort_order`ِ کتاب — نه ترتیبِ آپلود.
`Book::groupForCatalog` گروه‌بندیِ صفحه‌ی اصلی را می‌سازد. API هر کتاب را با شیءِ
`category` و `sort_order` می‌فرستد؛ نسخه‌های قدیمیِ اپ این‌ها را نادیده می‌گیرند
ولی همان ترتیبِ درست را می‌بینند. تست‌ها: `tests/Feature/BookCatalogTest.php`.

**معرفی در ویترین (SF-1):** ستون‌های `cover_image` (مسیر روی دیسکِ `local`، پوشه‌ی
`covers/`)، `description`، `author`، `publisher`، `level`. همه اختیاری‌اند و از بخشِ
«معرفی در ویترینِ اپ» در فرمِ کتاب پر می‌شوند. `GET /api/books` برای هر کتاب این‌ها را هم
می‌فرستد:
- `price`، `discount`، `final_price`
- `has_sample`: فقط وقتی فایلِ `sample_file_path` واقعاً روی دیسک هست (`Book::hasSample`).
  اپ دکمه‌ی «دریافت نمونه» را با همین فعال می‌کند.
- `page_count`: تعدادِ فایل‌های `pages/`ِ نسخه‌ی کامل، کش‌شده با کلیدِ `json_version`.
- `audio_count`: تعدادِ `audio_files`، پیش از اینکه مسیرها برای خریدارنشده خالی شوند.
- `cover_version` و `cover_url`: نسبی، `/api/books/{id}/cover?v=…`.
  - `cover_version` از مسیرِ فایل ساخته می‌شود. FileUpload هر جلدِ تازه را با نامِ تصادفی
    ذخیره می‌کند، پس جلدِ تازه یعنی نسخه‌ی تازه.
  - `GET /api/books/{book}/cover` عمومی است و `Cache-Control: immutable` دارد.
- **پاک‌شدنِ جلد:** هوک‌های `updated`/`deleted`ِ مدل جلدِ قبلی را با جایگزینی و جلدِ کتاب را
  با حذفِ کتاب پاک می‌کنند.
- **تست‌ها:** `tests/Feature/BookStorefrontTest.php`.

**نسخه‌های اپ:** `app_releases` (ایندکسِ یکتای `(platform, build_number)`).
`AppRelease::latestFor` = بیشترین build در میانِ منتشرشده‌های دارای فایل؛
`minSupportedBuild` = بیشترین build در میانِ منتشرشده‌های اجباری. هوک‌های مدل حجم را
از دیسک می‌خوانند و فایلِ قدیمی را با جایگزینی/حذف پاک می‌کنند. تست‌ها:
`tests/Feature/AppReleaseTest.php`.

**حساب، پشتیبان و فروش:**
- **`users`:** این ستون‌ها اضافه شدند:
  - `mobile` (یکتا)، `mobile_verified_at`، `first_name`، `last_name`، `last_seen_at`
  - `email` و `password` حالا nullable‌اند، چون حسابِ موبایلی آن‌ها را ندارد.
- **`otp_codes`:** کدهای ورود.
- **`study_items`:** مرورِ من؛ یکتا روی `(user_id, item_id)`.
- **`user_settings`:** یک JSON برای هر کاربر.
- **`user_devices`:** دستگاه‌ها؛ یکتا روی `(user_id, device_id)`.
- **`orders`:** هر تلاش برای خرید.
  - وضعیت‌ها: `pending` ← `paid` / `failed` / `canceled`؛ برای کارت:
    `awaiting_review` ← `paid` / `rejected`.
  - مبلغ (تومان)، کدهای پیگیری، مسیرِ رسید، بررسی‌کننده.
- **`book_user`:** هنوز تنها منبعِ مالکیت است؛ فقط `OrderService::grant` آن را پر
  می‌کند.

تست‌ها: `tests/Feature/OtpAuthTest.php`، `BackupSyncTest.php`، `OrderFlowTest.php`.

### دستگاه‌ها و مجوزِ آفلاین (DEV-1..4)

**هدف:** هر حساب فقط روی دستگاه‌های ثبت‌شده‌اش کار کند (پیش‌فرض ۱ دستگاه).

- **شناسه‌ی دستگاه:** اپ آن را از سخت‌افزار می‌سازد (sha256ِ ANDROID_ID یا MachineGuid؛
  `h1-…`) و در هر درخواست در هدرِ `X-Device-Id` می‌فرستد.
- **ورود** (`OtpAuthController::verify`، `AuthController::login/register`) از
  `DeviceService::signIn` می‌گذرد:
  - **جای خالی هست:** دستگاه فعال می‌شود (`user_devices.activated_at`). توکن با
    `personal_access_tokens.user_device_id` به آن گره می‌خورد و توکن‌های قبلیِ همان دستگاه
    پاک می‌شوند. پاسخ `license` را هم دارد.
  - **سقف پر است:** ۴۰۹ `device_limit` با دستگاه‌های فعال، `can_release`،
    `next_release_at` و یک **بلیت** (رمزشده با APP_KEY، ۱۰ دقیقه). با بلیت،
    `POST /api/auth/device/replace` یکی را آزاد و این دستگاه را وارد می‌کند، بی‌کدِ تازه.
  - **بی‌`device_id` (نسخه‌ی قدیمی):** ۴۲۶ `update_required`، پیش از فرستادنِ پیامک
    (`otp/request` هم چک می‌کند).
- **هر درخواست:** `Sanctum::authenticateAccessTokensUsing` (در `AppServiceProvider`) →
  `DeviceService::tokenAllowed`. روی همه‌ی مسیرها اجرا می‌شود، حتی مسیرهای عمومی‌ای که
  `auth('sanctum')->user()` را می‌خوانند (`/books`، دانلودها). توکن رد می‌شود اگر:
  - دستگاهش آزاد شده باشد (`device_revoked`؛ توکن پاک می‌شود)،
  - هدر با دستگاه نخواند (`device_mismatch`، یعنی توکنِ کپی‌شده؛ پاک می‌شود)،
  - هدر نباشد (`device_header_missing`؛ توکن می‌ماند)،
  - بی‌دستگاه باشد و `require_binding` روشن (`unbound`).

  دلیل در `request()->attributes['device_denied']` است. ۴۰۱ِ API آن را در `reason` برمی‌گرداند
  (`bootstrap/app.php`) و `GET /api/books` در `session`. اپ برای `device_revoked` و
  `device_mismatch` محتوای کامل را پاک می‌کند.
- **آزادسازی** (`DeviceService::release`): `revoked_at` و `revoked_by` (`user` | `admin`).
  - **کاربر:** هر `release_cooldown_days` روز یک‌بار (`nextSelfReleaseAt`)، از ۴۰۹ِ ورود یا
    `DELETE /api/devices/{id}`.
  - **ادمین:** همیشه، از کاربران ← ویرایش ← «دستگاه‌ها» (`DevicesRelationManager`). در فاصله‌ی
    کاربر حساب نمی‌شود.
  - **خروج** دستگاه را آزاد نمی‌کند؛ عمداً، وگرنه حساب با خروج و ورود دست‌به‌دست می‌شد.
- **سقف:** `users.max_devices`، وگرنه `DEVICE_LIMIT`. در پنل: «سقفِ دستگاه».
- **DEV-5:**
  - **ستون‌های تازه:** `user_devices.build_mode` (debug/profile/release) و شناسه‌ی کوتاه
    (`shortId`) در اپ و پنل.
  - **شناسه‌ی تصادفیِ قبلی:** ورود با `previous_device_id` (فقط `r1-`) ردیفِ فعالِ همان دستگاه
    را به شناسه‌ی سخت‌افزاریِ تازه منتقل می‌کند.
  - **پنل:** «ویرایش» (نام، مدل) و «حذف» (`DeviceService::forget`: ردیف و توکن‌هایش) کنارِ
    «آزادسازی».
  - **`revoked_by=system`:** «جایگزین‌شده»؛ در فاصله‌ی ۳۰روزه‌ی کاربر حساب نمی‌شود.
- **مجوزِ آفلاین** (`LicenseService`):
  - JSONِ `{v, uid, did, books, iat, exp}` با Ed25519 (sodium) امضا می‌شود. کلیدِ خصوصی در
    `LICENSE_SIGNING_KEY` است و با `php artisan license:keygen` ساخته می‌شود. کلیدِ عمومی در
    اپ است.
  - با ورود، `GET /books`، `POST /devices` و `GET /license` صادر می‌شود؛ فقط برای دستگاهِ
    فعال. اعتبار `LICENSE_DAYS`، پیش‌فرض ۳۰ روز.
  - اپ نسخه‌ی کامل را فقط با مجوزِ معتبرِ همین دستگاه و کاربر باز می‌کند. پس دستگاهِ آزادشده
    حتی اگر آنلاین نشود، حداکثر بعد از این مدت قفل می‌شود.
- **دوره‌ی انتقال:** `DEVICE_BINDING_REQUIRED=false`:
  - نسخه‌های قدیمیِ اپ هنوز وارد می‌شوند و توکنِ بی‌دستگاه کار می‌کند.
  - اپِ تازه با `session.bound=false` یک‌بار خارج می‌شود تا دستگاهش ثبت شود.
  - بعد از انتشارِ اجباریِ نسخه‌ی تازه، `true`.
- **تست‌ها:** `tests/Feature/DeviceBindingTest.php` و `AdminDevicesPanelTest.php` (جدولِ پنل).

### مرورِ آماده (ST-7)

واژه‌ها و نکته‌هایی که ادمین برای «مرورِ من»ِ همه‌ی کاربران آماده می‌کند.

- **قالبِ یکسان** (`App\Support\StudyItemFormat`): همان قالبی است که اپ برای واژه/نکته‌ی «خودم»
  و پرامپتِ هوش مصنوعی دارد. سند: `STUDY-ITEMS.md` در مخزنِ اپ.
  - `parse` خروجیِ هوش مصنوعی را تمیز می‌کند: ```json، متنِ اضافه، نام‌های دیگرِ فیلدها،
    تکراری‌ها.
  - `normalize` یک مورد را تمیز می‌کند.
  - `prompt('fa'|'ar'|'both')` پرامپت را می‌سازد. همتای اپ: `study_item_format.dart`؛ هر تغییری
    هر دو جا.
- **جدول `shared_study_items`:**
  - `type`، `text`، `key` (یکتا: نوع + متنِ کوچک‌شده)، `payload` (JSONِ قالب)، `published`،
    `changed_ms`، حذفِ نرم.
  - `changed_ms` یکتا و صعودی است (`nextChangedMs`) و با ذخیره، حذف و بازگردانی زیاد می‌شود.
- **API** (`SharedStudyController`): `GET /api/study/shared?since=`، عمومی.
  - **بی‌since:** منتشرشده‌ها.
  - **با since:** همه‌ی تغییرها، شاملِ حذف‌شده/منتشرنشده با `deleted=true`.
  - **صفحه‌بندی:** `cursor` و `more` (۵۰۰ تایی).
- **پنل** (`SharedStudyItemResource`، «مرورِ آماده»):
  - فرم با فیلدهای `payload.*`.
  - «پرامپتِ هوش مصنوعی».
  - «واردکردن از هوش مصنوعی» (`SharedStudyItem::import`): تکراری‌ها به‌روز و حذف‌شده‌ها
    برگردانده می‌شوند.
- **پشتیبانِ «مرورِ من»:** `kind=tip` را هم می‌پذیرد. فیلدهای اضافه در `x`ِ همان payload‌اند.
- **تست‌ها:** `tests/Feature/SharedStudyItemsTest.php`.

### انتقادات و پیشنهادات (FB-1)

- **مدل‌ها:** `Feedback` (نوع: گزارشِ ایراد/پیشنهاد/انتقاد/سایر، موضوع، شرح، کتاب، منبع و نسخه‌ی اپ،
  وضعیت، پاسخِ ادمین) و `FeedbackAttachment` (تا ۵ تصویر، هرکدام ۵MB، دیسکِ خصوصی `feedback/{id}/`).
  حذفِ پیام پوشه‌ی تصویرهایش را هم پاک می‌کند.
- **سایت:** `/feedback` (فقط واردشده): فرم با تصویر + «پیام‌های من» با پاسخِ ادمین؛ پیوند در منوی
  بالا و پانویس. تصویر فقط برای صاحبِ پیام و ادمین سرو می‌شود (`feedback.attachment`).
- **API:** `GET /api/feedback` (فهرست، `unseen` = پاسخ‌های ندیده)، `POST /api/feedback` (multipart،
  `images[]`)، `POST /api/feedback/seen`.
- **پنل:** «انتقادات و پیشنهادات» (`FeedbackResource`): شمارِ پیام‌های تازه روی منو؛ بازکردن = «در حالِ
  بررسی»؛ پاسخ و وضعیت. پیامِ تازه اعلانِ زنگوله (و ایمیلِ `shop.notify_email`) می‌فرستد.
- **قانون‌ها:** `FeedbackService::rules()` برای سایت و اپ یکی است.

### پشتیبان‌گیری (BKP-1)

- **`SiteBackupService`:** یک ZIP در `storage/app/private/backups`: `manifest.json` + `db/<جدول>.jsonl`
  (هر ردیف یک خط) + `files/` (رسیدها، تصویرهای پیام‌ها، جلدها).
  - جدول‌ها: کاربران، دسته‌ها، کتاب‌ها، خریدها، دستگاه‌ها، تنظیمات و «مرورِ من»ِ کاربران، سفارش‌ها،
    «مرورِ آماده»، تنظیماتِ سایت، نسخه‌های اپ، محتوا، پیام‌ها.
  - عمداً نه: محتوای کتاب‌ها (`books/`)، فایل‌های نصبیِ اپ، نشست/کش/صف/کدهای پیامکی/توکن‌ها/اعلان‌ها.
  - **بازگردانی:** اول پشتیبانِ ایمنی (`…-before-restore.zip`)، بعد هر جدولِ داخلِ پشتیبان خالی و پر
    می‌شود (FK خاموش، یک تراکنش؛ ستون‌های ناشناخته کنار گذاشته می‌شوند)، بعد فایل‌ها.
  - تنظیماتِ مخفی با APP_KEY رمز شده‌اند؛ روی سروری با APP_KEYِ دیگر دوباره باید وارد شوند.
- **پنل** («پشتیبان‌گیری»، `BackupsPage`): ساختن، دانلود، آپلود (از سرورِ دیگر)، حذف، بازگردانی (با
  نوشتنِ «بازگردانی»)، تنظیمِ پشتیبانِ خودکار (`backup.daily`، `backup.keep` در SiteSettings).
- **خودکار:** `php artisan site:backup` هر شب ۳:۳۰ (`routes/console.php`)، بعد پاک‌کردنِ قدیمی‌ها.
- **تست‌ها:** `tests/Feature/SiteBackupTest.php`.

### ریزه‌کاری‌های فروشگاه و پنل

- **PAY-8:** `OrderService::grant` سفارش‌های ناتمامِ دیگرِ همان کاربر/کتاب (در انتظارِ پرداخت،
  لغوشده، ناموفق؛ بی‌رسید) را حذف می‌کند. «سفارش‌های من» (`User::visibleOrders`) سفارش‌های ناتمامِ
  کتاب‌هایی را که کاربر از راهِ دیگر دارد نشان نمی‌دهد. migrationِ `purge_abandoned_orders_of_owned_books`
  داده‌ی قبلی را پاک کرد. «در انتظارِ بررسی» (رسید فرستاده شده) و «ردشده» می‌مانند.
- **SF-4:** `Book::hasContent()` (index.jsonِ اصلی روی دیسک). بی‌محتوا ← API `has_content=false`،
  ویترینِ سایت «در حالِ آماده‌سازی»، checkout و `POST /api/orders` رد (۴۲۲).
- **SITE-9:** فرمِ سایت با توکنِ CSRFِ کهنه ← برگشت به همان فرم با مقدارها و پیامِ «مهلتِ صفحه تمام
  شده بود» (`bootstrap/app.php`)، وگرنه `errors/419`. در پنل، ۴۱۹ِ Livewire (حتی بررسیِ خودکارِ
  اعلان‌ها) مودال نمی‌شود؛ صفحه یک‌بار از نو بار می‌شود (render hookِ `panels::body.end`).
- **ویرایشِ کاربر:** فیلدِ «رمزِ تازه» `autocomplete="new-password"` و ایمیل `off` دارند، تا مرورگر رمز
  و ایمیلِ ذخیره‌شده‌ی خودِ ادمین را در فرمِ کاربرِ دیگر پر نکند. رمزِ فعلی قابلِ نمایش نیست (فقط هَش).
- **SITE-10 (ادامه‌ی نشست):** `App\Support\LoginRedirect`. کوکیِ رمزشده‌ی `last_login` (شناسه‌ی کاربر)
  با رویدادِ `Login`ِ گاردِ web نوشته و با `Logout` پاک می‌شود؛ پس بودنش هنگامِ ورود یعنی نشستِ قبلی
  بی‌خروج تمام شده. پیش از `redirect()->intended()`، اگر کوکی مالِ کاربرِ دیگری است یا مقصد `/admin`
  است و کاربر ادمین نیست، `url.intended` کنار می‌رود (← `route('home')`، که ادمین را به `/admin` می‌برد).
  سه راهِ ورود همین را صدا می‌زنند: `WebAuthController::login`، `WebOtpController::verify` و
  `AdminLoginResponse` (binding برای `LoginResponse`ِ فیلامنت). صفحه‌ی ورود با کوکی + `url.intended`
  پیامِ «نشستِ شما تمام شده است» نشان می‌دهد. تست: `SessionResumeTest`.
- **SITE-12 (مدتِ نشست):** `session.lifetime` و `site.auth.remember` در `SiteSettings::FIELDS` (بخشِ «نشست»ِ
  تبِ ورود). `remember` خاموش ← `Auth::login(..., false)` در هر سه راهِ ورودِ سایت و چک‌باکسِ «مرا به خاطر
  بسپار» پنهان؛ لحظه‌ی خاموش شدن، `remember_token`ِ همه‌ی کاربران null می‌شود تا کوکی‌های ماندگارِ قبلی هم
  دیگر کار نکنند. بی‌این، پایانِ نشست برای کاربرِ «به خاطر سپرده» دیده نمی‌شد (ورودِ خودکار).
- **SITE-13 (تغییرِ رمز):** `AccountController` (`/account/password`، auth، throttle:login). رمزِ فعلی اگر
  کاربر رمز دارد؛ کاربرِ بی‌ایمیل رد. بعد از ذخیره: `remember_token` تازه (مرورگرهای دیگر)، ورودِ دوباره در همین
  مرورگر (با remember اگر کوکی‌اش بود) و `password_hash_web` در نشست تا `AuthenticateSession`ِ پنل ادمین را
  بیرون نیندازد. برای مدیرِ رمزِ مرورگر: ایمیل‌ها `autocomplete="username"`، فیلدِ پنهانِ username در فرمِ
  تغییرِ رمز، و ارسالِ واقعی + redirect. تست: `AccountPasswordTest`.
- **SITE-11 (پیشنهادِ مقدارهای قبلی):** فیلدهای فیلامنت `name` ندارند و با Livewire فرستاده می‌شوند، پس
  مرورگر تاریخچه‌شان را نگه نمی‌داشت. `filament/field-history.blade.php` (render hookِ `panels::body.end`)
  با رویدادِ submit مقدارها را در localStorage (`fieldHistory:v1`، کلید = مسیرِ بخش بی‌شناسه/create/edit +
  id فیلد، ۱۲ مقدار) نگه می‌دارد و با focusin یک `<datalist>` می‌سازد. رمزها (حتی revealable)،
  autocompleteهای new-password/current-password/one-time-code، فایل/چک‌باکس/تاریخ و جست‌وجوی Select کنار
  گذاشته می‌شوند؛ برای کنار گذاشتنِ بخشی دیگر: `data-no-field-history`.
- **صفحه‌ی ویرایشِ کتاب:** بعد از آپلود/حذفِ محتوا صفحه از نو بار می‌شود، تا شماره‌ی نسخه‌ها و فهرستِ
  فایل‌ها تازه باشند و «ذخیره»ی بعدی مقدارِ کهنه را رویشان ننویسد.

### میدل‌ویرها

`AdminAuthenticate` و `AdminAuthenticateSession` فقط یک کار می‌کنند:
`redirectTo()` را به `route('login')` تغییر می‌دهند تا انقضای جلسه به
صفحه‌ی ورودِ سایت برود نه `/admin/login` (🌟 SITE-2: اگر ورودِ ایمیلی خاموش باشد، `AdminAuthenticate`
به `/admin/login` می‌فرستد). `SiteMaintenance` و `SecurityHeaders` در بخشِ «تنظیماتِ سایت» آمده‌اند. **دو تا لازم است** چون انقضای
عادی و باطل‌شدنِ جلسه (بعد از تغییرِ رمز) دو مسیرِ جداگانه‌اند.

## نکته‌های عملی

- **Filament پارامترهای closure را با نام تزریق می‌کند:** `$query`، `$record`، `$state`، `$get`، …
  - با نامِ دیگر (مثلاً `$q`) و نوعِ `Builder` از کانتینر یک Builderِ خالی می‌سازد.
  - نتیجه: «Cannot use ::class on null».
  - در `modifyQueryUsing` همیشه `$query`.

- **آرشیوِ دانلود همیشه ZIP است**، حتی اگر ادمین RAR آپلود کند؛ سرور از
  رویِ محتوای استخراج‌شده ZIP می‌سازد. دلیلش این است که اپِ فلاتر فقط
  `ZipDecoder` دارد و پکیجِ `archive` اصلاً RAR را پشتیبانی نمی‌کند.
- در حالتِ آپلودِ **«بروزرسانی»**، فایلِ آپلودی نمی‌تواند خودش آرشیوِ دانلود
  شود، چون محتوای دیسک = قدیم + جدید است ولی فایل فقط بخشِ جدید را دارد.
  فقط در حالتِ «جایگزینی + ZIP» فایلِ آپلودی مستقیم نگه داشته می‌شود.
- **حساسیت به حروف بزرگ/کوچک:** نامِ فایلِ کلاس‌ها باید دقیقاً با نامِ کلاس
  یکی باشد. روی ویندوز کار می‌کند و روی سرورِ لینوکس PSR-4 می‌شکند — یک بار
  دقیقاً همین اتفاق افتاد.
- فونتِ فارسی محلی است: `public/css/persian-font.css` +
  `public/fonts/vazirmatn/`. پنلِ ادمین از طریقِ `->font(...)` با
  `LocalFontProvider` همان را می‌گیرد.
