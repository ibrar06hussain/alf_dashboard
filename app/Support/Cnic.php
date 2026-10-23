<?php

namespace App\Support;

class Cnic
{
    /**
     * Format a 13-digit CNIC into dashed format: XXXXX-XXXXXXX-X
     */
    public static function dashed(?string $cnic): string
    {
        $digits = self::clean($cnic);

        if (strlen($digits) !== 13) {
            return (string) $cnic;
        }

        return substr($digits, 0, 5) . '-' . substr($digits, 5, 7) . '-' . substr($digits, 12, 1);
    }

    /**
     * Clean CNIC to numeric digits only.
     */
    public static function clean(?string $cnic): string
    {
        return preg_replace('/\D+/', '', (string) $cnic);
    }

    /**
     * Check if a given CNIC has exactly 13 digits.
     */
    public static function isValid(?string $cnic): bool
    {
        return strlen(self::clean($cnic)) === 13;
    }

    /**
     * Mask a CNIC for PII security (e.g. 71501-*****12-3).
     */
    public static function mask(?string $cnic): string
    {
        $digits = self::clean($cnic);

        if (strlen($digits) !== 13) {
            if (empty($cnic)) {
                return '—';
            }
            $len = strlen($cnic);
            if ($len <= 4) {
                return str_repeat('*', $len);
            }
            return substr($cnic, 0, 2) . '*****' . substr($cnic, -2);
        }

        return substr($digits, 0, 5) . '-*****' . substr($digits, 10, 2) . '-' . substr($digits, 12, 1);
    }

    /**
     * Mask a phone number for PII security (e.g. 0300-***4567).
     */
    public static function maskPhone(?string $phone): string
    {
        if (empty($phone)) {
            return '—';
        }

        $digits = preg_replace('/\D+/', '', $phone);
        if (strlen($digits) >= 10) {
            $prefix = substr($digits, 0, 4);
            $suffix = substr($digits, -4);
            return $prefix . '-***' . $suffix;
        }

        if (strlen($phone) <= 4) {
            return str_repeat('*', strlen($phone));
        }

        return substr($phone, 0, 3) . '***' . substr($phone, -2);
    }
}
