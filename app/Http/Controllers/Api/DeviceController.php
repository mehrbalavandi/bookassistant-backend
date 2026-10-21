<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\DeviceService;
use App\Services\LicenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 🌟 DEV-1..4: دستگاه‌های کاربر از دیدِ اپ.
 *
 * - `POST /api/auth/device/replace` (عمومی): بعد از ۴۰۹ِ سقفِ دستگاه، با بلیتی که همان
 *   پاسخ داده، یکی از دستگاه‌های فعال آزاد و این دستگاه وارد می‌شود (بی‌کدِ تازه).
 * - `GET /api/devices`: فهرستِ دستگاه‌های فعال، سقف، و اینکه کاربر الان می‌تواند آزاد کند یا نه.
 * - `DELETE /api/devices/{device}`: آزادسازی توسطِ کاربر (با فاصله‌ی `release_cooldown_days`).
 * - `POST /api/devices`: به‌روزکردنِ مشخصات (نسخه‌ی اپ، …) و تمدیدِ مجوز.
 * - `GET /api/license`: فقط تمدیدِ مجوز.
 */
class DeviceController extends Controller
{
    public function __construct(private DeviceService $devices, private LicenseService $licenses) {}

    public function replace(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ticket' => 'required|string',
            'release_device_id' => 'required|integer',
        ]);
        $ticket = $this->devices->readTicket($data['ticket']);
        if (! $ticket) {
            return response()->json([
                'success' => false,
                'reason' => 'ticket_expired',
                'message' => 'زمانِ این کار گذشته است؛ دوباره وارد شوید.',
            ], 422);
        }

        $user = User::find($ticket['uid']);
        if (! $user) {
            return response()->json(['success' => false, 'reason' => 'ticket_expired',
                'message' => 'این حساب دیگر فعال نیست.'], 422);
        }

        $next = $this->devices->nextSelfReleaseAt($user);
        if ($next !== null) {
            return $this->cooldown($next);
        }

        $old = UserDevice::where('user_id', $user->id)->active()->find($data['release_device_id']);
        if (! $old) {
            return response()->json(['success' => false, 'reason' => 'device_not_found',
                'message' => 'این دستگاه دیگر در حسابِ شما فعال نیست.'], 404);
        }
        if ($old->device_id === $ticket['info']['device_id']) {
            return response()->json(['success' => false, 'reason' => 'device_not_found',
                'message' => 'این همان دستگاهی است که با آن وارد می‌شوید.'], 422);
        }

        $this->devices->release($old, 'user');

        return $this->devices->signIn(
            $user,
            $ticket['info'],
            'دستگاهِ «'.$old->label().'» از حساب جدا شد و این دستگاه فعال شد.',
        );
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $current = $this->devices->current($request);
        $next = $this->devices->nextSelfReleaseAt($user);

        return response()->json([
            'success' => true,
            'limit' => $user->deviceLimit(),
            'devices' => UserDevice::where('user_id', $user->id)->active()->orderBy('activated_at')->get()
                ->map(fn (UserDevice $d) => $d->toAppArray($current?->id))->values(),
            'can_release' => $next === null,
            'next_release_at' => $next?->toIso8601String(),
            'cooldown_days' => (int) config('devices.release_cooldown_days', 30),
        ]);
    }

    public function destroy(Request $request, int $device): JsonResponse
    {
        $user = $request->user();
        $target = UserDevice::where('user_id', $user->id)->active()->find($device);
        if (! $target) {
            return response()->json(['success' => false, 'reason' => 'device_not_found',
                'message' => 'این دستگاه در حسابِ شما فعال نیست.'], 404);
        }
        $next = $this->devices->nextSelfReleaseAt($user);
        if ($next !== null) {
            return $this->cooldown($next);
        }

        $this->devices->release($target, 'user');
        $isCurrent = $this->devices->current($request)?->id === $target->id;

        return response()->json([
            'success' => true,
            'current_released' => $isCurrent,
            'message' => $isCurrent
                ? 'این دستگاه از حساب جدا شد.'
                : 'دستگاهِ «'.$target->label().'» از حساب جدا شد. محتوای کاملِ کتاب‌ها در اولین اتصالِ آن دستگاه پاک می‌شود.',
        ]);
    }

    public function touch(Request $request): JsonResponse
    {
        $user = $request->user();
        $device = $this->devices->current($request);

        if (! $device) {
            // توکنِ قدیمیِ بی‌دستگاه (فقط در دوره‌ی انتقال): همان رفتارِ BK-3.
            $data = $request->validate([
                'device_id' => 'required|string|max:64',
                'platform' => 'nullable|string|max:16',
                'app_version' => 'nullable|string|max:32',
                'build_number' => 'nullable|integer|min:0',
                'os_version' => 'nullable|string|max:64',
                'model' => 'nullable|string|max:100',
            ]);
            UserDevice::updateOrCreate(
                ['user_id' => $user->id, 'device_id' => $data['device_id']],
                array_merge($data, ['last_ip' => $request->ip(), 'last_seen_at' => now()]),
            );
            $user->forceFill(['last_seen_at' => now()])->save();

            return response()->json(['success' => true]);
        }

        $data = $request->validate([
            'device_name' => 'nullable|string|max:100',
            'platform' => 'nullable|string|max:16',
            'app_version' => 'nullable|string|max:32',
            'build_number' => 'nullable|integer|min:0',
            'build_mode' => 'nullable|string|in:debug,profile,release',
            'os_version' => 'nullable|string|max:64',
            'model' => 'nullable|string|max:100',
        ]);
        if (isset($data['device_name'])) {
            $data['name'] = $data['device_name'];
            unset($data['device_name']);
        }
        $device->fill(array_filter($data, fn ($v) => $v !== null && $v !== ''));
        $device->forceFill(['last_ip' => $request->ip(), 'last_seen_at' => now()])->save();
        $user->forceFill(['last_seen_at' => now()])->save();

        return response()->json([
            'success' => true,
            'license' => $this->licenses->issue($user, $device),
            // 🌟 SYNC-1: نام/موبایل/ایمیلی که در سایت عوض شده، با همین تماسِ همیشگیِ اپ به اپ می‌رسد.
            'user' => $user->toAppArray(),
        ]);
    }

    public function license(Request $request): JsonResponse
    {
        $device = $this->devices->current($request);
        if (! $device) {
            return response()->json(['success' => false, 'reason' => 'unbound',
                'message' => DeviceService::deniedMessage('unbound')], 409);
        }

        return response()->json([
            'success' => true,
            'license' => $this->licenses->issue($request->user(), $device),
            'user' => $request->user()->toAppArray(), // 🌟 SYNC-1
        ]);
    }

    private function cooldown($next): JsonResponse
    {
        return response()->json([
            'success' => false,
            'reason' => 'release_cooldown',
            'message' => 'هر '.(int) config('devices.release_cooldown_days', 30)
                .' روز فقط یک‌بار می‌توانید دستگاهی را از حساب جدا کنید. برای تغییرِ زودتر با پشتیبانی تماس بگیرید.',
            'next_release_at' => $next->toIso8601String(),
        ], 429);
    }
}
