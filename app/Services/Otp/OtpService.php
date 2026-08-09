<?php

namespace App\Services\Otp;

use App\Models\OtpVerification;
use Illuminate\Support\Carbon;

class OtpService
{
    public function __construct(private SmsOtpSender $sender) {}

    public function send(string $phone): string
    {
        $phone = preg_replace('/\D+/', '', $phone);
        $code = (string) random_int(100000, 999999);

        OtpVerification::where('phone', $phone)->whereNull('verified_at')->delete();

        OtpVerification::create([
            'phone' => $phone,
            'code' => $code,
            'expires_at' => Carbon::now()->addMinutes(10),
        ]);

        $this->sender->send($phone, $code);

        return $code;
    }

    public function verify(string $phone, string $code): bool
    {
        $phone = preg_replace('/\D+/', '', $phone);

        $otp = OtpVerification::where('phone', $phone)
            ->whereNull('verified_at')
            ->where('expires_at', '>=', now())
            ->latest()
            ->first();

        if (! $otp || $otp->code !== $code) {
            return false;
        }

        $otp->update(['verified_at' => now()]);
        session(['phone_verified' => $phone]);

        return true;
    }

    public function isVerified(string $phone): bool
    {
        $phone = preg_replace('/\D+/', '', $phone);

        return session('phone_verified') === $phone;
    }
}
