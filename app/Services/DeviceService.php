<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserDevice;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * 🌟 DEV-1..3: هر حساب فقط روی دستگاه‌های ثبت‌شده‌اش کار می‌کند.
 *
 * - **ثبتِ دستگاه هنگامِ ورود:** اپ شناسه‌ای می‌فرستد که از سخت‌افزار ساخته شده. اگر جای
 *   خالی در سقفِ دستگاه باشد، دستگاه «فعال» می‌شود و توکن به آن گره می‌خورد؛ وگرنه ۴۰۹ با
 *   فهرستِ دستگاه‌های فعال و یک «بلیتِ جایگزینی».
 * - **هر درخواست:** اپ هدرِ X-Device-Id را می‌فرستد. tokenAllowed (که از طریقِ
 *   Sanctum::authenticateAccessTokensUsing روی همه‌ی مسیرها اجرا می‌شود) توکنی را که
 *   دستگاهش آزاد شده یا از دستگاهِ دیگری آمده رد می‌کند؛ پس توکنِ کپی‌شده بی‌فایده است.
 * - **آزادسازی:** کاربر هر `release_cooldown_days` روز یک‌بار؛ ادمین همیشه.
 */
class DeviceService
{
    public const HEADER = 'X-Device-Id';

    /** دلیلِ ردِ توکن، برای پاسخِ ۴۰۱ و فیلدِ session در GET /api/books. */
    public const DENIED_ATTRIBUTE = 'device_denied';

    public function __construct(private LicenseService $licenses) {}

    // ───────── مشخصاتِ دستگاه از درخواستِ ورود ─────────

    /** @return array<string,mixed>|null null = اپ شناسه‌ی دستگاه نفرستاده (نسخه‌ی قدیمی). */
    public function deviceInfo(Request $request): ?array
    {
        if (! filled($request->input('device_id'))) {
            return null;
        }
        $data = Validator::make($request->all(), [
            'device_id' => ['required', 'string', 'min:8', 'max:64', 'regex:/^[A-Za-z0-9._:-]+$/'],
            'device_name' => 'nullable|string|max:100',
            'platform' => 'nullable|string|max:16',
            'model' => 'nullable|string|max:100',
            'app_version' => 'nullable|string|max:32',
            'build_number' => 'nullable|integer|min:0',
            'os_version' => 'nullable|string|max:64',
        ])->validate();

        return [
            'device_id' => $data['device_id'],
            'name' => $data['device_name'] ?? null,
            'platform' => $data['platform'] ?? null,
            'model' => $data['model'] ?? null,
            'app_version' => $data['app_version'] ?? null,
            'build_number' => $data['build_number'] ?? null,
            'os_version' => $data['os_version'] ?? null,
        ];
    }

    /** پیش از مصرفِ کد/رمز: نسخه‌ی قدیمیِ اپ (بی‌شناسه) در حالتِ اجباری ← ۴۲۶. */
    public function legacyRejection(Request $request): ?JsonResponse
    {
        if (! config('devices.require_binding') || filled($request->input('device_id'))) {
            return null;
        }

        return response()->json([
            'success' => false,
            'reason' => 'update_required',
            'message' => 'برای ورود، برنامه را به آخرین نسخه به‌روز کنید.',
        ], 426);
    }

    // ───────── فعال‌سازی، آزادسازی، توکن ─────────

    /** این دستگاه را برای کاربر فعال می‌کند؛ null اگر سقفِ دستگاه پر است. */
    public function activate(User $user, array $info): ?UserDevice
    {
        return DB::transaction(function () use ($user, $info) {
            // ورودِ هم‌زمان از دو دستگاه نباید هر دو از یک جای خالی رد شوند.
            User::whereKey($user->id)->lockForUpdate()->first();

            $device = UserDevice::firstOrNew(['user_id' => $user->id, 'device_id' => $info['device_id']]);
            $device->fill(array_filter(
                Arr::only($info, ['name', 'platform', 'model', 'app_version', 'build_number', 'os_version']),
                fn ($v) => $v !== null && $v !== '',
            ));

            if (! $device->isActive()) {
                $active = UserDevice::where('user_id', $user->id)->active()->count();
                if ($active >= $user->deviceLimit()) {
                    return null;
                }
                $device->activated_at = now();
                $device->revoked_at = null;
                $device->revoked_by = null;
            }

            $device->last_ip = request()->ip();
            $device->last_seen_at = now();
            $device->save();

            return $device;
        });
    }

    /**
     * دستگاه را آزاد می‌کند. توکن‌هایش عمداً پاک نمی‌شوند: اولین درخواستِ بعدیِ همان دستگاه
     * با `device_revoked` رد می‌شود (و آن‌وقت توکن پاک می‌شود)، تا اپ بفهمد آزاد شده و
     * محتوای کاملِ کتاب‌ها را پاک کند. تا آن لحظه هم tokenAllowed هیچ دسترسی‌ای نمی‌دهد.
     */
    public function release(UserDevice $device, string $by): void
    {
        $device->forceFill(['revoked_at' => now(), 'revoked_by' => $by])->save();
    }

    /** زمانی که کاربر دوباره می‌تواند خودش دستگاهی را آزاد کند؛ null = همین حالا. */
    public function nextSelfReleaseAt(User $user): ?CarbonInterface
    {
        $days = (int) config('devices.release_cooldown_days', 30);
        if ($days <= 0) {
            return null;
        }
        $last = UserDevice::where('user_id', $user->id)->where('revoked_by', 'user')->max('revoked_at');
        if (! $last) {
            return null;
        }
        $next = Carbon::parse($last)->addDays($days);

        return $next->isFuture() ? $next : null;
    }

    /** توکنِ تازه برای این دستگاه؛ نشست‌های قبلیِ همین دستگاه بسته می‌شوند. */
    public function issueToken(User $user, UserDevice $device): string
    {
        PersonalAccessToken::where('user_device_id', $device->id)->delete();
        $new = $user->createToken(mb_substr($device->label(), 0, 100));
        $new->accessToken->forceFill(['user_device_id' => $device->id])->save();

        return $new->plainTextToken;
    }

    // ───────── وارسیِ هر درخواست ─────────

    /**
     * از Sanctum::authenticateAccessTokensUsing صدا زده می‌شود (AppServiceProvider)؛ یعنی
     * روی همه‌ی مسیرهای auth:sanctum و هر جا auth('sanctum')->user() خوانده می‌شود.
     */
    public function tokenAllowed(PersonalAccessToken $token, Request $request): bool
    {
        if (! $token->user_device_id) {
            // توکنِ نسخه‌های قدیمیِ اپ. فقط در دوره‌ی انتقال (DEVICE_BINDING_REQUIRED=false) کار می‌کند.
            if (config('devices.require_binding')) {
                return $this->deny($request, 'unbound');
            }

            return true;
        }

        $device = UserDevice::find($token->user_device_id);
        if (! $device || ! $device->isActive() || (int) $device->user_id !== (int) $token->tokenable_id) {
            $token->delete();

            return $this->deny($request, 'device_revoked');
        }

        $header = trim((string) $request->header(self::HEADER));
        if ($header === '') {
            // هدر جا افتاده (مثلاً درخواستی از مسیرِ دیگری در اپ): رد، ولی توکن سالم می‌ماند.
            return $this->deny($request, 'device_header_missing');
        }
        if (! hash_equals($device->device_id, $header)) {
            // توکن از دستگاهِ دیگری آمده — یعنی کپی شده. باطلش می‌کنیم.
            $token->delete();

            return $this->deny($request, 'device_mismatch');
        }

        $request->attributes->set('device', $device);

        return true;
    }

    private function deny(Request $request, string $reason): bool
    {
        $request->attributes->set(self::DENIED_ATTRIBUTE, $reason);

        return false;
    }

    /** دستگاهِ همین درخواست (فقط بعد از اینکه tokenAllowed آن را پذیرفته باشد). */
    public function current(Request $request): ?UserDevice
    {
        $d = $request->attributes->get('device');

        return $d instanceof UserDevice ? $d : null;
    }

    public static function deniedMessage(?string $reason): string
    {
        return match ($reason) {
            'device_revoked' => 'این دستگاه از حسابِ شما جدا شده است. برای استفاده دوباره وارد شوید.',
            'device_mismatch' => 'این نشست مالِ دستگاهِ دیگری است و باطل شد. دوباره وارد شوید.',
            'unbound' => 'برای امنیتِ حساب، یک‌بار دیگر وارد شوید.',
            'device_header_missing' => 'شناسه‌ی دستگاه فرستاده نشد. برنامه را به‌روز کنید.',
            default => 'نشستِ شما معتبر نیست؛ دوباره وارد شوید.',
        };
    }

    // ───────── پاسخِ ورود ─────────

    /**
     * پاسخِ مشترکِ همه‌ی راه‌های ورود (کدِ پیامکی، ایمیل، جایگزینیِ دستگاه).
     * $info = null فقط وقتی ممکن است که require_binding خاموش باشد (نسخه‌ی قدیمیِ اپ).
     */
    public function signIn(User $user, ?array $info, string $message, int $status = 200): JsonResponse
    {
        $user->forceFill(['last_seen_at' => now()])->save();

        if ($info === null) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'token' => $user->createToken('flutter-app-token')->plainTextToken,
                'user' => $user->toAppArray(),
            ], $status);
        }

        $device = $this->activate($user, $info);
        if (! $device) {
            return $this->limitResponse($user, $info);
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'token' => $this->issueToken($user, $device),
            'user' => $user->toAppArray(),
            'device' => $device->toAppArray($device->id),
            'license' => $this->licenses->issue($user, $device),
        ], $status);
    }

    /** ۴۰۹: سقفِ دستگاه پر است. بلیت اجازه می‌دهد بی‌کدِ تازه یکی را جایگزین کند. */
    public function limitResponse(User $user, array $info): JsonResponse
    {
        $next = $this->nextSelfReleaseAt($user);
        $limit = $user->deviceLimit();

        return response()->json([
            'success' => false,
            'reason' => 'device_limit',
            'message' => $limit === 1
                ? 'این حساب روی دستگاهِ دیگری فعال است. هر حساب فقط روی یک دستگاه کار می‌کند.'
                : "این حساب روی {$limit} دستگاهِ دیگر فعال است و بیشتر از این ممکن نیست.",
            'limit' => $limit,
            'devices' => UserDevice::where('user_id', $user->id)->active()->orderBy('activated_at')->get()
                ->map(fn (UserDevice $d) => $d->toAppArray())->values(),
            'can_release' => $next === null,
            'next_release_at' => $next?->toIso8601String(),
            'cooldown_days' => (int) config('devices.release_cooldown_days', 30),
            'ticket' => $next === null ? $this->makeTicket($user, $info) : null,
        ], 409);
    }

    public function makeTicket(User $user, array $info): string
    {
        return Crypt::encryptString(json_encode([
            'uid' => $user->id,
            'info' => $info,
            'exp' => now()->addMinutes((int) config('devices.replace_ticket_minutes', 10))->timestamp,
        ]));
    }

    /** @return array{uid:int,info:array}|null */
    public function readTicket(?string $ticket): ?array
    {
        if (! is_string($ticket) || $ticket === '') {
            return null;
        }
        try {
            $data = json_decode(Crypt::decryptString($ticket), true);
        } catch (\Throwable) {
            return null;
        }
        if (! is_array($data) || ($data['exp'] ?? 0) < time() || ! isset($data['uid'], $data['info']['device_id'])) {
            return null;
        }

        return $data;
    }
}
