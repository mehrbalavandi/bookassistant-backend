<?php

namespace App\Services;

use App\Models\OtpCode;
use App\Services\Sms\SmsSender;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * 🌟 AUTH-2: کدِ یک‌بارمصرفِ ورود.
 * - کد تصادفیِ امن (random_int)، فقط هشش ذخیره می‌شود.
 * - اعتبار ۳ دقیقه؛ ارسالِ دوباره زودتر از ۲ دقیقه رد می‌شود؛ ۵ بار اشتباه ← کد باطل.
 * - هر کدِ تازه کدهای قبلیِ همان شماره را باطل می‌کند.
 * (محدودیتِ تعدادِ درخواست به‌ازای IP و شماره در RateLimiterِ «otp» است.)
 */
class OtpService
{
    public const OK = 'ok';
    public const WRONG = 'wrong';
    public const EXPIRED = 'expired';
    public const TOO_MANY = 'too_many';

    public function __construct(private readonly SmsSender $sms) {}

    /**
     * @return array{sent: bool, retry_after: int, code?: string}
     */
    public function request(string $mobile, ?string $ip): array
    {
        $cfg = config('sms.otp');
        $last = OtpCode::where('mobile', $mobile)->latest('id')->first();
        if ($last && $last->created_at && $last->created_at->diffInSeconds(now()) < $cfg['resend_seconds']) {
            return ['sent' => false, 'retry_after' => (int) ceil($cfg['resend_seconds'] - $last->created_at->diffInSeconds(now()))];
        }

        $length = max(4, min(8, $cfg['length']));
        $code = str_pad((string) random_int(0, 10 ** $length - 1), $length, '0', STR_PAD_LEFT);

        OtpCode::where('mobile', $mobile)->whereNull('consumed_at')->update(['consumed_at' => now()]);
        OtpCode::create([
            'mobile' => $mobile,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addSeconds($cfg['ttl_seconds']),
            'ip' => $ip,
        ]);

        try {
            $this->sms->sendOtp($mobile, $code);
        } catch (\Throwable $e) {
            Log::error('OTP send failed: '.$e->getMessage());
            OtpCode::where('mobile', $mobile)->whereNull('consumed_at')->update(['consumed_at' => now()]);
            throw $e;
        }

        $result = ['sent' => true, 'retry_after' => $cfg['resend_seconds']];
        if (config('sms.driver') === 'log' && config('app.debug') && $cfg['expose_in_debug']) {
            $result['code'] = $code;
        }

        return $result;
    }

    public function verify(string $mobile, string $code): string
    {
        $otp = OtpCode::where('mobile', $mobile)->whereNull('consumed_at')->latest('id')->first();
        if (! $otp || $otp->expires_at->isPast()) {
            return self::EXPIRED;
        }
        if ($otp->attempts >= config('sms.otp.max_attempts')) {
            $otp->update(['consumed_at' => now()]);

            return self::TOO_MANY;
        }
        $code = strtr(trim($code), ['۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9']);
        if (! Hash::check($code, $otp->code_hash)) {
            $otp->increment('attempts');

            return $otp->attempts >= config('sms.otp.max_attempts') ? self::TOO_MANY : self::WRONG;
        }
        $otp->update(['consumed_at' => now()]);

        return self::OK;
    }

    public static function message(string $result): string
    {
        return match ($result) {
            self::WRONG => 'کد وارد شده درست نیست.',
            self::EXPIRED => 'کد منقضی شده است؛ دوباره درخواست کنید.',
            self::TOO_MANY => 'تعدادِ تلاش‌های نادرست زیاد بود؛ کدِ تازه بگیرید.',
            default => '',
        };
    }
}
