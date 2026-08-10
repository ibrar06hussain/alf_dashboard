<?php

namespace App\Support;

use App\Models\SiteSetting;

class Media
{
    public static function site(string $key, string $default = 'frontend/img/bg-img/1.jpg'): string
    {
        return SiteSetting::imageUrl($key, $default);
    }
}
