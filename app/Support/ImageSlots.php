<?php

namespace App\Support;

class ImageSlots
{
    /**
     * Recommended display sizes for public media slots.
     * Uploads may be any size; frontend crops (large) or stretches (small) to these.
     */
    public const SLOTS = [
        'carousel' => [
            'width' => 1920,
            'height' => 800,
            'label' => 'Homepage carousel / hero',
        ],
        'breadcrumb' => [
            'width' => 1920,
            'height' => 300,
            'label' => 'Page banner / breadcrumb',
        ],
        'footer' => [
            'width' => 1920,
            'height' => 520,
            'label' => 'Footer background',
        ],
        'scheme_cover' => [
            'width' => 800,
            'height' => 450,
            'label' => 'Scheme cover (cards & listings)',
        ],
        'scheme_detail' => [
            'width' => 1200,
            'height' => 360,
            'label' => 'Scheme detail cover',
        ],
        'department_cover' => [
            'width' => 1200,
            'height' => 450,
            'label' => 'Department cover / banner',
        ],
        'department_card' => [
            'width' => 800,
            'height' => 450,
            'label' => 'Department card thumbnail',
        ],
        'about_side' => [
            'width' => 800,
            'height' => 560,
            'label' => 'About page side image',
        ],
        'favicon' => [
            'width' => 64,
            'height' => 64,
            'label' => 'Favicon',
        ],
    ];

    public static function get(string $slot): array
    {
        return self::SLOTS[$slot] ?? ['width' => 1200, 'height' => 675, 'label' => 'Image'];
    }

    public static function sizeLabel(string $slot): string
    {
        $s = self::get($slot);

        return $s['width'].' × '.$s['height'].' px';
    }

    public static function hint(string $slot): string
    {
        $s = self::get($slot);

        return 'Recommended size: '.$s['width'].' × '.$s['height'].' px'
            .' ('.$s['label'].'). Any size is allowed — larger images are cropped to fit;'
            .' smaller images are stretched to fill this area on the website.';
    }

    /** Map site-media setting keys to slots. */
    public static function forSetting(string $key): string
    {
        return match ($key) {
            'footer_background' => 'footer',
            'about_side_image' => 'about_side',
            'favicon' => 'favicon',
            default => 'breadcrumb',
        };
    }
}
