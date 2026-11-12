<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsappTemplate extends Model
{
    protected $fillable = [
        'name',
        'event_key',
        'scheme_id',
        'send_language',
        'template_name',
        'message_en',
        'message_ur',
        'is_active',
        'is_system',
    ];

    protected function casts(): array
    {
        return [
            'scheme_id' => 'integer',
            'is_active' => 'boolean',
            'is_system' => 'boolean',
        ];
    }

    public function scheme(): BelongsTo
    {
        return $this->belongsTo(Scheme::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(WhatsappLog::class, 'whatsapp_template_id');
    }

    public static function eventLabels(): array
    {
        return SmsTemplate::eventLabels();
    }

    public function eventLabel(): string
    {
        return self::eventLabels()[$this->event_key] ?? ucwords(str_replace('_', ' ', (string) $this->event_key));
    }

    public static function availablePlaceholders(): array
    {
        return SmsTemplate::availablePlaceholders();
    }
}

