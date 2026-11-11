<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class VoucherSerial extends Model
{
    protected $table = 'voucher_serials';

    protected $fillable = [
        'serial_no',
        'voucher_code',
        'application_id',
        'bill_id',
        'psid',
        'challan_no',
    ];

    protected function casts(): array
    {
        return [
            'serial_no' => 'integer',
            'application_id' => 'integer',
            'bill_id' => 'integer',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class, 'application_id');
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class, 'bill_id', 'bill_id');
    }

    /**
     * Pad an integer serial number from the left with zeros to make a 10-digit unique voucher ID.
     */
    public static function formatVoucherId(int $serialNo): string
    {
        return str_pad((string) $serialNo, 10, '0', STR_PAD_LEFT);
    }

    /**
     * Atomically allocate, record, and return the next sequential voucher serial number starting from 1.
     * Never duplicates any serial across challans or PSIDs; rolls back automatically if wrapping transaction fails.
     */
    public static function allocateNext(?Application $application = null, ?string $challanNo = null): self
    {
        return DB::transaction(function () use ($application, $challanNo) {
            // Lock the Global Default PsidConfig row as a consistent concurrency mutex across all schemes
            $lockedConfig = PsidConfig::whereNull('scheme_id')->where('is_active', true)->lockForUpdate()->first()
                ?? PsidConfig::whereNull('scheme_id')->lockForUpdate()->first()
                ?? PsidConfig::where('is_active', true)->lockForUpdate()->first()
                ?? PsidConfig::lockForUpdate()->first();

            if (! $lockedConfig) {
                $lockedConfig = PsidConfig::create([
                    'one_link_prefix' => '101530',
                    'department_code' => '09',
                    'district_code' => '01',
                    'department_service_no' => '01',
                    'rco' => 'GL1618',
                    'head' => 'C03870',
                    'is_active' => true,
                    'last_voucher_serial' => 0,
                ]);
                $lockedConfig = PsidConfig::where('config_id', $lockedConfig->config_id)->lockForUpdate()->first();
            }

            $maxRecorded = (int) (static::query()->lockForUpdate()->max('serial_no') ?? 0);
            $configLast = (int) ($lockedConfig->last_voucher_serial ?? 0);
            $nextSerial = max($maxRecorded, $configLast) + 1;

            $voucherCode = self::formatVoucherId($nextSerial);

            // Safety check in case a pre-existing voucher_serial or voucher_code somehow exists
            while (
                static::where('serial_no', $nextSerial)->exists() ||
                static::where('voucher_code', $voucherCode)->exists()
            ) {
                $nextSerial++;
                $voucherCode = self::formatVoucherId($nextSerial);
            }

            $lockedConfig->last_voucher_serial = $nextSerial;
            $lockedConfig->save();

            return static::create([
                'serial_no' => $nextSerial,
                'voucher_code' => $voucherCode,
                'application_id' => $application?->id,
                'challan_no' => $challanNo ?? $application?->challan_no,
            ]);
        });
    }
}

