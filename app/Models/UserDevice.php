<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * دستگاه‌های هر کاربر (BK-3) و ثبتِ دستگاه برای محدودیتِ دستگاه (DEV-1).
 *
 * «فعال» = activated_at دارد و revoked_at ندارد؛ فقط دستگاهِ فعال یکی از جاهای سقفِ
 * دستگاه را می‌گیرد و توکن‌هایش کار می‌کنند. device_id را اپ از سخت‌افزار می‌سازد
 * (اندروید: ANDROID_ID، ویندوز: MachineGuid)، پس نصبِ دوباره‌ی اپ همان دستگاه است.
 */
class UserDevice extends Model
{
    protected $fillable = [
        'user_id', 'device_id', 'name', 'platform', 'app_version', 'build_number', 'build_mode',
        'os_version', 'model', 'last_ip', 'last_seen_at',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
        'activated_at' => 'datetime',
        'revoked_at' => 'datetime',
        'build_number' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->whereNotNull('activated_at')->whereNull('revoked_at');
    }

    public function isActive(): bool
    {
        return $this->activated_at !== null && $this->revoked_at === null;
    }

    /** نامی که به کاربر و ادمین نشان داده می‌شود. */
    public function label(): string
    {
        $name = trim((string) ($this->name ?: $this->model));
        $platform = match ($this->platform) {
            'android' => 'اندروید',
            'windows' => 'ویندوز',
            default => (string) $this->platform,
        };

        return trim($name !== '' ? "{$name} ({$platform})" : $platform) ?: 'دستگاهِ بی‌نام';
    }

    /**
     * 🌟 DEV-5: شناسه‌ی کوتاه برای نمایش (مثلاً «h1-3fa9c2»)، تا کاربر و ادمین ببینند دو ردیف
     * واقعاً یک دستگاه‌اند یا نه. h1 = از سخت‌افزار، r1 = تصادفی (سخت‌افزار در دسترس نبود).
     */
    public function shortId(): string
    {
        return substr((string) $this->device_id, 0, 9);
    }

    /** نوعِ ساخت برای نمایش (فقط وقتی release نیست). */
    public function buildLabel(): ?string
    {
        return match ($this->build_mode) {
            'debug' => 'نسخه‌ی آزمایشی (debug)',
            'profile' => 'profile',
            default => null,
        };
    }

    /** شکلِ مشترکِ دستگاه برای اپ (فهرستِ دستگاه‌ها و پاسخِ سقفِ پُر). */
    public function toAppArray(?int $currentId = null): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label(),
            'platform' => $this->platform,
            'model' => $this->model,
            'app_version' => $this->app_version,
            'activated_at' => $this->activated_at?->toIso8601String(),
            'last_seen_at' => $this->last_seen_at?->toIso8601String(),
            'current' => $currentId !== null && $this->id === $currentId,
            'short_id' => $this->shortId(),
            'build_mode' => $this->build_mode,
        ];
    }
}
