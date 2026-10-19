<?php

namespace App\Services;

use App\Models\Feedback;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

/** 🌟 FB-1: ثبتِ انتقاد/پیشنهاد/گزارشِ ایراد (سایت و اپ) و اعلان به ادمین‌ها. */
class FeedbackService
{
    /** قانون‌های اعتبارسنجیِ مشترکِ سایت و اپ. */
    public static function rules(): array
    {
        return [
            'kind' => 'required|in:'.implode(',', array_keys(Feedback::KINDS)),
            'subject' => 'required|string|max:150',
            'message' => 'required|string|max:5000',
            'book_title' => 'nullable|string|max:150',
            'images' => 'nullable|array|max:'.Feedback::MAX_IMAGES,
            'images.*' => 'image|mimes:jpg,jpeg,png,webp,gif|max:'.Feedback::MAX_IMAGE_KB,
        ];
    }

    public static function messages(): array
    {
        return [
            'images.max' => 'حداکثر '.Feedback::MAX_IMAGES.' تصویر.',
            'images.*.image' => 'فقط تصویر (jpg، png، webp، gif) پذیرفته می‌شود.',
            'images.*.mimes' => 'فقط تصویر (jpg، png، webp، gif) پذیرفته می‌شود.',
            'images.*.max' => 'هر تصویر حداکثر '.(Feedback::MAX_IMAGE_KB / 1024).' مگابایت.',
        ];
    }

    /**
     * @param  UploadedFile[]  $images
     */
    public function submit(User $user, array $data, array $images, string $source, ?string $appVersion = null, ?string $platform = null): Feedback
    {
        $feedback = DB::transaction(function () use ($user, $data, $images, $source, $appVersion, $platform) {
            $f = Feedback::create([
                'user_id' => $user->id,
                'kind' => $data['kind'],
                'subject' => trim($data['subject']),
                'message' => trim($data['message']),
                'book_title' => isset($data['book_title']) ? trim((string) $data['book_title']) ?: null : null,
                'source' => $source,
                'app_version' => $appVersion ? mb_substr($appVersion, 0, 32) : null,
                'platform' => $platform ? mb_substr($platform, 0, 120) : null,
                'status' => 'new',
            ]);
            foreach (array_slice($images, 0, Feedback::MAX_IMAGES) as $img) {
                if (! $img instanceof UploadedFile || ! $img->isValid()) {
                    continue;
                }
                $path = $img->store("feedback/{$f->id}", 'local');
                $f->attachments()->create([
                    'path' => $path,
                    'original_name' => mb_substr($img->getClientOriginalName(), 0, 190),
                    'size' => (int) $img->getSize(),
                ]);
            }

            return $f;
        });

        $this->notifyAdmins($feedback);

        return $feedback;
    }

    /** پاسخِ ادمین (و تغییرِ وضعیت). */
    public function reply(Feedback $feedback, ?string $reply, string $status, User $admin): void
    {
        $changed = trim((string) $reply) !== trim((string) $feedback->admin_reply);
        $feedback->fill(['status' => $status, 'admin_reply' => filled($reply) ? trim($reply) : null]);
        if ($changed && filled($reply)) {
            $feedback->replied_by = $admin->id;
            $feedback->replied_at = now();
        }
        $feedback->save();
    }

    private function notifyAdmins(Feedback $f): void
    {
        $f->loadMissing('user');
        $title = Feedback::KINDS[$f->kind].'ِ تازه';
        $body = sprintf('%s — «%s»%s', $f->user?->name ?: ('کاربر #'.$f->user_id), $f->subject,
            $f->attachments()->count() ? ' — '.$f->attachments()->count().' تصویر' : '');
        try {
            $admins = User::where('is_admin', true)->get();
            if ($admins->isNotEmpty() && Schema::hasTable('notifications')) {
                \Filament\Notifications\Notification::make()->title($title)->body($body)->info()
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->actions([\Filament\Notifications\Actions\Action::make('open')->label('بررسی')
                        ->url(\App\Filament\Resources\FeedbackResource::getUrl('edit', ['record' => $f]))->markAsRead()])
                    ->sendToDatabase($admins);
            }
        } catch (\Throwable $e) {
            Log::warning("Feedback {$f->id} admin notification failed: ".$e->getMessage());
        }
        $email = trim((string) config('shop.notify_email'));
        if ($email !== '') {
            try {
                Mail::raw($title."\n\n".$body."\n\n".$f->message."\n\n".url('/admin/feedback/'.$f->id.'/edit'),
                    fn ($m) => $m->to($email)->subject($title.' — '.config('site.name')));
            } catch (\Throwable $e) {
                Log::warning("Feedback {$f->id} admin email failed: ".$e->getMessage());
            }
        }
    }
}
