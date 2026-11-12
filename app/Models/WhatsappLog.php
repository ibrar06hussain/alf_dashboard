<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappLog extends Model
{
    protected $fillable = [
        'application_id',
        'whatsapp_template_id',
        'event_key',
        'recipient_phone',
        'recipient_name',
        'operator',
        'language',
        'provider',
        'message',
        'status',
        'provider_status_code',
        'provider_message_id',
        'provider_response',
        'sent_by',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'application_id' => 'integer',
            'whatsapp_template_id' => 'integer',
            'sent_by' => 'integer',
            'sent_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(WhatsappTemplate::class, 'whatsapp_template_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public static function statusDescription(?string $code): string
    {
        return match ((string) $code) {
            '200', '201', 'OK', '0' => '200 OK: WhatsApp message accepted by gateway',
            '400' => '400 Bad Request: Invalid parameter or phone number format',
            '401' => '401 Unauthorized: Invalid or expired WhatsApp Access Token / API Key',
            '403' => '403 Forbidden: WhatsApp Business Account permission denied',
            '404' => '404 Not Found: Invalid Phone Number ID or API Endpoint URL',
            '429' => '429 Rate Limit: Too many WhatsApp API requests',
            '500' => '500 Server Error: Remote WhatsApp provider error',
            'SIMULATED' => 'Simulated locally (WhatsApp API Access Token not configured yet)',
            default => $code ? "Status {$code}" : '—',
        };
    }
}

