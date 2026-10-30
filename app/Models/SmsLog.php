<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmsLog extends Model
{
    protected $fillable = [
        'application_id',
        'sms_template_id',
        'event_key',
        'recipient_phone',
        'recipient_name',
        'language',
        'is_unicode',
        'mask',
        'message',
        'status',
        'zong_status_code',
        'zong_sms_id',
        'zong_response',
        'sent_by',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'application_id' => 'integer',
            'sms_template_id' => 'integer',
            'is_unicode' => 'boolean',
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
        return $this->belongsTo(SmsTemplate::class, 'sms_template_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    /**
     * Map Zong CBS error / status codes from official documentation v2.0.
     */
    public static function zongCodeDescription(?string $code): string
    {
        return match ((string) $code) {
            '0' => '0 OK: Message submitted successfully',
            '101' => '101 Bad Request: loginId or Password cannot be null',
            '102' => '102 Bad Request: Request cannot be null',
            '103' => '103 Bad Request: Invalid source number (loginId)',
            '104' => '104 Unauthorized: Invalid username or password',
            '105' => '105 Bad Request: Invalid destination number',
            '106' => '106 Bad Request: Mask cannot be empty or null',
            '107' => '107 Bad Request: Error submitting',
            '108' => '108 Bad Request: Invalid Mask',
            '109' => '109 Bad Request: Message cannot be empty or null',
            '110' => '110 Bad Request: Insufficient balance or account expired',
            '111' => '111 Bad Request: Unicode cannot be empty or null',
            '112' => '112 Bad Request: Invalid Unicode',
            '117' => '117 Bad Request: Message Length exceeded (Max 12 SMS / 160 chars per SMS)',
            '118' => '118 Bad Request: Urdu / Chinese Message Length exceeded (Max 11 SMS / 70 chars per SMS)',
            '119' => '119 Bad Request: Short Code Preference cannot be empty or null',
            '120' => '120 Bad Request: Incorrect short code',
            '121' => '121 Bad Request: Account blocked (Multiple invalid attempts)',
            '125' => '125 No Bundles Found: No active SMS bundle assigned to this Zong CBS account',
            '407' => '407 Bad Request: ASCII conversion error',
            '409' => '409 Record Not Found: Data',
            '415' => '415 Invalid Mask: The specified Mask is not assigned to this Zong CBS account (User_Masks: No Mask)',
            '999' => '999 Exception: Remote server exception',
            'SIMULATED' => 'Logged locally (Zong CBS credentials not configured yet)',
            default => $code ? "Code {$code}" : '—',
        };
    }
}

