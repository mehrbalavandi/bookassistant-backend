<?php

namespace App\Filament\Resources\BookResource\Pages;

use App\Filament\Resources\BookResource;
use App\Http\Controllers\Api\BookController;
use App\Support\UploadLimits;
use Filament\Actions;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Storage;

class EditBook extends EditRecord
{
    protected static string $resource = BookResource::class;

    /**
     * نامِ کلاسِ افزونه‌ی اختیاریِ PECL rar. به‌صورتِ رشته نگه داشته می‌شود چون
     * این افزونه معمولاً نصب نیست و ارجاعِ مستقیم به آن، در IDE و ابزارهای
     * تحلیلِ ایستا خطای «Undefined type 'RarArchive'» می‌دهد — بدونِ اینکه
     * واقعاً باگی در کد باشد (همه‌ی استفاده‌ها پشتِ class_exists هستند).
     */
    private const RAR_EXTENSION_CLASS = 'RarArchive';

    protected function getHeaderActions(): array
    {
        return [
            // ── آپلودِ گروهیِ محتوا (خروجیِ اکسترکتور، به‌صورت ZIP یا RAR) ──
            Actions\ActionGroup::make([
                $this->uploadArchiveAction('main',   'آپلود محتوای اصلی (ZIP/RAR)'),
                $this->uploadArchiveAction('sample', 'آپلود محتوای نمونه (ZIP/RAR)'),
            ])->label('آپلود محتوا')->icon('heroicon-o-arrow-up-tray')->button(),

            // ── حذفِ گروهی ──
            Actions\ActionGroup::make([
                $this->deleteGroupAction('main',   'pages',  'حذف همه صفحاتِ اصلی'),
                $this->deleteGroupAction('main',   'audio',  'حذف همه صوت‌های اصلی'),
                $this->deleteGroupAction('main',   'images', 'حذف همه تصاویرِ اصلی'),
                $this->deleteGroupAction('sample', 'pages',  'حذف همه صفحاتِ نمونه'),
                $this->deleteGroupAction('sample', 'audio',  'حذف همه صوت‌های نمونه'),
                $this->deleteGroupAction('sample', 'images', 'حذف همه تصاویرِ نمونه'),
            ])->label('حذف گروهی')->icon('heroicon-o-trash')->color('danger')->button(),

            // ── مدیریتِ تکیِ فایل‌های صوت/تصویر (مشاهده + حذفِ فایل‌های انتخابی) ──
            Actions\ActionGroup::make([
                $this->manageAssetsAction('main',   'audio'),
                $this->manageAssetsAction('main',   'images'),
                $this->manageAssetsAction('sample', 'audio'),
                $this->manageAssetsAction('sample', 'images'),
            ])->label('مدیریت فایل‌ها')->icon('heroicon-o-folder-open')->button(),

            Actions\DeleteAction::make(),
        ];
    }

    // ───────────────────────── helpers ─────────────────────────

    private function scopeRoot(string $scope): string
    {
        $folder = $this->record->folder_name;
        return $scope === 'sample' ? "books/{$folder}/sample" : "books/{$folder}";
    }

    private function dir(string $scope, string $kind): string
    {
        return $this->scopeRoot($scope) . '/' . $kind;
    }

    // برچسبِ فارسیِ یک ترکیبِ scope+kind — یک‌جا تعریف شده تا همه‌جا از همین استفاده شود
    private function assetLabel(string $scope, string $kind): string
    {
        $scopeFa = $scope === 'sample' ? 'نمونه' : 'اصلی';
        $kindFa = match ($kind) {
            'pages'  => 'صفحات',
            'audio'  => 'صوت‌ها',
            'images' => 'تصاویر',
            default  => $kind,
        };
        return "{$kindFa}ِ {$scopeFa}";
    }

    private function columnFor(string $scope, string $kind): string
    {
        return match ([$scope, $kind]) {
            ['main', 'pages']    => 'json_file',
            ['main', 'audio']    => 'audio_files',
            ['main', 'images']   => 'images',
            ['sample', 'pages']  => 'sample_file_path',
            ['sample', 'audio']  => 'sample_audio_files',
            ['sample', 'images'] => 'sample_images',
        };
    }

    private function versionColumnFor(string $scope, string $kind): string
    {
        if ($scope === 'sample') {
            return 'sample_version';
        }
        return match ($kind) {
            'pages'  => 'json_version',
            'audio'  => 'audio_version',
            'images' => 'images_version',
        };
    }

    // ───────────────────────── آپلودِ آرشیو (ZIP یا RAR) ─────────────────────────

    private function uploadArchiveAction(string $scope, string $label): Actions\Action
    {
        return Actions\Action::make("upload_{$scope}")
            ->label($label)
            ->icon('heroicon-o-arrow-up-tray')
            ->color($scope === 'sample' ? 'warning' : 'primary')
            ->modalWidth('2xl')
            ->stickyModalHeader()
            ->stickyModalFooter()
            ->modalSubmitActionLabel('آپلود و اعمال')
            ->modalCancelActionLabel('انصراف')
            ->form([
                FileUpload::make('archive')
                    ->label('فایلِ فشرده (ZIP یا RAR) — شاملِ index.json و پوشه‌های pages/ , audio/ , images/')
                    // 🐞 نوعِ MIMEی که مرورگرها برای RAR می‌فرستند یکسان نیست
                    // (vnd.rar، x-rar-compressed، و حتی octet-stream روی بعضی
                    // ویندوزها). برای همین فهرست را سخاوتمندانه می‌گیریم و
                    // اعتبارسنجیِ واقعی را سمتِ سرور روی *پسوند* انجام می‌دهیم.
                    ->acceptedFileTypes([
                        'application/zip',
                        'application/x-zip-compressed',
                        'multipart/x-zip',
                        'application/vnd.rar',
                        'application/x-rar-compressed',
                        'application/x-rar',
                        'application/octet-stream',
                    ])
                    ->disk('local')->directory('tmp')->preserveFilenames()
                    // 🐞 فایل‌های بزرگ‌تر از ۱۲ مگابایت روی «Uploading 100%» گیر
                    // می‌کردند: سقفِ پیش‌فرضِ آپلودِ موقتِ Livewire ۱۲MB است و
                    // ردشدنِ فایل هیچ پیامی نشان نمی‌داد. سقف در
                    // config/livewire.php بالا برده شد، و این‌جا سقفِ *واقعی*
                    // (کمینه‌ی Livewire و php.ini) به FilePond داده می‌شود تا
                    // فایلِ بزرگ‌تر از توانِ سرور قبل از ارسال، با پیام، رد شود.
                    ->maxSize(UploadLimits::effectiveMaxKb())
                    ->helperText(UploadLimits::helperText())
                    ->required(),

                // 🌟 درخواستِ کاربر: قبل از استخراج پرسیده شود که با محتوای
                // قبلیِ همین بخش چه‌کار شود. «جایگزینی» یعنی پوشه‌ی محتوای
                // همین scope کاملاً خالی و بعد آرشیو در آن باز شود؛
                // «بروزرسانی» یعنی آرشیو روی محتوای موجود باز شود (فایل‌های
                // هم‌نام بازنویسی می‌شوند و بقیه سرِ جای‌شان می‌مانند).
                Radio::make('mode')
                    ->label('با محتوای قبلیِ این بخش چه‌کار شود؟')
                    ->options([
                        'replace' => 'حذفِ کاملِ پوشه‌ی قبلی و جایگزینی با این فایل',
                        'update'  => 'بروزرسانیِ محتویات (فایل‌های هم‌نام بازنویسی، بقیه دست‌نخورده)',
                    ])
                    ->descriptions([
                        'replace' => 'هرچه در این بخش هست پاک می‌شود و فقط محتوای همین فایل باقی می‌ماند.',
                        'update'  => 'فایل‌های قبلی که در این آرشیو نیستند حذف نمی‌شوند.',
                    ])
                    ->default('update')
                    ->required(),
            ])
            ->action(function (array $data) use ($scope) {
                // 🐞 استخراج و ساختِ دوباره‌ی ZIPِ دانلود برای آرشیوهای بزرگ
                // ممکن است از max_execution_time (پیش‌فرض ۳۰ ثانیه) بیشتر شود و
                // کار نیمه‌کاره بماند — مخصوصاً در حالتِ «جایگزینی» که محتوای
                // قبلی پاک شده است. پس برای این درخواست سقفِ زمان برداشته می‌شود.
                @set_time_limit(0);

                $relPath = $data['archive'];
                $mode = (($data['mode'] ?? 'update') === 'replace') ? 'replace' : 'update';
                $absPath = Storage::disk('local')->path($relPath);
                $ext = strtolower(pathinfo($relPath, PATHINFO_EXTENSION));

                if (! in_array($ext, ['zip', 'rar'], true)) {
                    Storage::disk('local')->delete($relPath);
                    Notification::make()
                        ->title('فقط فایل‌های ZIP یا RAR پذیرفته می‌شوند.')
                        ->danger()->send();
                    return;
                }

                // 🐞 پیش‌بررسی *قبل از* هر تخریبی: اگر فایل خراب باشد یا
                // (برای RAR) روی سرور هیچ ابزارِ بازکردنی نباشد، در حالتِ
                // «جایگزینی» محتوای قبلی پاک شده بود و بعد استخراج شکست
                // می‌خورد — یعنی کتاب بدونِ محتوا می‌ماند. پس اول امکانِ
                // استخراج را می‌سنجیم و تنها بعد از آن دست به پوشه می‌زنیم.
                $precheck = $this->probeArchive($absPath, $ext);
                if ($precheck !== null) {
                    Storage::disk('local')->delete($relPath);
                    Notification::make()
                        ->title('این فایل قابلِ استخراج نیست — هیچ تغییری اعمال نشد.')
                        ->body($precheck)
                        ->danger()->persistent()->send();
                    return;
                }

                // 🌟 حالتِ «جایگزینی»: قبل از استخراج، محتوای فعلیِ همین scope
                // پاک می‌شود. (پوشه‌ی sample و پوشه‌ی archive عمداً حفظ
                // می‌شوند — اولی محتوای scopeِ دیگر است و دومی آرشیوهای
                // دانلود که پایین‌تر خودشان مدیریت می‌شوند.)
                if ($mode === 'replace') {
                    $this->wipeScopeContent($scope);
                }

                $targetAbs = Storage::disk('local')->path($this->scopeRoot($scope));
                if (! is_dir($targetAbs)) {
                    mkdir($targetAbs, 0775, true);
                }

                $error = $ext === 'zip'
                    ? $this->extractZip($absPath, $targetAbs)
                    : $this->extractRar($absPath, $targetAbs);

                if ($error !== null) {
                    Storage::disk('local')->delete($relPath);
                    Notification::make()
                        ->title('استخراجِ فایل ناموفق بود.')
                        ->body($error)
                        ->danger()->persistent()->send();
                    return;
                }

                // 🌟 لیستِ فایل‌های صوت/تصویرِ روی دیسک را در DB ثبت کن — بدون این،
                // اپ فهرستِ خالی می‌بیند و هیچ صوت/تصویری دانلود نمی‌کند (بدون هیچ خطایی).
                // چون بعد از استخراج، *کلِ* پوشه دوباره اسکن می‌شود، هر دو حالتِ
                // replace و update خودبه‌خود فهرستِ درست را می‌دهند.
                $audioDir  = $this->scopeRoot($scope) . '/audio';
                $imagesDir = $this->scopeRoot($scope) . '/images';
                $audioFiles = Storage::disk('local')->exists($audioDir)
                    ? Storage::disk('local')->files($audioDir) : [];
                $imageFiles = Storage::disk('local')->exists($imagesDir)
                    ? Storage::disk('local')->files($imagesDir) : [];

                $this->record->update([
                    $this->columnFor($scope, 'pages')  => $this->scopeRoot($scope) . '/index.json',
                    $this->columnFor($scope, 'audio')  => $audioFiles,
                    $this->columnFor($scope, 'images') => $imageFiles,
                ]);

                if ($scope === 'sample') {
                    $this->record->increment('sample_version');
                } else {
                    $this->record->increment('json_version');
                    $this->record->increment('audio_version');
                    $this->record->increment('images_version');
                }

                // نام آرشیو شاملِ شماره‌نسخه‌هاست، پس باید بعد از increment و
                // refresh ساخته شود تا با چیزی که BookController موقعِ دانلود
                // دنبالش می‌گردد یکی باشد.
                $this->record->refresh();
                $archiveRel = BookController::zipRelativePath($this->record, $scope);
                Storage::disk('local')->makeDirectory(dirname($archiveRel));
                if (Storage::disk('local')->exists($archiveRel)) {
                    Storage::disk('local')->delete($archiveRel);
                }

                // 🐞 دو نکته‌ی مهم درباره‌ی آرشیوِ دانلود:
                // ۱) اپِ فلاتر فقط ZIP را باز می‌کند (کتابخانه‌ی archive اصلاً
                //    RAR را پشتیبانی نمی‌کند). پس آرشیوِ دانلود *همیشه* ZIP
                //    می‌ماند و اگر ادمین RAR آپلود کرده باشد، سرور از رویِ
                //    محتوایِ استخراج‌شده یک ZIP می‌سازد. با این کار هیچ تغییری
                //    سمتِ فلاتر لازم نیست.
                // ۲) در حالتِ «بروزرسانی»، محتوایِ رویِ دیسک = قدیم + جدید است،
                //    ولی خودِ فایلِ آپلودی فقط بخشِ جدید را دارد. اگر همان را
                //    آرشیو کنیم، کاربرِ اپ نسخه‌ی ناقص می‌گیرد. پس فقط در حالتِ
                //    «جایگزینی» و وقتی آپلود ZIP بوده، همان فایل نگه داشته
                //    می‌شود؛ در بقیه‌ی حالت‌ها آرشیو از رویِ دیسک ساخته می‌شود.
                if ($ext === 'zip' && $mode === 'replace') {
                    Storage::disk('local')->move($relPath, $archiveRel);
                    BookController::pruneOldZips($this->record, $scope, $archiveRel);
                } else {
                    Storage::disk('local')->delete($relPath);
                    BookController::ensureZip($this->record, $scope);
                }

                Notification::make()
                    ->title(
                        'محتوای «' . ($scope === 'sample' ? 'نمونه' : 'اصلی') . '» '
                            . ($mode === 'replace' ? 'جایگزین' : 'بروزرسانی') . ' شد.'
                    )
                    ->success()->send();
            });
    }

    /**
     * محتوای یک scope را خالی می‌کند (برای حالتِ «جایگزینی»).
     * پوشه‌ی sample (که محتوای scopeِ دیگر است) و پوشه‌ی archive (آرشیوهای
     * دانلود) عمداً دست‌نخورده می‌مانند.
     */
    private function wipeScopeContent(string $scope): void
    {
        $disk = Storage::disk('local');
        $root = $this->scopeRoot($scope);

        if (! $disk->exists($root)) {
            return;
        }

        $keep = $scope === 'sample' ? [] : ['sample', 'archive'];

        foreach ($disk->directories($root) as $directory) {
            if (in_array(basename($directory), $keep, true)) {
                continue;
            }
            $disk->deleteDirectory($directory);
        }

        foreach ($disk->files($root) as $file) {
            $disk->delete($file);
        }
    }

    /**
     * سنجشِ اینکه آیا این آرشیو اصلاً قابلِ بازکردن هست یا نه — بدونِ اینکه
     * چیزی روی دیسک تغییر کند. در صورتِ سالم‌بودن null، وگرنه پیامِ خطا.
     */
    private function probeArchive(string $archiveAbs, string $ext): ?string
    {
        if ($ext === 'zip') {
            $zip = new \ZipArchive();
            if ($zip->open($archiveAbs) !== true) {
                return 'باز کردنِ فایل ZIP ممکن نشد (فایل ناقص یا خراب است).';
            }
            $zip->close();
            return null;
        }

        if (class_exists(self::RAR_EXTENSION_CLASS)) {
            return null;
        }
        if (function_exists('exec') && ! empty($this->rarTools())) {
            return null;
        }

        return 'برای بازکردنِ RAR روی سرور یکی از اینها لازم است: افزونه‌ی PHPِ rar، یا ابزارِ unrar، یا 7-Zip. '
            . 'ساده‌ترین راه روی لینوکس: apt install unrar (یا p7zip-full). '
            . 'تا آن زمان می‌توانید همان محتوا را به‌صورت ZIP آپلود کنید.';
    }

    /** استخراجِ ZIP — در صورتِ موفقیت null، وگرنه پیامِ خطا برمی‌گرداند. */
    private function extractZip(string $archiveAbs, string $targetAbs): ?string
    {
        $zip = new \ZipArchive();
        if ($zip->open($archiveAbs) !== true) {
            return 'باز کردنِ فایل ZIP ممکن نشد (فایل ناقص یا خراب است).';
        }

        $ok = $zip->extractTo($targetAbs);
        $zip->close();

        return $ok ? null : 'استخراجِ فایل ZIP ناموفق بود (احتمالاً دسترسیِ نوشتن نیست).';
    }

    /**
     * استخراجِ RAR. برخلافِ ZIP، خودِ PHP هیچ پشتیبانیِ داخلی‌ای از RAR ندارد،
     * پس به‌ترتیب اینها امتحان می‌شوند:
     *   ۱) افزونه‌ی PECLِ rar (اگر روی سرور نصب باشد)
     *   ۲) ابزارِ خط‌فرمانِ unrar یا 7z/7za (روی ویندوز مسیرهای متداولِ
     *      7-Zip و WinRAR هم چک می‌شوند)
     * اگر هیچ‌کدام نبود، پیامِ راهنما برگردانده می‌شود.
     */
    private function extractRar(string $archiveAbs, string $targetAbs): ?string
    {
        if (class_exists(self::RAR_EXTENSION_CLASS)) {
            // 🐞 عمداً با نامِ *رشته‌ای* صدا زده می‌شود، نه \RarArchive::open()
            // مستقیم: افزونه‌ی rar روی اکثرِ نصب‌های PHP وجود ندارد، پس هم
            // IDE خطای «Undefined type» می‌داد و هم استاتیک‌آنالایزرها. این
            // شکل، تشخیصِ زمانِ اجرا را حفظ می‌کند و آن هشدارها را می‌بَرد.
            $rarClass = self::RAR_EXTENSION_CLASS;
            $rar = $rarClass::open($archiveAbs);
            if ($rar !== false) {
                foreach (($rar->getEntries() ?: []) as $entry) {
                    $entry->extract($targetAbs);
                }
                $rar->close();
                return null;
            }
        }

        if (! function_exists('exec')) {
            return 'روی این سرور نه افزونه‌ی rar نصب است و نه اجرای دستورِ سیستمی (exec) مجاز است. '
                . 'یا افزونه‌ی PHPِ rar را نصب کنید یا فایل را به‌صورت ZIP آپلود کنید.';
        }

        $targetDir = rtrim(str_replace('\\', '/', $targetAbs), '/') . '/';
        $lastOutput = '';

        foreach ($this->rarTools() as [$binary, $style]) {
            $command = $style === 'unrar'
                ? sprintf('%s x -o+ -y %s %s', escapeshellarg($binary), escapeshellarg($archiveAbs), escapeshellarg($targetDir))
                : sprintf('%s x -y -o%s %s', escapeshellarg($binary), escapeshellarg($targetDir), escapeshellarg($archiveAbs));

            $output = [];
            $code = 1;
            @exec($command . ' 2>&1', $output, $code);

            if ($code === 0) {
                return null;
            }
            $lastOutput = trim(implode(' ', array_slice($output, -3)));
        }

        return 'برای بازکردنِ RAR روی سرور یکی از اینها لازم است: افزونه‌ی PHPِ rar، یا ابزارِ unrar، یا 7-Zip. '
            . 'ساده‌ترین راه روی لینوکس: apt install unrar (یا p7zip-full). '
            . ($lastOutput !== '' ? 'آخرین خروجی: ' . $lastOutput : 'هیچ ابزارِ مناسبی پیدا نشد.');
    }

    /** فهرستِ ابزارهای قابلِ‌استفاده برای RAR، به‌صورت [مسیر/نامِ اجرایی, سبکِ آرگومان]. */
    private function rarTools(): array
    {
        $isWindows = PHP_OS_FAMILY === 'Windows';
        $probe = $isWindows ? 'where ' : 'command -v ';

        $tools = [];
        foreach ([['unrar', 'unrar'], ['7z', '7z'], ['7za', '7z']] as [$binary, $style]) {
            $output = [];
            $code = 1;
            @exec($probe . escapeshellarg($binary) . ' 2>&1', $output, $code);
            if ($code === 0 && ! empty($output)) {
                $tools[] = [$binary, $style];
            }
        }

        if ($isWindows) {
            $absolute = [
                ['C:\\Program Files\\7-Zip\\7z.exe', '7z'],
                ['C:\\Program Files (x86)\\7-Zip\\7z.exe', '7z'],
                ['C:\\Program Files\\WinRAR\\UnRAR.exe', 'unrar'],
                ['C:\\Program Files\\WinRAR\\Rar.exe', 'unrar'],
            ];
            foreach ($absolute as [$path, $style]) {
                if (is_file($path)) {
                    $tools[] = [$path, $style];
                }
            }
        }

        return $tools;
    }

    // ───────────────────────── حذفِ گروهی ─────────────────────────

    private function deleteGroupAction(string $scope, string $kind, string $label): Actions\Action
    {
        return Actions\Action::make("del_{$scope}_{$kind}")
            ->label($label)
            ->color('danger')
            ->requiresConfirmation()
            ->action(function () use ($scope, $kind) {
                Storage::disk('local')->deleteDirectory($this->dir($scope, $kind));

                $col = $this->columnFor($scope, $kind);
                if ($kind === 'pages') {
                    Storage::disk('local')->delete($this->scopeRoot($scope) . '/index.json');
                    $this->record->update([$col => null]);
                } else {
                    $this->record->update([$col => []]);
                }

                $this->record->increment($this->versionColumnFor($scope, $kind));

                Notification::make()
                    ->title('«' . $this->assetLabel($scope, $kind) . '» حذف شد.')
                    ->success()->send();
            });
    }

    // ───────────────────────── مدیریتِ تکیِ فایل‌ها ─────────────────────────

    /**
     * فهرستِ فایل‌های یک نوع را نشان می‌دهد: هر ردیف شاملِ چک‌باکسِ انتخاب،
     * پلیر (صوت) یا بندانگشتی (تصویر)، نام، و دکمه‌ی حذفِ همان ردیف.
     * 🐞 اصلاحِ درخواستیِ کاربر: چک‌باکس‌ها دیگر یک CheckboxListِ جدا در پایینِ
     * لیست نیستند؛ داخلِ *همان* ردیفِ پلیر/تصویر قرار گرفته‌اند. «انتخابِ همه»
     * بالای لیست و دکمه‌های «حذفِ انتخاب‌شده‌ها/انصراف» پایینِ مودال شناور
     * می‌مانند تا در لیست‌های بلند هم در دسترس باشند.
     */
    private function manageAssetsAction(string $scope, string $kind): Actions\Action
    {
        return Actions\Action::make("manage_{$scope}_{$kind}")
            ->label($this->assetLabel($scope, $kind))
            ->modalHeading('مدیریت ' . $this->assetLabel($scope, $kind))
            ->modalWidth('2xl')
            ->stickyModalHeader()
            ->stickyModalFooter()
            ->modalSubmitActionLabel('حذفِ انتخاب‌شده‌ها')
            ->modalCancelActionLabel('انصراف')
            ->form([
                // نامِ فیلد عمداً «files» مانده تا اکشنِ زیر بدونِ تغییر همان
                // آرایه‌ی مسیرها را بگیرد؛ فقط ظاهرِ انتخاب از CheckboxList به
                // چک‌باکسِ داخلِ ردیف منتقل شده است.
                ViewField::make('files')
                    ->hiddenLabel()
                    ->default([])
                    ->view('filament.book-assets')
                    ->viewData([
                        'scope'  => $scope,
                        'kind'   => $kind,
                        'bookId' => $this->record->getKey(),
                        'assets' => $this->assetRows($scope, $kind),
                    ]),
            ])
            ->action(function (array $data) use ($scope, $kind) {
                $selected = $data['files'] ?? [];
                if (empty($selected)) {
                    Notification::make()->title('هیچ فایلی انتخاب نشده بود.')->warning()->send();
                    return;
                }
                Storage::disk('local')->delete($selected);

                $col = $this->columnFor($scope, $kind);
                $remaining = array_values(array_diff($this->record->{$col} ?? [], $selected));
                $this->record->update([$col => $remaining]);

                $this->record->increment($this->versionColumnFor($scope, $kind));

                Notification::make()
                    ->title(count($selected) . ' فایل از «' . $this->assetLabel($scope, $kind) . '» حذف شد.')
                    ->success()->send();
            });
    }

    /**
     * ردیف‌های لیستِ مدیریتِ فایل: نامِ فایل (برای نمایش و پیش‌نمایش) و مسیرِ
     * کاملِ آن (مقدارِ چک‌باکس — همان چیزی که اکشنِ حذف انتظار دارد).
     */
    private function assetRows(string $scope, string $kind): array
    {
        $dir = $this->dir($scope, $kind);
        if (! Storage::disk('local')->exists($dir)) {
            return [];
        }

        return collect(Storage::disk('local')->files($dir))
            ->map(fn($path) => ['name' => basename($path), 'path' => $path])
            ->values()
            ->all();
    }

    /**
     * 🐞 حذفِ یک فایلِ تکی از دکمه‌ی کنارِ همان ردیف (wire:click در ویو).
     * همان کارهای جانبیِ حذفِ گروهی را انجام می‌دهد تا داده‌ها ناسازگار نشوند:
     * پاک‌کردنِ مسیر از ستونِ مربوطه و افزایشِ نسخه (تا اپ‌ها دلتا بگیرند).
     */
    public function deleteAssetFile(string $scope, string $kind, string $file): void
    {
        if (! in_array($scope, ['main', 'sample'], true)) {
            return;
        }
        if (! in_array($kind, ['audio', 'images'], true)) {
            return;
        }

        $name = basename($file);
        if ($name !== $file || $name === '') {
            return;
        }

        $path = $this->dir($scope, $kind) . '/' . $name;
        if (! Storage::disk('local')->exists($path)) {
            return;
        }

        Storage::disk('local')->delete($path);

        $col = $this->columnFor($scope, $kind);
        $remaining = array_values(array_diff($this->record->{$col} ?? [], [$path]));
        $this->record->update([$col => $remaining]);

        $this->record->increment($this->versionColumnFor($scope, $kind));
        $this->record->refresh();

        Notification::make()
            ->title('فایل «' . $name . '» حذف شد.')
            ->success()->send();
    }
}
