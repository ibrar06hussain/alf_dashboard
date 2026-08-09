<?php

namespace App\Services\Otp;

use Illuminate\Support\Facades\Log;

class MockSmsOtpSender implements SmsOtpSender
{
    public function send(string $phone, string $code): void
    {
        Log::info('Mock OTP sent', ['phone' => $phone, 'code' => $code]);
        session()->flash('dev_otp', $code);
        session()->flash('dev_otp_phone', $phone);
    }
}
