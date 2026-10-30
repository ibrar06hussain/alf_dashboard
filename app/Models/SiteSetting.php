<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SiteSetting extends Model
{
    protected $fillable = [
        'key', 'label', 'group', 'value', 'value_ur', 'fallback', 'fallback_ur', 'help_text',
    ];

    public static function getValue(string $key, ?string $default = null): ?string
    {
        $settings = static::cached();
        $definitionFallback = static::definitionFallback($key);

        if (! isset($settings[$key])) {
            return $default ?? $definitionFallback;
        }

        $row = $settings[$key];

        if ($row['value'] !== null && $row['value'] !== '') {
            return (string) $row['value'];
        }

        if ($row['fallback'] !== null && $row['fallback'] !== '') {
            return (string) $row['fallback'];
        }

        return $default ?? $definitionFallback;
    }

    public static function setValue(string $key, ?string $value, string $group = 'general', ?string $label = null): self
    {
        $setting = static::updateOrCreate(
            ['key' => $key],
            [
                'value' => $value,
                'group' => $group,
                'label' => $label ?? ucwords(str_replace('_', ' ', $key)),
            ]
        );

        static::flushCache();

        return $setting;
    }

    /**
     * Locale-aware text setting (English / Urdu) with :placeholder replacement.
     */
    public static function text(string $key, ?string $default = null, array $replace = []): string
    {
        $settings = static::cached();
        $row = $settings[$key] ?? null;
        $def = static::contentDefinition($key);

        $english = filled($row['value'] ?? null)
            ? (string) $row['value']
            : (string) ($row['fallback'] ?? $def['fallback'] ?? $default ?? '');

        $urdu = filled($row['value_ur'] ?? null)
            ? (string) $row['value_ur']
            : (string) ($row['fallback_ur'] ?? $def['fallback_ur'] ?? '');

        $value = app()->getLocale() === 'ur' && $urdu !== ''
            ? $urdu
            : $english;

        if ($value === '' && $default !== null) {
            $value = $default;
        }

        foreach ($replace as $search => $replacement) {
            $value = str_replace(':'.$search, (string) $replacement, $value);
        }

        return $value;
    }

    /** Resolve fallback path/text from media or content definition arrays. */
    public static function definitionFallback(string $key): ?string
    {
        foreach (static::mediaDefinitions() as $def) {
            if (($def['key'] ?? null) === $key) {
                return $def['fallback'] ?? null;
            }
        }

        $content = static::contentDefinition($key);

        return $content['fallback'] ?? null;
    }

    public static function contentDefinition(string $key): ?array
    {
        foreach (static::contentDefinitions() as $def) {
            if (($def['key'] ?? null) === $key) {
                return $def;
            }
        }

        return null;
    }

    public static function imageUrl(string $key, string $defaultAsset = 'frontend/img/bg-img/1.jpg'): string
    {
        $path = static::getValue($key, $defaultAsset) ?: $defaultAsset;

        if (str_starts_with($path, 'frontend/') || str_starts_with($path, 'http')) {
            return str_starts_with($path, 'http') ? $path : asset($path);
        }

        return asset('storage/'.$path);
    }

    public static function cached(): array
    {
        return Cache::remember('site_settings_map', 300, function () {
            return static::query()
                ->get(['key', 'value', 'value_ur', 'fallback', 'fallback_ur'])
                ->keyBy('key')
                ->map(fn ($row) => [
                    'value' => $row->value,
                    'value_ur' => $row->value_ur,
                    'fallback' => $row->fallback,
                    'fallback_ur' => $row->fallback_ur,
                ])
                ->all();
        });
    }

    public static function flushCache(): void
    {
        Cache::forget('site_settings_map');
    }

    protected static function booted(): void
    {
        static::saved(fn () => static::flushCache());
        static::deleted(fn () => static::flushCache());
    }

    public static function mediaDefinitions(): array
    {
        return [
            [
                'key' => 'footer_background',
                'label' => 'Footer background',
                'fallback' => 'frontend/img/bg-img/3.jpg',
                'help_text' => 'Background image behind the public site footer (fully dynamic — uploads replace the default).',
                'slot' => 'footer',
            ],
            [
                'key' => 'about_breadcrumb',
                'label' => 'About page banner',
                'fallback' => 'frontend/img/bg-img/5.jpg',
                'help_text' => 'Breadcrumb / banner image on the About page.',
                'slot' => 'breadcrumb',
            ],
            [
                'key' => 'about_side_image',
                'label' => 'About page side image',
                'fallback' => 'frontend/img/bg-img/2.jpg',
                'help_text' => 'Large image shown in the About page content.',
                'slot' => 'about_side',
            ],
            [
                'key' => 'cm_message_image',
                'label' => 'Chief Minister message banner',
                'fallback' => 'frontend/img/bg-img/cm-message-banner.png',
                'help_text' => 'Wide ALF / CM promotional banner on the home page. Recommended landscape ~1600×900 — the full artwork is shown without cropping.',
                'slot' => 'cm_banner',
            ],
            [
                'key' => 'contact_breadcrumb',
                'label' => 'Contact page banner',
                'fallback' => 'frontend/img/bg-img/11.jpg',
                'help_text' => 'Breadcrumb / banner image on the Contact page.',
                'slot' => 'breadcrumb',
            ],
            [
                'key' => 'schemes_breadcrumb',
                'label' => 'Schemes listing banner',
                'fallback' => 'frontend/img/bg-img/8.jpg',
                'help_text' => 'Default banner for the schemes index page.',
                'slot' => 'breadcrumb',
            ],
            [
                'key' => 'track_breadcrumb',
                'label' => 'Track application banner',
                'fallback' => 'frontend/img/bg-img/10.jpg',
                'help_text' => 'Breadcrumb / banner image on Track Application.',
                'slot' => 'breadcrumb',
            ],
            [
                'key' => 'default_breadcrumb',
                'label' => 'Default page banner',
                'fallback' => 'frontend/img/bg-img/2.jpg',
                'help_text' => 'Fallback banner when a page/scheme/department has no cover image.',
                'slot' => 'breadcrumb',
            ],
            [
                'key' => 'favicon',
                'label' => 'Favicon',
                'fallback' => 'frontend/img/core-img/favicon.ico',
                'help_text' => 'Browser tab icon (.ico, .png).',
                'slot' => 'favicon',
            ],
        ];
    }

    public static function contentDefinitions(): array
    {
        return [
            [
                'section' => 'Branding',
                'key' => 'site_name',
                'label' => 'Site name',
                'type' => 'text',
                'translatable' => true,
                'fallback' => 'ALF Gilgit-Baltistan',
                'fallback_ur' => 'اے ایل ایف گلگت بلتستان',
                'help_text' => 'Used in page titles and branding references.',
            ],
            [
                'section' => 'Branding',
                'key' => 'logo_primary',
                'label' => 'Logo primary text',
                'type' => 'text',
                'translatable' => true,
                'fallback' => 'ALF Gilgit',
                'fallback_ur' => 'اے ایل ایف',
                'help_text' => 'Green part of the text logo in header/footer.',
            ],
            [
                'section' => 'Branding',
                'key' => 'logo_secondary',
                'label' => 'Logo secondary text',
                'type' => 'text',
                'translatable' => true,
                'fallback' => ' Baltistan',
                'fallback_ur' => ' گلگت بلتستان',
                'help_text' => 'Second part of the text logo.',
            ],
            [
                'section' => 'Header',
                'key' => 'welcome_message',
                'label' => 'Top welcome message',
                'type' => 'textarea',
                'translatable' => true,
                'fallback' => 'Welcome to ALF Gilgit-Baltistan — Secretariat of Agriculture, Livestock & Dairy Development, and Fisheries',
                'fallback_ur' => 'اے ایل ایف گلگت بلتستان میں خوش آمدید — سیکرٹریٹ زراعت، لائیوسٹاک اینڈ ڈیری ڈیولپمنٹ، اور فشریز',
                'help_text' => 'Shown in the top bar of the public website.',
            ],
            [
                'section' => 'Chief Minister',
                'key' => 'cm_message_eyebrow',
                'label' => 'CM message eyebrow',
                'type' => 'text',
                'translatable' => true,
                'fallback' => 'Message from the Honourable Chief Minister',
                'fallback_ur' => 'محترم وزیراعلیٰ کا پیغام',
                'help_text' => 'Small heading above the Chief Minister message on the home page.',
            ],
            [
                'section' => 'Chief Minister',
                'key' => 'cm_message_name',
                'label' => 'CM name / title',
                'type' => 'text',
                'translatable' => true,
                'fallback' => 'Chief Minister, Gilgit-Baltistan',
                'fallback_ur' => 'وزیراعلیٰ، گلگت بلتستان',
                'help_text' => 'Name or title shown under the message (e.g. Honourable Chief Minister).',
            ],
            [
                'section' => 'Chief Minister',
                'key' => 'cm_message_body',
                'label' => 'CM message about ALF sector',
                'type' => 'textarea',
                'translatable' => true,
                'fallback' => 'Agriculture, livestock & dairy development, and fisheries are the backbone of livelihoods across Gilgit-Baltistan. Through the ALF portal, our Secretariat is committed to transparent, citizen-friendly digital services — so farmers, youth, and entrepreneurs can access schemes, track applications, and contribute to a more food-secure and prosperous Gilgit-Baltistan.',
                'fallback_ur' => 'زراعت، لائیوسٹاک اینڈ ڈیری ڈیولپمنٹ، اور فشریز گلگت بلتستان بھر میں روزگار کی بنیاد ہیں۔ اے ایل ایف پورٹل کے ذریعے ہمارا سیکرٹریٹ شفاف اور شہری دوست ڈیجیٹل خدمات کے لیے پرعزم ہے — تاکہ کسان، نوجوان اور کاروباری افراد اسکیمز تک رسائی حاصل کر سکیں، درخواستوں کا سراغ لگا سکیں، اور زیادہ غذائی تحفظ اور خوشحال گلگت بلتستان میں اپنا کردار ادا کر سکیں۔',
                'help_text' => 'Full message from the Chief Minister regarding the ALF sector (shown on the home page).',
            ],
            [
                'section' => 'Contact',
                'key' => 'contact_email',
                'label' => 'Contact email',
                'type' => 'email',
                'translatable' => false,
                'fallback' => 'info@alf.gb.gov.pk',
                'fallback_ur' => null,
                'help_text' => 'Public email shown in header, footer and contact page.',
            ],
            [
                'section' => 'Contact',
                'key' => 'contact_phone',
                'label' => 'Contact phone',
                'type' => 'text',
                'translatable' => false,
                'fallback' => '',
                'fallback_ur' => null,
                'help_text' => 'Optional phone number. Leave blank to hide.',
            ],
            [
                'section' => 'Contact',
                'key' => 'contact_address',
                'label' => 'Full address',
                'type' => 'textarea',
                'translatable' => true,
                'fallback' => 'Secretariat Complex, Gilgit, Gilgit-Baltistan',
                'fallback_ur' => 'سیکرٹریٹ کمپلیکس، گلگت، گلگت بلتستان',
                'help_text' => 'Full address on the Contact page.',
            ],
            [
                'section' => 'Contact',
                'key' => 'contact_address_short',
                'label' => 'Short address (footer)',
                'type' => 'text',
                'translatable' => true,
                'fallback' => 'Secretariat Complex, Gilgit',
                'fallback_ur' => 'سیکرٹریٹ کمپلیکس، گلگت',
                'help_text' => 'Shorter address line used in the footer.',
            ],
            [
                'section' => 'Contact',
                'key' => 'organization_name',
                'label' => 'Organization / government line',
                'type' => 'text',
                'translatable' => true,
                'fallback' => 'Government of Gilgit-Baltistan',
                'fallback_ur' => 'حکومت گلگت بلتستان',
                'help_text' => 'Shown on Contact and About pages.',
            ],
            [
                'section' => 'Contact',
                'key' => 'contact_intro',
                'label' => 'Contact page intro',
                'type' => 'textarea',
                'translatable' => true,
                'fallback' => 'For scheme-related queries, application guidance, or departmental coordination, contact the ALF Secretariat.',
                'fallback_ur' => 'اسکیم سے متعلق سوالات، درخواست کی رہنمائی، یا محکمہ جاتی رابطے کے لیے اے ایل ایف سیکرٹریٹ سے رابطہ کریں۔',
                'help_text' => 'Paragraph under the Contact page heading.',
            ],
            [
                'section' => 'Contact',
                'key' => 'contact_heading',
                'label' => 'Contact page heading',
                'type' => 'text',
                'translatable' => true,
                'fallback' => 'ALF Secretariat',
                'fallback_ur' => 'اے ایل ایف سیکرٹریٹ',
                'help_text' => 'Main heading on the Contact page.',
            ],
            [
                'section' => 'Footer',
                'key' => 'footer_about',
                'label' => 'Footer about text',
                'type' => 'textarea',
                'translatable' => true,
                'fallback' => 'Secretariat of Agriculture, Livestock & Dairy Development, and Fisheries — Government of Gilgit-Baltistan.',
                'fallback_ur' => 'سیکرٹریٹ زراعت، لائیوسٹاک اینڈ ڈیری ڈیولپمنٹ، اور فشریز — حکومت گلگت بلتستان۔',
                'help_text' => 'Short description under the footer logo.',
            ],
            [
                'section' => 'Footer',
                'key' => 'citizen_services_blurb',
                'label' => 'Citizen services blurb',
                'type' => 'textarea',
                'translatable' => true,
                'fallback' => 'Apply online with CNIC verification and track your application status in real time.',
                'fallback_ur' => 'شناختی کارڈ کی تصدیق کے ساتھ آن لائن درخواست دیں اور اپنی درخواست کی صورتحال حقیقی وقت میں دیکھیں۔',
                'help_text' => 'Footer “Citizen Services” paragraph.',
            ],
            [
                'section' => 'Footer',
                'key' => 'copyright_text',
                'label' => 'Copyright text (legacy)',
                'type' => 'textarea',
                'translatable' => true,
                'fallback' => 'Powered By Information Technology Department, Government of Gilgit-Baltistan © :year All rights reserved',
                'fallback_ur' => 'تیار کردہ محکمہ انفارمیشن ٹیکنالوجی، حکومت گلگت بلتستان © :year جملہ حقوق محفوظ ہیں',
                'help_text' => 'Legacy field. Public footer credit is fixed in translations (IT Department link). Use :year for the year.',
            ],
            [
                'section' => 'About',
                'key' => 'about_eyebrow',
                'label' => 'About page eyebrow',
                'type' => 'text',
                'translatable' => true,
                'fallback' => 'Government of Gilgit-Baltistan',
                'fallback_ur' => 'حکومت گلگت بلتستان',
                'help_text' => 'Small heading above the About title.',
            ],
            [
                'section' => 'About',
                'key' => 'about_heading',
                'label' => 'About page heading',
                'type' => 'text',
                'translatable' => true,
                'fallback' => 'ALF Gilgit-Baltistan',
                'fallback_ur' => 'اے ایل ایف گلگت بلتستان',
                'help_text' => 'Main About page title.',
            ],
            [
                'section' => 'About',
                'key' => 'about_p1',
                'label' => 'About paragraph 1',
                'type' => 'textarea',
                'translatable' => true,
                'fallback' => 'The Secretariat of Agriculture, Livestock & Dairy Development, and Fisheries (ALF) provides a single-window digital portal for citizens of Gilgit-Baltistan to discover schemes, submit applications with CNIC-linked identity, and track progress transparently.',
                'fallback_ur' => 'سیکرٹریٹ زراعت، لائیوسٹاک اینڈ ڈیری ڈیولپمنٹ، اور فشریز (اے ایل ایف) گلگت بلتستان کے شہریوں کے لیے ایک ونڈو ڈیجیٹل پورٹل فراہم کرتا ہے تاکہ اسکیمز دریافت کریں، شناختی کارڈ سے منسلک شناخت کے ساتھ درخواست دیں، اور شفاف طریقے سے پیش رفت دیکھیں۔',
                'help_text' => 'First About page body paragraph.',
            ],
            [
                'section' => 'About',
                'key' => 'about_p2',
                'label' => 'About paragraph 2',
                'type' => 'textarea',
                'translatable' => true,
                'fallback' => 'Our mission is to digitize farmer and livestock services, strengthen district-level delivery, and support sustainable food systems across the region’s unique highland economy.',
                'fallback_ur' => 'ہمارا مشن کسان اور لائیوسٹاک خدمات کو ڈیجیٹل بنانا، ضلعی سطح پر فراہمی کو مضبوط کرنا، اور خطے کی منفرد پہاڑی معیشت میں پائیدار غذائی نظام کی حمایت کرنا ہے۔',
                'help_text' => 'Second About page body paragraph.',
            ],
        ];
    }
}
