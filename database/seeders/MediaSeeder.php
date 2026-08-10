<?php

namespace Database\Seeders;

use App\Models\CarouselSlide;
use App\Models\SiteSetting;
use Illuminate\Database\Seeder;

class MediaSeeder extends Seeder
{
    public function run(): void
    {
        foreach (SiteSetting::mediaDefinitions() as $def) {
            SiteSetting::updateOrCreate(
                ['key' => $def['key']],
                [
                    'label' => $def['label'],
                    'group' => 'media',
                    'fallback' => $def['fallback'],
                    'help_text' => $def['help_text'],
                ]
            );
        }

        foreach (SiteSetting::contentDefinitions() as $def) {
            $existing = SiteSetting::where('key', $def['key'])->first();
            if ($existing) {
                $existing->update([
                    'label' => $def['label'],
                    'group' => 'content',
                    'fallback' => $def['fallback'],
                    'fallback_ur' => $def['fallback_ur'] ?? null,
                    'help_text' => $def['help_text'],
                ]);
            } else {
                SiteSetting::create([
                    'key' => $def['key'],
                    'label' => $def['label'],
                    'group' => 'content',
                    'fallback' => $def['fallback'],
                    'fallback_ur' => $def['fallback_ur'] ?? null,
                    'help_text' => $def['help_text'],
                    'value' => $def['fallback'] !== '' ? $def['fallback'] : null,
                    'value_ur' => $def['fallback_ur'] ?: null,
                ]);
            }
        }

        $slides = [
            [
                'title' => 'ALF Gilgit-Baltistan',
                'title_ur' => 'اے ایل ایف گلگت بلتستان',
                'subtitle' => 'Secretariat of Agriculture, Livestock & Dairy Development, and Fisheries — Government of Gilgit-Baltistan. Apply online for schemes, track applications, and access department services in one place.',
                'subtitle_ur' => 'سیکرٹریٹ زراعت، لائیوسٹاک اینڈ ڈیری ڈیولپمنٹ، اور فشریز — حکومت گلگت بلتستان۔ اسکیمز کے لیے آن لائن درخواست دیں، درخواستیں ٹریک کریں، اور محکمہ جاتی خدمات ایک جگہ حاصل کریں۔',
                'image' => 'frontend/img/bg-img/1.jpg',
                'button_label' => 'Apply for Schemes',
                'button_label_ur' => 'اسکیمز کے لیے درخواست دیں',
                'button_url' => '/schemes?type=running',
                'button2_label' => 'CM Proposed Schemes',
                'button2_label_ur' => 'وزیراعلیٰ کی مجوزہ اسکیمز',
                'button2_url' => '/#cm-proposed-schemes',
                'display_order' => 1,
            ],
            [
                'title' => 'Transparent Digital Services',
                'title_ur' => 'شفاف ڈیجیٹل خدمات',
                'subtitle' => 'From seed distribution and dairy farm registration to trout farm support — submit applications with CNIC verification and monitor status in real time.',
                'subtitle_ur' => 'بیج کی تقسیم اور ڈیری فارم رجسٹریشن سے لے کر ٹراؤٹ فارم سپورٹ تک — شناختی کارڈ کی تصدیق کے ساتھ درخواست جمع کروائیں اور صورتحال حقیقی وقت میں دیکھیں۔',
                'image' => 'frontend/img/bg-img/7.jpg',
                'button_label' => 'Learn More',
                'button_label_ur' => 'مزید جانیں',
                'button_url' => '/about',
                'button2_label' => 'Track Application',
                'button2_label_ur' => 'درخواست ٹریک کریں',
                'button2_url' => '/track',
                'display_order' => 2,
            ],
        ];

        foreach ($slides as $slide) {
            CarouselSlide::updateOrCreate(
                ['title' => $slide['title'], 'display_order' => $slide['display_order']],
                array_merge($slide, ['is_active' => true])
            );
        }

        SiteSetting::flushCache();
    }
}
