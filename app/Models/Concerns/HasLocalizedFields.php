<?php

namespace App\Models\Concerns;

trait HasLocalizedFields
{
    /**
     * Return field value for the current locale.
     * When locale is Urdu, prefer {field}_ur and fall back to English.
     */
    public function t(string $field, ?string $default = null): ?string
    {
        $fallback = $this->getAttribute($field);
        $fallback = is_string($fallback) ? $fallback : $default;

        if (app()->getLocale() !== 'ur') {
            return $fallback;
        }

        $urKey = $field.'_ur';
        $urValue = $this->getAttribute($urKey);

        if (is_string($urValue) && trim($urValue) !== '') {
            return $urValue;
        }

        return $fallback;
    }
}
