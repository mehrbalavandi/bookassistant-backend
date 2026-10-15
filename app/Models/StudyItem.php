<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** یک موردِ «مرورِ من» (BK-1). */
class StudyItem extends Model
{
    protected $fillable = [
        'user_id', 'item_id', 'kind', 'book_id', 'importance', 'status',
        'deleted', 'payload', 'client_updated_ms', 'server_ms',
    ];

    protected $casts = [
        'payload' => 'array',
        'deleted' => 'boolean',
        'importance' => 'integer',
        'client_updated_ms' => 'integer',
        'server_ms' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** همان JSONی که اپ فرستاده، به‌اضافه‌ی پرچمِ حذف. */
    public function toClientArray(): array
    {
        return array_merge($this->payload ?? [], [
            'id' => $this->item_id,
            'updated' => $this->client_updated_ms,
            'deleted' => $this->deleted,
        ]);
    }
}
