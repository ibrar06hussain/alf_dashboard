<?php

namespace Database\Seeders;

use App\Models\CarouselSlide;
use App\Models\Department;
use App\Models\DepartmentStat;
use App\Models\District;
use App\Models\Scheme;
use Illuminate\Database\Seeder;

class UrduContentSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedDistricts();
        $this->seedDepartments();
        $this->seedSchemes();
        $this->seedCarousel();
        $this->seedStats();
    }

    private function seedDistricts(): void
    {
        $map = [
            'GLT' => 'گلگت',
            'HNZ' => 'ہنزہ',
            'NGR' => 'نگر',
            'SKD' => 'سکردو',
            'GHC' => 'گانچھے',
            'SHG' => 'شگر',
            'KHM' => 'کھرمنگ',
            'AST' => 'استور',
            'DMR' => 'دیامر',
            'GHZ' => 'غذر',
        ];

        foreach ($map as $code => $nameUr) {
            District::where('code', $code)->update(['name_ur' => $nameUr]);
        }
    }

    private function seedDepartments(): void
    {
        $data = [
            'agriculture' => [
                'name_ur' => 'زراعت',
                'vision_ur' => 'ایک پیداواری اور موسمیاتی طور پر مضبوط زراعتی شعبہ جو گلگت بلتستان میں غذائی تحفظ اور خوشحالی کو فروغ دے۔',
                'mission_ur' => 'معیاری بیج، باغات کی ترقی، سرنگ فارمنگ اور کسانوں کی صلاحیت سازی شفاف ڈیجیٹل اسکیمز کے ذریعے فراہم کرنا۔',
                'objectives_ur' => "گندم، مکئی اور ہائبرڈ سبزیوں کے بیج لاگت کی شراکت پر تقسیم۔\nپلاسٹک سرنگیں اور باورچی خانے کی باغبانی کو فروغ دینا۔\nماڈل باغات اور پودوں کی نرسریاں قائم کرنا۔\nکسانوں کی تربیت، فیلڈ ڈے اور زرعی نمائشیں منعقد کرنا۔",
                'leadership_info_ur' => 'ڈائریکٹوریٹ آف ایگریکلچر، حکومت گلگت بلتستان۔',
            ],
            'livestock' => [
                'name_ur' => 'لائیوسٹاک اینڈ ڈیری ڈیولپمنٹ',
                'vision_ur' => 'جدید اور ڈیجیٹل لائیوسٹاک و ڈیری ویلیو چینز جو گلگت بلتستان میں نجی سرمایہ کاری اور نوجوانوں کے روزگار کی حمایت کریں۔',
                'mission_ur' => 'ڈیری فارم، بریڈنگ، یاک چارہ اور جانوروں کی الاٹمنٹ کے لیے شفاف اور وقت کی پابندی والا آن لائن پورٹل فراہم کرنا۔',
                'objectives_ur' => "لائیوسٹاک کسانوں کے ڈیٹا کو ڈیجیٹل بنانا۔\nنجی ڈیری فارمز اور دودھ فروخت مراکز کو فروغ دینا۔\nبریڈنگ خدمات اور اونچائی والے علاقوں میں یاک ترقی کو مضبوط کرنا۔\nنوجوانوں کے لیے لائیوسٹاک مینجمنٹ تربیت۔",
                'leadership_info_ur' => 'ڈائریکٹوریٹ آف لائیوسٹاک، ڈیری ڈیولپمنٹ اینڈ پولٹری پروڈکشن، گلگت بلتستان۔',
            ],
            'fisheries' => [
                'name_ur' => 'فشریز',
                'vision_ur' => 'کمیونٹی پر مبنی فارمنگ، خواتین و نوجوانوں کی شمولیت اور پائیدار پیداوار کے ذریعے گلگت بلتستان کی ٹراؤٹ معیشت کو مضبوط بنانا۔',
                'mission_ur' => 'نرم قرضوں اور لاگت کی شراکت کے ذریعے نجی سرمایہ کاری کو محفوظ بناتے ہوئے 450 سے زائد ٹراؤٹ فارمز کی پیداوار بڑھانا۔',
                'objectives_ur' => "خواتین کے لیے گھریلو منی فش فارمنگ۔\nنجی فش فارمرز کے لیے نرم قرض اسکیم۔\nلاگت کی شراکت کے ذریعے ٹراؤٹ میں اضافہ۔\nڈیجیٹل فارم رجسٹری اور نگرانی۔",
                'leadership_info_ur' => 'فشریز ڈیپارٹمنٹ، حکومت گلگت بلتستان۔',
            ],
        ];

        foreach ($data as $slug => $fields) {
            Department::where('slug', $slug)->update($fields);
        }
    }

    private function seedSchemes(): void
    {
        $titles = [
            'Distribution of Wheat Seed (Cost Sharing)' => 'گندم کے بیج کی تقسیم (لاگت کی شراکت)',
            'Distribution of Maize Seed (Cost Sharing)' => 'مکئی کے بیج کی تقسیم (لاگت کی شراکت)',
            'Hybrid Vegetable Seed (Cost Sharing)' => 'ہائبرڈ سبزیوں کے بیج (لاگت کی شراکت)',
            'Plastic Tunnels (Cost Sharing)' => 'پلاسٹک سرنگیں (لاگت کی شراکت)',
            'Vegetable Seed for Kitchen Gardening' => 'باورچی خانے کی باغبانی کے لیے سبزیوں کے بیج',
            'Fruit Plants Distribution (Green Pakistan)' => 'پھلوں کے پودوں کی تقسیم (گرین پاکستان)',
            'Establishment of Model Orchards' => 'ماڈل باغات کا قیام',
            'Seed Dealer Provisional Permission' => 'بیج ڈیلر عارضی اجازت',
            'Farmer Trainings & Field Days' => 'کسانوں کی تربیت اور فیلڈ ڈے',
            'Application for Dairy Farm (Promotion of Dairy Activities)' => 'ڈیری فارم کی درخواست (ڈیری سرگرمیوں کا فروغ)',
            'Application for Milk Sale Center' => 'دودھ فروخت مرکز کی درخواست',
            'Feed/Fodder Incentive for Yak / Zou Farmers' => 'یاک / زو کسانوں کے لیے چارہ مراعات',
            'Application for Cow / Heifer (Block Allocation)' => 'گائے / ہیفر کی درخواست (بلاک الاٹمنٹ)',
            'Application for Breeding Bull' => 'بریڈنگ بل کی درخواست',
            'Application for Goat (Block Allocation)' => 'بکری کی درخواست (بلاک الاٹمنٹ)',
            'Application for Sheep (Block Allocation)' => ' بھیڑ کی درخواست (بلاک الاٹمنٹ)',
            'Application for Poultry Birds (Block Allocation)' => 'پولٹری پرندوں کی درخواست (بلاک الاٹمنٹ)',
            'Dairy Farm Registration' => 'ڈیری فارم رجسٹریشن',
            '01-Year Livestock Management Course' => 'ایک سالہ لائیوسٹاک مینجمنٹ کورس',
            'Backyard Mini Fish Farming for Women' => 'خواتین کے لیے گھریلو منی فش فارمنگ',
            'Soft Loan Scheme for Private Fish Farmers' => 'نجی فش فارمرز کے لیے نرم قرض اسکیم',
            'Trout Enhancement via Cost-Sharing Partnership' => 'لاگت کی شراکت کے ذریعے ٹراؤٹ میں اضافہ',
            'Trout Farm Registration & Renewal' => 'ٹراؤٹ فارم رجسٹریشن اور تجدید',
            'High-Altitude Yak Value-Chain Development' => 'اونچائی والے علاقوں میں یاک ویلیو چین کی ترقی',
        ];

        $eligibilityUr = 'درخواست گزار گلگت بلتستان کا اصل رہائشی ہونا چاہیے اور درست قومی شناختی کارڈ رکھتا ہو۔ اسکیم کے مخصوص معیار بھی لاگو ہوتے ہیں۔';
        $termsUr = 'غلط معلومات فراہم کرنے پر درخواست مسترد اور بلیک لسٹ کیا جا سکتا ہے۔ لاگت کی شراکت والی اسکیمز میں کسان کا حصہ نوٹی فائی شدہ شرح کے مطابق ہوگا۔';

        foreach (Scheme::query()->get() as $scheme) {
            $titleUr = $titles[$scheme->title] ?? ($scheme->title_ur ?: null);
            if (! $titleUr) {
                continue;
            }

            $scheme->update([
                'title_ur' => $titleUr,
                'description_ur' => $scheme->description_ur ?: ($titleUr.' — سیکرٹریٹ زراعت، لائیوسٹاک اینڈ ڈیری ڈیولپمنٹ، اور فشریز، گلگت بلتستان کے تحت۔'),
                'eligibility_criteria_ur' => $scheme->eligibility_criteria_ur ?: $eligibilityUr,
                'terms_conditions_ur' => $scheme->terms_conditions_ur ?: $termsUr,
            ]);
        }

        // Richer Urdu copy for CM proposed flagship schemes
        $rich = [
            'Backyard Mini Fish Farming for Women' => [
                'description_ur' => "خواتین کاروباری افراد کے درمیان گھریلو ٹراؤٹ / منی فش فارمنگ کو پھیلانے کی ایک اہم مجوزہ اسکیم۔\n\nاس پیکج میں گھریلو یونٹ کی تعمیر، ابتدائی فنگرلنگز، بنیادی خوراک اور عملی تربیت شامل ہے تاکہ خواتین اضافی آمدنی حاصل کر سکیں اور مقامی پروٹین کی فراہمی مضبوط ہو۔\n\nتخمینی بجٹ: 31.6 ملین روپے۔",
                'eligibility_criteria_ur' => "گلگت بلتستان کی اصل رہائشی خواتین جن کے پاس درست شناختی کارڈ ہو۔\nمناسب پانی اور جگہ والے گھرانوں کو ترجیح۔\nہر گھرانے سے ایک درخواست۔",
                'terms_conditions_ur' => 'مدد وزیراعلیٰ / کابینہ کی منظوری اور دستیاب بجٹ کے تابع ہے۔ مستفیدین محکمہ کی تکنیکی ہدایات پر عمل کریں اور نگرانی کے دوروں کی اجازت دیں۔',
            ],
            'Soft Loan Scheme for Private Fish Farmers' => [
                'description_ur' => "ٹراؤٹ فارم کی توسیع، ہیچری اپ گریڈ اور کولڈ چین / مارکیٹنگ میں بہتری کے لیے نجی سرمایہ کاری کو محفوظ بنانے کی مجوزہ نرم قرض سہولت۔\n\nتخمینی بجٹ: 50 ملین روپے۔",
                'eligibility_criteria_ur' => 'گلگت بلتستان میں رجسٹرڈ یا رجسٹر ہونے والے نجی فش فارمرز۔ درست شناختی کارڈ اور فارم کی تفصیلات۔',
                'terms_conditions_ur' => 'قرض کی شرائط منظوری کے بعد نوٹی فائی کی جائیں گی۔ فنڈز کے غلط استعمال پر وصولی اور بلیک لسٹنگ ہو سکتی ہے۔',
            ],
            'Trout Enhancement via Cost-Sharing Partnership' => [
                'description_ur' => "بہتر ان پٹس، ریس وے / تالاب اپ گریڈ اور معیاری فنگرلنگز کے ذریعے ٹراؤٹ پیداوار بڑھانے کی لاگت کی شراکت والی شراکت داری۔\n\nتخمینی بجٹ: 90 ملین روپے۔",
                'eligibility_criteria_ur' => 'گلگت بلتستان کے فعال ٹراؤٹ فارمرز جن کی پیداوار کی صلاحیت ظاہر ہو۔ درست شناختی کارڈ اور فارم کا مقام۔',
                'terms_conditions_ur' => 'لاگت کی شراکت کی شرحیں منظوری کے بعد نوٹی فائی ہوں گی۔ غلط بیانی پر مستردی اور وصولی ہو سکتی ہے۔',
            ],
            'Farmer Trainings & Field Days' => [
                'description_ur' => "ضلعوں میں موسمی کسان تربیت، فیلڈ ڈے اور زرعی نمائشوں کا مجوزہ پروگرام۔\n\nتخمینی بجٹ: 5 ملین روپے۔",
                'eligibility_criteria_ur' => 'گلگت بلتستان کے اصل کسان۔ ضلعی زراعت دفاتر شرکاء نامزد / رجسٹر کریں گے۔',
                'terms_conditions_ur' => 'شرکت نشستوں کی دستیابی اور محکمہ کے شیڈول کے تابع ہے۔',
            ],
            'High-Altitude Yak Value-Chain Development' => [
                'description_ur' => "اونچائی والے یاک / زو کمیونٹیز کے لیے چارہ، بنیادی جانوروں کی صحت اور مارکیٹ لنکج کا مجوزہ پیکج۔\n\nتخمینی بجٹ: 25 ملین روپے۔",
                'eligibility_criteria_ur' => 'مخصوص اونچائی والی وادیوں کے یاک / زو چرواہے جو گلگت بلتستان کے رہائشی ہوں اور درست شناختی کارڈ رکھتے ہوں۔',
                'terms_conditions_ur' => 'فوائد منظوری کے بعد ضلع نوٹی فائی کرے گا۔ جھوٹے دعووں پر نااہلی ہوگی۔',
            ],
        ];

        foreach ($rich as $title => $fields) {
            Scheme::where('title', $title)->update($fields);
        }
    }

    private function seedCarousel(): void
    {
        CarouselSlide::where('title', 'ALF Gilgit-Baltistan')->update([
            'title_ur' => 'اے ایل ایف گلگت بلتستان',
            'subtitle_ur' => 'سیکرٹریٹ زراعت، لائیوسٹاک اینڈ ڈیری ڈیولپمنٹ، اور فشریز — حکومت گلگت بلتستان۔ اسکیمز کے لیے آن لائن درخواست دیں، درخواستیں ٹریک کریں، اور محکمہ جاتی خدمات ایک جگہ حاصل کریں۔',
            'button_label_ur' => 'اسکیمز کے لیے درخواست دیں',
            'button2_label_ur' => 'وزیراعلیٰ کی مجوزہ اسکیمز',
        ]);

        CarouselSlide::where('title', 'Transparent Digital Services')->update([
            'title_ur' => 'شفاف ڈیجیٹل خدمات',
            'subtitle_ur' => 'بیج کی تقسیم اور ڈیری فارم رجسٹریشن سے لے کر ٹراؤٹ فارم سپورٹ تک — شناختی کارڈ کی تصدیق کے ساتھ درخواست جمع کروائیں اور صورتحال حقیقی وقت میں دیکھیں۔',
            'button_label_ur' => 'مزید جانیں',
            'button2_label_ur' => 'درخواست ٹریک کریں',
        ]);
    }

    private function seedStats(): void
    {
        $map = [
            'Total Farmers Supported' => 'کل معاونت یافتہ کسان',
            'Tunnels Installed' => 'نصب شدہ سرنگیں',
            'Fruit Plants Distributed' => 'تقسیم شدہ پھلوں کے پودے',
            'Seed Distributed (MT)' => 'تقسیم شدہ بیج (میٹرک ٹن)',
            'Dairy Farms Registered' => 'رجسٹرڈ ڈیری فارمز',
            'Yaks Covered (Fodder Incentive)' => 'یاک (چارہ مراعات)',
            'Breeding Bulls Allocated' => 'الاٹ شدہ بریڈنگ بل',
            'Diploma Trainees' => 'ڈپلومہ ٹرینیز',
            'Registered Trout Farms' => 'رجسٹرڈ ٹراؤٹ فارمز',
            'Annual Trout Production (Tonnes)' => 'سالانہ ٹراؤٹ پیداوار (ٹن)',
            'Fishing Licenses / Year' => 'سالانہ فشنگ لائسنس',
            'Proposed Investment (PKR M)' => 'مجوزہ سرمایہ کاری (ملین روپے)',
        ];

        foreach ($map as $en => $ur) {
            DepartmentStat::where('stat_label', $en)->update(['stat_label_ur' => $ur]);
        }
    }
}
