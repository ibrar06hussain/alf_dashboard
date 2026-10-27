<?php

namespace App\Support;

class NumberToWords
{
    /**
     * Convert an integer or float amount into English words.
     */
    public static function toWords(float|int $amount): string
    {
        $amount = (int) round($amount);
        if ($amount === 0) {
            return 'Zero';
        }

        if (class_exists(\NumberFormatter::class)) {
            try {
                $formatter = new \NumberFormatter('en', \NumberFormatter::SPELLOUT);
                $words = $formatter->format($amount);
                if ($words !== false && ! empty($words)) {
                    // Standardize title case and hyphens
                    return ucwords(str_replace(['-', '  '], [' ', ' '], $words));
                }
            } catch (\Throwable $e) {
                // Fall back to pure PHP implementation
            }
        }

        return self::convertIntegerToWords($amount);
    }

    /**
     * Convert amount to currency wording (e.g. "Fifty Thousand Rupees Only").
     */
    public static function toCurrencyWords(float|int $amount, string $currency = 'Rupees'): string
    {
        $words = self::toWords($amount);
        return trim($words . ' ' . $currency . ' Only');
    }

    /**
     * Pure PHP fallback converter for large integers.
     */
    private static function convertIntegerToWords(int $number): string
    {
        if ($number < 0) {
            return 'Minus ' . self::convertIntegerToWords(abs($number));
        }

        $units = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten',
            'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
        $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

        if ($number < 20) {
            return $units[$number];
        }

        if ($number < 100) {
            return trim($tens[(int) ($number / 10)] . ' ' . $units[$number % 10]);
        }

        if ($number < 1000) {
            return trim($units[(int) ($number / 100)] . ' Hundred ' . self::convertIntegerToWords($number % 100));
        }

        if ($number < 100000) {
            return trim(self::convertIntegerToWords((int) ($number / 1000)) . ' Thousand ' . self::convertIntegerToWords($number % 1000));
        }

        if ($number < 10000000) {
            return trim(self::convertIntegerToWords((int) ($number / 100000)) . ' Lakh ' . self::convertIntegerToWords($number % 100000));
        }

        return trim(self::convertIntegerToWords((int) ($number / 10000000)) . ' Crore ' . self::convertIntegerToWords($number % 10000000));
    }
}
