# راه‌اندازیِ bookassistant-backend روی یک کامپیوترِ تازه (ویندوز)

> این راهنما پروژه را از صفر به همین‌جایی که هست می‌رساند. معماری و منطقِ کد در
> `ARCHITECTURE-backend.md` است و تاریخچه‌ی تغییرات در پوشه‌ی `Changes/`.
> همه‌ی مراحل یک‌بار روی یک کامپیوترِ تمیز (MySQL/MariaDB) اجرا و تست شدند.

**کوتاه‌ترین مسیر (اگر همه‌چیز نصب است):**
```bat
git clone https://github.com/mehrbalavandi/bookassistant-backend.git
cd bookassistant-backend
composer install
copy .env.example .env
php artisan key:generate
```
دیتابیسِ `bookassistant_db` را بسازید (بخشِ ۵)، بعد:
```bat
php artisan migrate
php artisan serve --host=0.0.0.0 --port=8000
```

---

## ۱. چه چیزهایی در git نیست؟ (قبل از عوض‌کردنِ کامپیوتر نسخه‌ی پشتیبان بگیرید)

| چیز | کجاست | چرا مهم است |
|---|---|---|
| فایلِ `.env` | ریشه‌ی پروژه | تنظیماتِ دیتابیس، کلیدِ برنامه، کدِ درگاه، **و `LICENSE_SIGNING_KEY`** (کلیدِ امضای مجوزِ آفلاین؛ اگر گم شود، همه‌ی اپ‌های منتشرشده تا نسخه‌ی تازه کتابِ خریده‌شده را باز نمی‌کنند) |
| **دیتابیس** | MySQL، دیتابیسِ `bookassistant_db` | کاربرها، خریدها، کتاب‌ها، دسته‌ها، نسخه‌های اپ |
| **محتوای کتاب‌ها و فایل‌های اپ** | `storage\app\private\` | پوشه‌ی `books\` (JSON، صوت، تصویر، ZIP) و `app-releases\` (APKها) |
| `vendor\` | ریشه‌ی پروژه | لازم نیست؛ `composer install` دوباره می‌سازدش |

دیتابیس و پوشه‌ی `storage\app\private\` **با هم** معنا دارند: دیتابیس می‌گوید چه
کتابی هست و فایل‌ها روی دیسک‌اند. هر دو را با هم منتقل کنید.

## ۲. نصبِ پیش‌نیازها

1. **Laragon** (همان چیزی که الان استفاده می‌کنید): PHP، MySQL و ترمینال را با هم می‌دهد.
   - **نسخه‌ی PHP باید ۸.۳ یا بالاتر باشد.** در ترمینالِ Laragon بزنید `php -v`. اگر
     کمتر بود، PHP 8.3 را به Laragon اضافه کنید (منوی Laragon ← PHP ← Version).
2. **Composer**: `composer --version`. اگر نبود، از getcomposer.org نصب کنید.
3. **Git**: `git --version`.
4. **7-Zip** (فقط اگر آرشیوِ RAR آپلود می‌کنید): در مسیرِ پیش‌فرض نصب کنید. پروژه
   خودش مسیرهای متداولِ 7-Zip و WinRAR را پیدا می‌کند. برای ZIP لازم نیست.

## ۳. تنظیمِ php.ini

مسیرِ فایل را با `php --ini` پیدا کنید. این فایل باید همان PHPای باشد که
`php artisan serve` با آن اجرا می‌شود.

**الف) این افزونه‌ها را فعال کنید.** `;` اولِ خط را بردارید:
```ini
extension=curl
extension=fileinfo
extension=intl
extension=mbstring
extension=openssl
extension=pdo_mysql
extension=zip
```
- `intl` را فیلامنت لازم دارد.
- `zip` را برای ساختنِ آرشیوِ کتاب‌ها لازم است.

**ب) سقفِ آپلود.** بدونِ این، آپلودِ فایل‌های بزرگ در پنل روی «Uploading 100%» گیر
می‌کند:
```ini
upload_max_filesize = 1024M
post_max_size = 1100M
```
`post_max_size` کمی بزرگ‌تر از `upload_max_filesize` باشد.

**ج) گواهیِ SSL برای درگاهِ پرداخت.** رفعِ خطای `cURL error 60`:
1. https://curl.se/ca/cacert.pem را دانلود کنید.
2. آن را مثلاً در `C:\laragon\bin\php\php-8.3.x\extras\ssl\` بگذارید.
3. در php.ini بنویسید:
```ini
curl.cainfo = "C:\laragon\bin\php\php-8.3.x\extras\ssl\cacert.pem"
openssl.cafile = "C:\laragon\bin\php\php-8.3.x\extras\ssl\cacert.pem"
```

بعد از هر تغییرِ php.ini، `php artisan serve` را ببندید و دوباره اجرا کنید.

## ۴. گرفتنِ کد و تنظیمِ `.env`

```bat
cd C:\laragon\www
git clone https://github.com/mehrbalavandi/bookassistant-backend.git
cd bookassistant-backend
composer install
```

**`.env`، یکی از این دو راه:**
- **راهِ ۱:** فایلِ `.env`ِ کامپیوترِ قبلی را کپی کنید. این راه بهتر است، چون همه‌چیز
  همان می‌ماند.
- **راهِ ۲: از الگو.** الگو حالا برای همین پروژه تنظیم شده است: MySQL، دیتابیسِ
  `bookassistant_db`، کاربرِ root بی‌رمز، و کلیدهای پروژه.
  ```bat
  copy .env.example .env
  php artisan key:generate
  ```
  بعد در `.env` این‌ها را بررسی کنید:
  - `DB_PASSWORD`: اگر MySQL رمز دارد.
  - `ZARINPAL_MERCHANT_ID`: کدِ درگاه.
  - `APP_URL`: آدرسِ سایت.

| کلید | معنی |
|---|---|
| `DB_*` | اتصال به MySQL |
| `ARCHIVE_UPLOAD_MAX_MB` / `ARCHIVE_UPLOAD_MAX_MINUTES` | سقفِ حجم و زمانِ آپلود در پنل (با php.ini هماهنگ) |
| `PASSWORD_MODE` | `strong` = رمزِ قوی در ثبت‌نامِ اپ؛ `lax` = هر رمزی (فقط برای تست) |
| `ZARINPAL_MERCHANT_ID` / `ZARINPAL_MODE` | کدِ پذیرنده‌ی زرین‌پال؛ `sandbox` برای تست، `normal` روی سرورِ واقعی |
| `PAYMENT_GATEWAY` / `PAYMENT_GATEWAY_ENABLED` | نامِ درایورِ درگاه در `config/payment.php` (zarinpal، zibal، sep، …) و روشن/خاموش |
| `CARD_TRANSFER_*` | کارت‌به‌کارت: `ENABLED=true`، شماره‌ی کارت، نامِ صاحبِ کارت، بانک، شبا (اختیاری) |
| `SMS_DRIVER` | `log` (توسعه: کد در لاگ و در پاسخِ API)، `kavenegar` یا `smsir` |
| `KAVENEGAR_*` / `SMSIR_*` | کلیدِ API و نام/شناسه‌ی قالب‌ها (قالبِ ورود: کاوه‌نگار `%token%`، sms.ir پارامترِ `CODE`) |
| `SANCTUM_EXPIRATION` | عمرِ توکنِ اپ به دقیقه (۵۲۵۶۰۰ = یک سال) |
| `DEVICE_LIMIT` | سقفِ دستگاه‌های فعالِ هر حساب (پیش‌فرض ۱). برای یک کاربر جدا: پنل ← کاربران ← «سقفِ دستگاه» |
| `DEVICE_RELEASE_COOLDOWN_DAYS` | کاربر هر چند روز یک‌بار خودش می‌تواند دستگاهی را جدا کند (پیش‌فرض ۳۰؛ ادمین همیشه) |
| `DEVICE_BINDING_REQUIRED` | `true` در حالتِ عادی. `false` فقط در دوره‌ی کوتاهِ انتقال تا نسخه‌های قدیمیِ اپ هنوز وارد شوند |
| `LICENSE_SIGNING_KEY` | کلیدِ خصوصیِ امضای مجوزِ آفلاین؛ با `php artisan license:keygen` ساخته می‌شود (بخشِ ۴-الف) |
| `LICENSE_DAYS` | اعتبارِ هر مجوزِ آفلاین به روز (پیش‌فرض ۳۰)؛ دستگاهِ جداشده‌ای که آنلاین نشود حداکثر بعد از این مدت قفل می‌شود |

**روی سرورِ واقعی:** `APP_ENV=production`، `APP_DEBUG=false` (وگرنه با `SMS_DRIVER=log`
کدِ ورود در پاسخ دیده می‌شود)، `ZARINPAL_MODE=normal`، و `SMS_DRIVER` یکی از دو
سامانه‌ی واقعی.
`LICENSE_SIGNING_KEY` هم باید پر باشد (بخشِ ۴-الف). بدونِ آن، نسخه‌ی release اپ کتاب‌های
خریده‌شده را باز نمی‌کند.

### ۴-الف. کلیدِ مجوزِ آفلاین و محدودیتِ دستگاه (DEV-1..4)

**فقط یک‌بار برای کلِ پروژه** — نه برای هر کامپیوتر. روی کامپیوتر یا سرورِ تازه همان
`LICENSE_SIGNING_KEY`ِ قبلی را در `.env` بگذارید و کلیدِ تازه نسازید.

1. **ساختنِ کلید:**
   ```bat
   php artisan license:keygen
   ```
   دو خط چاپ می‌کند:
   - **`LICENSE_SIGNING_KEY=…`:** به `.env` سرور می‌رود. هرگز در گیت نگذارید و از آن نسخه‌ی
     پشتیبان نگه دارید.
   - **`const String kLicensePublicKey = '…';`:** به
     `lib/core/license/license_public_key.dart` در مخزنِ اپ می‌رود.
2. `php artisan config:clear` (یا روی سرورِ واقعی `config:cache`).
3. **وارسی:**
   - `php artisan license:keygen --show` باید همان کلیدِ عمومیِ داخلِ اپ را چاپ کند.
   - اگر فرق داشت، اپ هیچ مجوزی را نمی‌پذیرد و کتاب‌های خریده‌شده باز نمی‌شوند.

**اگر کلید عوض شود:** اپ‌های منتشرشده مجوزِ تازه را نمی‌پذیرند، تا نسخه‌ای با کلیدِ عمومیِ
تازه بسازید و اجباری منتشر کنید. پس فقط وقتی عوضش کنید که کلیدِ خصوصی لو رفته باشد.

**اولین انتشارِ محدودیتِ دستگاه:** سرور و نسخه‌ی تازه‌ی اپ با هم منتشر شوند و نسخه‌ی تازه
«اجباری» باشد.
- اگر میانشان فاصله می‌افتد، تا انتشارِ اپ `DEVICE_BINDING_REQUIRED=false` و بعد `true`.
- کاربرانِ فعلی بعد از به‌روزرسانی یک‌بار دوباره وارد می‌شوند تا دستگاهشان ثبت شود.
- جزئیات در `ARCHITECTURE-backend.md`، بخشِ «دستگاه‌ها و مجوزِ آفلاین».

## ۵. ساختنِ دیتابیس

یکی از این دو راه:
- **از Laragon:** دکمه‌ی «Database» ← HeidiSQL ← راست‌کلیک ← Create new ← Database
  - نام: `bookassistant_db`
  - Collation: `utf8mb4_unicode_ci`
- **از ترمینالِ Laragon:**
  ```bat
  mysql -u root -e "CREATE DATABASE bookassistant_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
  ```

## ۶. جدول‌ها

**الف) پروژه‌ی خالی:**
```bat
php artisan migrate
```
همه‌ی جدول‌ها ساخته می‌شوند. بعد بخشِ ۷.

**ب) انتقال از کامپیوترِ قبلی:**
1. **روی کامپیوترِ قبلی** خروجیِ دیتابیس را بگیرید (ترمینالِ Laragon):
   ```bat
   mysqldump -u root --routines bookassistant_db > bookassistant_db.sql
   ```
   (یا در HeidiSQL: راست‌کلیک روی دیتابیس ← Export database as SQL.)
2. پوشه‌ی `storage\app\private\` را کامل کپی کنید.
3. **روی کامپیوترِ تازه:**
   1. دیتابیسِ خالی را بسازید (بخشِ ۵).
   2. خروجی را وارد کنید:
      ```bat
      mysql -u root bookassistant_db < bookassistant_db.sql
      ```
      این دستور را در ترمینالِ Laragon یا cmd بزنید؛ PowerShell علامتِ `<` را
      نمی‌پذیرد. راهِ دیگر: HeidiSQL ← File ← Run SQL file.
   3. پوشه‌ی کپی‌شده را در `storage\app\private\` بگذارید.
   4. این را اجرا کنید:
      ```bat
      php artisan migrate
      ```
      اگر خروجی قدیمی‌تر از کد باشد، جدول‌های تازه را اضافه می‌کند. داده‌های موجود
      دست نمی‌خورند.

وضعیتِ migrationها را با `php artisan migrate:status` ببینید. همه باید `Ran` باشند.

## ۷. کاربرِ ادمین (فقط برای پروژه‌ی خالی)

ورود به پنل (`/admin`) فقط برای کاربری است که `is_admin = 1` دارد. سایت هر ثبت‌نامی را
کاربرِ عادی می‌سازد. یکی از این دو (ایمیل و رمز را عوض کنید):

ساختنِ ادمینِ تازه:
```bat
php artisan tinker --execute="App\Models\User::create(['name'=>'Admin','email'=>'you@example.com','password'=>'YourPass#123','is_admin'=>true]);"
```
یا ادمین‌کردنِ کاربری که قبلاً در سایت ثبت‌نام کرده:
```bat
php artisan tinker --execute="App\Models\User::where('email','you@example.com')->update(['is_admin'=>true]);"
```

## ۸. اجرا

```bat
php artisan serve --host=0.0.0.0 --port=8000
```
- **سایت:** http://127.0.0.1:8000
- **پنل:** http://127.0.0.1:8000/admin
- **صفحه‌ی دانلودِ اپ:** http://127.0.0.1:8000/app

`--host=0.0.0.0` لازم است تا گوشی در همان شبکه به سرور برسد.
- آدرس را در اپ ← تنظیمات وارد کنید: `http://IPِ‌کامپیوتر:8000`. IP را با `ipconfig` پیدا
  کنید.
- اگر گوشی وصل نشد، اولین بار که ویندوز پرسید، اجازه‌ی دسترسیِ PHP در «Private
  network» را بدهید. یا در فایروال پورتِ 8000 را باز کنید.

## ۹. وارسیِ نهایی

- [ ] ورود به `/admin` و دیدنِ «کتاب‌ها»، «دسته‌ها» و «نسخه‌های اپ» بدونِ خطا
- [ ] `http://127.0.0.1:8000/api/app/latest?platform=android` ← `{"success":true,...}`
- [ ] کتاب‌ها در سایت و اپ دیده می‌شوند (اگر داده منتقل شد)
- [ ] آپلودِ یک فایلِ بزرگ در پنل (سقفِ نشان‌داده‌شده زیرِ فیلد باید حدودِ ۱ گیگابایت باشد)
- [ ] `php artisan license:keygen --show` همان کلیدِ عمومیِ داخلِ `license_public_key.dart` اپ را چاپ می‌کند

---

## بعد از هر `git pull` (مهم‌ترین عادت)

هر بار کد را از گیت گرفتید (یا فایل‌هایی را که من فرستادم جایگزین کردید):
```bat
composer install
php artisan migrate
php artisan optimize:clear
```
- `composer install`: اگر پکیجی اضافه شده باشد.
- `php artisan migrate`: اگر جدول یا ستونِ تازه‌ای آمده باشد.
- `php artisan optimize:clear`: پاک‌کردنِ کش‌ها.

اجرای هر سه وقتی چیزی عوض نشده هم بی‌ضرر است. در `Changes/` هر تغییری که migration
دارد، این را جدا هم گفته است.

## رفعِ خطاهای رایج

| خطا | علت | راه |
|---|---|---|
| `Base table or view not found: 1146 Table '...' doesn't exist` | کدِ تازه آمده ولی migrationش اجرا نشده | `php artisan migrate` |
| `No application encryption key has been specified` | `.env` بدونِ `APP_KEY` | `php artisan key:generate` |
| `Access denied for user 'root'` / `Unknown database` | `DB_*` در `.env` اشتباه است، یا دیتابیس ساخته نشده | بخش‌های ۴ و ۵؛ بعد `php artisan config:clear` |
| `could not find driver` | `pdo_mysql` خاموش است | بخشِ ۳-الف |
| ورود به `/admin` ← 403 | کاربر ادمین نیست | بخشِ ۷ |
| آپلود روی «Uploading 100%» می‌ماند | سقفِ php.ini | بخشِ ۳-ب، بعد سرور را دوباره اجرا کنید |
| `cURL error 60` در پرداخت | گواهیِ SSL | بخشِ ۳-ج |
| آپلودِ RAR: «… یکی از اینها لازم است» | ابزارِ RAR نیست | 7-Zip نصب کنید، یا ZIP آپلود کنید |
| `419 Page Expired` | جلسه منقضی شده یا `APP_KEY` عوض شده | صفحه را تازه کنید و دوباره وارد شوید |
| تغییرِ `.env` اثر ندارد | کشِ تنظیمات | `php artisan optimize:clear` |
| اپ به سرور وصل نمی‌شود | سرور فقط روی 127.0.0.1 است، یا فایروال | `--host=0.0.0.0` و بخشِ ۸ |
| کلاسی پیدا نمی‌شود (`Class ... not found`) | autoload کهنه است | `composer dump-autoload` |

## دستورهای پرکاربرد

| دستور | کار |
|---|---|
| `php artisan serve --host=0.0.0.0 --port=8000` | اجرای سرور |
| `php artisan migrate:status` | کدام migrationها اجرا شده‌اند |
| `php artisan route:list` | فهرستِ مسیرها |
| `php artisan optimize:clear` | پاک‌کردنِ همه‌ی کش‌های لاراول و فیلامنت |
| `php artisan test` | اجرای تست‌های خودکار (دیتابیسِ جدا در حافظه؛ به دیتابیسِ اصلی دست نمی‌زند) |
| `php artisan tinker` | کنسولِ PHP روی پروژه (خروج: `exit`) |
