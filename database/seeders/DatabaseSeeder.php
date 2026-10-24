<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\DepartmentStat;
use App\Models\District;
use App\Models\Form;
use App\Models\FormDocument;
use App\Models\FormField;
use App\Models\Scheme;
use App\Models\User;
use App\Support\CoreFormFields;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            'Super Admin',
            'Secretary',
            'Director Agriculture',
            'Director Livestock',
            'Director Fisheries',
            'District Officer',
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $districts = [
            ['name' => 'Gilgit', 'code' => 'GLT'],
            ['name' => 'Hunza', 'code' => 'HNZ'],
            ['name' => 'Nagar', 'code' => 'NGR'],
            ['name' => 'Skardu', 'code' => 'SKD'],
            ['name' => 'Ghanche', 'code' => 'GHC'],
            ['name' => 'Shigar', 'code' => 'SHG'],
            ['name' => 'Kharmang', 'code' => 'KHM'],
            ['name' => 'Astore', 'code' => 'AST'],
            ['name' => 'Diamer', 'code' => 'DMR'],
            ['name' => 'Ghizer', 'code' => 'GHZ'],
        ];

        foreach ($districts as $d) {
            District::firstOrCreate(['code' => $d['code']], $d);
        }

        $agriculture = Department::updateOrCreate(['slug' => 'agriculture'], [
            'name' => 'Agriculture',
            'vision' => 'A productive, climate-resilient agriculture sector driving food security and prosperity across Gilgit-Baltistan.',
            'mission' => 'Deliver quality seed, orchard development, tunnel farming, and farmer capacity building through transparent digital schemes.',
            'objectives' => "Distribute quality wheat, maize and hybrid vegetable seed on cost-sharing basis.\nPromote plastic tunnel and kitchen gardening.\nEstablish model orchards and fruit plant nurseries.\nConduct farmer trainings, field days and agri exhibitions.",
            'leadership_info' => 'Directorate of Agriculture, Government of Gilgit-Baltistan.',
            'icon' => 'fa-solid fa-leaf',
            'is_active' => true,
            'display_order' => 1,
        ]);

        $livestock = Department::updateOrCreate(['slug' => 'livestock'], [
            'name' => 'Livestock & Dairy Development',
            'vision' => 'Modern, digitized livestock and dairy value chains supporting private investment and youth employment in GB.',
            'mission' => 'Provide a transparent, time-bound online portal for dairy farm, breeding, yak fodder and animal allocation applications.',
            'objectives' => "Digitise livestock farmers' data.\nPromote private dairy farms and milk sale centres.\nStrengthen breeding services and high-altitude yak development.\nOffer livestock management training for youth.",
            'leadership_info' => 'Directorate of Livestock, Dairy Development & Poultry Production, Gilgit-Baltistan.',
            'icon' => 'fa-solid fa-bolt',
            'is_active' => true,
            'display_order' => 2,
        ]);

        $fisheries = Department::updateOrCreate(['slug' => 'fisheries'], [
            'name' => 'Fisheries',
            'vision' => 'Building GB\'s trout economy through community-based farming, youth & women inclusion, and sustainable production.',
            'mission' => 'De-risk private capital via soft loans and cost-sharing while raising productivity of 450+ trout farms.',
            'objectives' => "Backyard mini fish farming for women.\nSoft loan scheme for private fish farmers.\nTrout enhancement through cost-sharing partnerships.\nDigital farm registry and monitoring.",
            'leadership_info' => 'Fisheries Department, Government of Gilgit-Baltistan.',
            'icon' => 'fa-solid fa-droplet',
            'is_active' => true,
            'display_order' => 3,
        ]);

        $this->seedStats($agriculture, [
            ['Total Farmers Supported', '12,500', 'fa-solid fa-users'],
            ['Tunnels Installed', '850', 'fa-solid fa-house'],
            ['Fruit Plants Distributed', '220,000', 'fa-solid fa-landmark'],
            ['Seed Distributed (MT)', '1,450', 'fa-solid fa-chart-simple'],
        ]);

        $this->seedStats($livestock, [
            ['Dairy Farms Registered', '320', 'fa-solid fa-briefcase'],
            ['Yaks Covered (Fodder Incentive)', '2,190', 'fa-solid fa-bolt'],
            ['Breeding Bulls Allocated', '145', 'fa-solid fa-medal'],
            ['Diploma Trainees', '90', 'fa-solid fa-graduation-cap'],
        ]);

        $this->seedStats($fisheries, [
            ['Registered Trout Farms', '450+', 'fa-solid fa-location-dot'],
            ['Annual Trout Production (Tonnes)', '600', 'fa-solid fa-chart-column'],
            ['Fishing Licenses / Year', '5,000-6,000', 'fa-solid fa-ticket'],
            ['Proposed Investment (PKR M)', '171.6', 'fa-solid fa-money-bill'],
        ]);

        $agriSchemes = [
            ['Distribution of Wheat Seed (Cost Sharing)', 'running', 'district', 25000000],
            ['Distribution of Maize Seed (Cost Sharing)', 'running', 'district', 18000000],
            ['Hybrid Vegetable Seed (Cost Sharing)', 'running', 'district', 22000000],
            ['Plastic Tunnels (Cost Sharing)', 'running', 'district', 35000000],
            ['Vegetable Seed for Kitchen Gardening', 'running', 'district', 8000000],
            ['Fruit Plants Distribution (Green Pakistan)', 'running', 'district', 40000000],
            ['Establishment of Model Orchards', 'running', 'district', 15000000],
            ['Seed Dealer Provisional Permission', 'running', 'district', null],
            ['Farmer Trainings & Field Days', 'proposed', 'gb', 5000000],
        ];

        foreach ($agriSchemes as [$title, $type, $scope, $budget]) {
            $scheme = $this->createScheme($agriculture, $title, $type, $scope, $budget);
            $this->attachDistrictQuotas($scheme);
            if ($type === 'running') {
                $form = $this->createPublishedForm($scheme, $title.' Application');
                $this->addAgriCustomFields($form, $title);
            }
        }

        $livestockSchemes = [
            ['Application for Dairy Farm (Promotion of Dairy Activities)', 'running', 'gb', 50000000],
            ['Application for Milk Sale Center', 'running', 'gb', 15000000],
            ['Feed/Fodder Incentive for Yak / Zou Farmers', 'running', 'district', 21410000],
            ['Application for Cow / Heifer (Block Allocation)', 'running', 'district', 30000000],
            ['Application for Breeding Bull', 'running', 'district', 20000000],
            ['Application for Goat (Block Allocation)', 'running', 'district', 12000000],
            ['Application for Sheep (Block Allocation)', 'running', 'district', 12000000],
            ['Application for Poultry Birds (Block Allocation)', 'running', 'district', 10000000],
            ['Dairy Farm Registration', 'running', 'gb', null],
            ['01-Year Livestock Management Course', 'running', 'gb', 8000000],
        ];

        foreach ($livestockSchemes as [$title, $type, $scope, $budget]) {
            $scheme = $this->createScheme($livestock, $title, $type, $scope, $budget);
            $this->attachDistrictQuotas($scheme);
            $form = $this->createPublishedForm($scheme, $title);
            $this->addLivestockCustomFields($form, $title);
        }

        $fishSchemes = [
            ['Backyard Mini Fish Farming for Women', 'proposed', 'gb', 31600000],
            ['Soft Loan Scheme for Private Fish Farmers', 'proposed', 'gb', 50000000],
            ['Trout Enhancement via Cost-Sharing Partnership', 'proposed', 'gb', 90000000],
            ['Trout Farm Registration & Renewal', 'running', 'gb', null],
        ];

        foreach ($fishSchemes as [$title, $type, $scope, $budget]) {
            $scheme = $this->createScheme($fisheries, $title, $type, $scope, $budget);
            if ($scope === 'district') {
                $this->attachDistrictQuotas($scheme);
            }
            if ($type === 'running') {
                $form = $this->createPublishedForm($scheme, $title);
                $this->addFisheriesCustomFields($form);
            }
        }

        $this->call(ProposedSchemeSeeder::class);
        $this->call(MediaSeeder::class);
        $this->call(UrduContentSeeder::class);

        $admin = User::updateOrCreate(['email' => 'admin@alf.gb.gov.pk'], [
            'name' => 'Super Admin',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $admin->syncRoles(['Super Admin']);

        $secretary = User::updateOrCreate(['email' => 'secretary@alf.gb.gov.pk'], [
            'name' => 'Secretary ALF',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $secretary->syncRoles(['Secretary']);

        User::updateOrCreate(['email' => 'director.agri@alf.gb.gov.pk'], [
            'name' => 'Director Agriculture',
            'password' => Hash::make('password'),
            'department_id' => $agriculture->id,
            'is_active' => true,
        ])->syncRoles(['Director Agriculture']);

        User::updateOrCreate(['email' => 'director.livestock@alf.gb.gov.pk'], [
            'name' => 'Director Livestock',
            'password' => Hash::make('password'),
            'department_id' => $livestock->id,
            'is_active' => true,
        ])->syncRoles(['Director Livestock']);

        User::updateOrCreate(['email' => 'director.fisheries@alf.gb.gov.pk'], [
            'name' => 'Director Fisheries',
            'password' => Hash::make('password'),
            'department_id' => $fisheries->id,
            'is_active' => true,
        ])->syncRoles(['Director Fisheries']);

        $gilgit = District::where('code', 'GLT')->first();
        User::updateOrCreate(['email' => 'dd.gilgit@alf.gb.gov.pk'], [
            'name' => 'District Officer Gilgit',
            'password' => Hash::make('password'),
            'district_id' => $gilgit?->id,
            'department_id' => $agriculture->id,
            'is_active' => true,
        ])->syncRoles(['District Officer']);

        $this->call(DemoApplicationSeeder::class);
    }

    private function seedStats(Department $department, array $stats): void
    {
        foreach ($stats as $i => [$label, $value, $icon]) {
            DepartmentStat::updateOrCreate(
                ['department_id' => $department->id, 'stat_label' => $label],
                [
                    'stat_value' => $value,
                    'icon' => $icon,
                    'source' => 'manual',
                    'display_order' => $i + 1,
                    'is_active' => true,
                ]
            );
        }
    }

    private function createScheme(Department $department, string $title, string $type, string $scope, ?float $budget): Scheme
    {
        $slug = Str::slug($title);

        return Scheme::updateOrCreate(['slug' => $slug], [
            'department_id' => $department->id,
            'title' => $title,
            'type' => $type,
            'scope' => $scope,
            'budget' => $budget,
            'start_date' => $type === 'proposed' ? null : now()->subDays(10)->toDateString(),
            'last_date' => $type === 'proposed' ? null : now()->addMonths(3)->toDateString(),
            'status' => 'active',
            'description' => $title.' under the Secretariat of Agriculture, Livestock & Dairy Development, and Fisheries, Gilgit-Baltistan.',
            'eligibility_criteria' => 'Applicant must be a bona fide resident of Gilgit-Baltistan with a valid CNIC. Scheme-specific eligibility applies.',
            'terms_conditions' => 'False information may lead to rejection and blacklisting. Cost-sharing schemes require farmer contribution as notified.',
            'is_featured' => true,
            'display_order' => 0,
        ]);
    }

    private function attachDistrictQuotas(Scheme $scheme): void
    {
        if ($scheme->scope !== 'district') {
            return;
        }

        foreach (District::all() as $district) {
            $scheme->districts()->syncWithoutDetaching([
                $district->id => ['quota' => 50],
            ]);
        }
    }

    private function createPublishedForm(Scheme $scheme, string $title): Form
    {
        $form = Form::updateOrCreate(
            ['scheme_id' => $scheme->id, 'version' => 1],
            ['title' => $title, 'is_published' => true]
        );

        Form::where('scheme_id', $scheme->id)->where('id', '!=', $form->id)->update(['is_published' => false]);

        foreach (CoreFormFields::definitions() as $field) {
            FormField::updateOrCreate(
                ['form_id' => $form->id, 'field_key' => $field['field_key']],
                array_merge($field, ['form_id' => $form->id])
            );
        }

        FormDocument::updateOrCreate(
            ['form_id' => $form->id, 'document_name' => 'Copy of CNIC'],
            [
                'is_mandatory' => true,
                'allowed_extensions' => 'pdf,jpg,jpeg,png',
                'max_size_mb' => 5,
                'display_order' => 1,
            ]
        );

        return $form->fresh(['fields', 'documents']);
    }

    private function addAgriCustomFields(Form $form, string $title): void
    {
        $order = 10;
        $fields = [];

        if (str_contains($title, 'Wheat')) {
            $fields[] = ['land_kanals', 'Land Available (Kanals)', 'number', true];
            $fields[] = ['previous_cultivation', 'Wheat Cultivated Area Last Year (Kanals)', 'number', false];
            $fields[] = ['bags_required', 'Number of 50kg Seed Bags Required', 'number', true];
            $fields[] = ['road_access', 'Road Access to Land', 'radio', true, ['Yes', 'No']];
            $fields[] = ['water_available', 'Availability of Water', 'radio', true, ['Yes', 'No']];
        } elseif (str_contains($title, 'Vegetable') || str_contains($title, 'Maize') || str_contains($title, 'Kitchen')) {
            $fields[] = ['land_kanals', 'Land Available (Kanals)', 'number', true];
            $fields[] = ['existing_tunnels', 'Existing Number of Tunnels', 'number', false];
            $fields[] = ['road_access', 'Road Access to Land', 'radio', true, ['Yes', 'No']];
            $fields[] = ['water_available', 'Availability of Water', 'radio', true, ['Yes', 'No']];
        }

        if (str_contains($title, 'Fruit')) {
            $fields[] = ['land_kanals', 'Land Available for Fruit Plants (Kanals)', 'number', true];
            $fields[] = ['previous_orchard', 'Previous Orchard Area (Kanals)', 'number', false];
            $fields[] = ['road_access', 'Road Access to Land', 'radio', true, ['Yes', 'No']];
            $fields[] = ['water_available', 'Water Available', 'radio', true, ['Yes', 'No']];
            $fields[] = ['species', 'Species Required', 'checkbox', true, ['Cherry', 'Apple', 'Pomegranate', 'Apricot', 'Almond', 'Grapes', 'Walnut', 'Pear', 'Persimmon', 'Plum', 'Peach', 'Fig', 'Mulberry', 'Lemon']];
        }

        if (str_contains($title, 'Tunnel')) {
            $fields[] = ['cultivated_land', 'Total Area of Cultivated Land', 'number', true];
            $fields[] = ['road_access', 'Road Access to Land', 'radio', true, ['Yes', 'No']];
            $fields[] = ['water_available', 'Water Available', 'radio', true, ['Yes', 'No']];
            $fields[] = ['tunnel_experience', 'Experience in Tunnel Vegetable Production', 'textarea', false];
            $fields[] = ['cost_share_ready', 'Ready to share in cost?', 'switch', true, ['Yes', 'No']];
        }

        if (str_contains($title, 'Seed Dealer')) {
            $fields[] = ['education', 'Educational Qualification', 'text', true];
            $fields[] = ['fscrd_certificate', 'FSC&RD Training Certificate No. & Date', 'text', true];
            $fields[] = ['shop_location', 'Location of Shop / Sale Point', 'textarea', true];
            $fields[] = ['crop_seeds', 'Name of Crop Seed Dealing', 'text', true];
            $fields[] = ['companies', 'Name of Companies of Dealership', 'textarea', false];

            FormDocument::updateOrCreate(
                ['form_id' => $form->id, 'document_name' => 'Copy of Matric Certificate'],
                ['is_mandatory' => true, 'allowed_extensions' => 'pdf,jpg,jpeg,png', 'max_size_mb' => 5, 'display_order' => 2]
            );
            FormDocument::updateOrCreate(
                ['form_id' => $form->id, 'document_name' => 'FSC&RD Training Certificate'],
                ['is_mandatory' => true, 'allowed_extensions' => 'pdf,jpg,jpeg,png', 'max_size_mb' => 5, 'display_order' => 3]
            );
            FormDocument::updateOrCreate(
                ['form_id' => $form->id, 'document_name' => 'Ownership / Lease Record of Shop'],
                ['is_mandatory' => true, 'allowed_extensions' => 'pdf,jpg,jpeg,png', 'max_size_mb' => 5, 'display_order' => 4]
            );
            FormDocument::updateOrCreate(
                ['form_id' => $form->id, 'document_name' => 'Certificate / Letter of Dealership'],
                ['is_mandatory' => false, 'allowed_extensions' => 'pdf,jpg,jpeg,png', 'max_size_mb' => 5, 'display_order' => 5]
            );
        }

        foreach ($fields as $i => $f) {
            FormField::updateOrCreate(
                ['form_id' => $form->id, 'field_key' => $f[0]],
                [
                    'field_label' => $f[1],
                    'field_type' => $f[2],
                    'is_core' => false,
                    'is_mandatory' => $f[3],
                    'options_json' => $f[4] ?? null,
                    'display_order' => $order + $i,
                ]
            );
        }
    }

    private function addLivestockCustomFields(Form $form, string $title): void
    {
        $order = 10;
        $fields = [
            ['gender', 'Gender', 'select', true, ['Male', 'Female', 'Other']],
        ];

        if (str_contains($title, 'Dairy Farm') || str_contains($title, 'Registration')) {
            $fields = array_merge($fields, [
                ['business_name', 'Business Name', 'text', true],
                ['form_c', 'Form-C Business License No.', 'text', true],
                ['num_cows', 'Number of Cows', 'number', true],
                ['num_bulls', 'Number of Bulls', 'number', false],
                ['num_heifers', 'Number of Pregnant Heifers', 'number', false],
                ['num_sheds', 'Number of Sheds', 'number', false],
                ['shed_type', 'Shed Type', 'text', false],
                ['water_point', 'Water Point Available', 'switch', false, ['Yes', 'No']],
                ['milking_machine', 'Milking Machine', 'switch', false, ['Yes', 'No']],
                ['electricity', 'Electricity Available', 'switch', false, ['Yes', 'No']],
                ['vet_doctor', 'Vet Doctor Available', 'switch', false, ['Yes', 'No']],
                ['available_land', 'Available Land', 'text', false],
                ['distance_market', 'Distance to Nearest Market', 'text', false],
            ]);
            FormDocument::updateOrCreate(
                ['form_id' => $form->id, 'document_name' => 'Form-C Business License'],
                ['is_mandatory' => true, 'allowed_extensions' => 'pdf,jpg,jpeg,png', 'max_size_mb' => 5, 'display_order' => 2]
            );
            FormDocument::updateOrCreate(
                ['form_id' => $form->id, 'document_name' => 'Farm Photo'],
                ['is_mandatory' => true, 'allowed_extensions' => 'jpg,jpeg,png', 'max_size_mb' => 5, 'display_order' => 3]
            );
        }

        if (str_contains($title, 'Milk Sale')) {
            $fields = array_merge($fields, [
                ['business_name', 'Business Name', 'text', true],
                ['form_c', 'Form-C Business License No.', 'text', true],
                ['shop_location', 'Shop Location', 'textarea', true],
                ['deep_freezer', 'Deep Freezer Available', 'switch', false, ['Yes', 'No']],
                ['packing_machine', 'Milk Packing Machine', 'switch', false, ['Yes', 'No']],
                ['generator', 'Generator Available', 'switch', false, ['Yes', 'No']],
            ]);
            FormDocument::updateOrCreate(
                ['form_id' => $form->id, 'document_name' => 'Shop Photo'],
                ['is_mandatory' => true, 'allowed_extensions' => 'jpg,jpeg,png', 'max_size_mb' => 5, 'display_order' => 2]
            );
        }

        if (str_contains($title, 'Yak')) {
            $fields = array_merge($fields, [
                ['num_yaks', 'Total Number of Yaks / Zou', 'number', true],
                ['num_calves', 'Number of Calves', 'number', true],
                ['bank_name', 'Bank Name', 'text', true],
                ['account_no', 'Account Number', 'text', true],
                ['shed_for_calves', 'Shed for Calves', 'text', false],
                ['agri_land', 'Agri Land', 'text', false],
            ]);
        }

        if (str_contains($title, 'Breeding Bull')) {
            $fields = array_merge($fields, [
                ['domicile', 'Domicile District', 'text', true],
                ['breeding_farm_location', 'Location of Breeding Farm', 'textarea', true],
                ['experience_years', 'Previous Experience (Years)', 'number', true],
                ['experience_record', 'Record of Previous Experience', 'textarea', false],
            ]);
        }

        if (str_contains($title, 'Cow') || str_contains($title, 'Goat') || str_contains($title, 'Sheep') || str_contains($title, 'Poultry')) {
            $fields[] = ['domicile', 'Domicile District', 'text', true];
        }

        if (str_contains($title, 'Livestock Management')) {
            $fields = array_merge($fields, [
                ['domicile', 'Domicile', 'text', true],
                ['ssc_science', 'SSC Science with Biology', 'switch', true, ['Yes', 'No']],
            ]);
            FormDocument::updateOrCreate(
                ['form_id' => $form->id, 'document_name' => 'SSC Certificate'],
                ['is_mandatory' => true, 'allowed_extensions' => 'pdf,jpg,jpeg,png', 'max_size_mb' => 5, 'display_order' => 2]
            );
            FormDocument::updateOrCreate(
                ['form_id' => $form->id, 'document_name' => 'Domicile Certificate'],
                ['is_mandatory' => true, 'allowed_extensions' => 'pdf,jpg,jpeg,png', 'max_size_mb' => 5, 'display_order' => 3]
            );
        }

        foreach ($fields as $i => $f) {
            FormField::updateOrCreate(
                ['form_id' => $form->id, 'field_key' => $f[0]],
                [
                    'field_label' => $f[1],
                    'field_type' => $f[2],
                    'is_core' => false,
                    'is_mandatory' => $f[3],
                    'options_json' => $f[4] ?? null,
                    'display_order' => $order + $i,
                ]
            );
        }
    }

    private function addFisheriesCustomFields(Form $form): void
    {
        $fields = [
            ['gps_coordinates', 'GPS Coordinates', 'text', false],
            ['water_source', 'Water Source & Type', 'select', true, ['Stream', 'Spring', 'Channel-fed', 'Other']],
            ['num_ponds', 'Number of Ponds / Raceways', 'number', true],
            ['pond_size', 'Size of Ponds / Raceways', 'text', false],
            ['species_farmed', 'Species Farmed', 'text', true],
            ['fingerling_source', 'Fingerling Source', 'text', false],
            ['stocking_date', 'Stocking Date', 'text', false],
            ['production_previous', 'Previous Cycle Production (kg)', 'number', false],
        ];

        foreach ($fields as $i => $f) {
            FormField::updateOrCreate(
                ['form_id' => $form->id, 'field_key' => $f[0]],
                [
                    'field_label' => $f[1],
                    'field_type' => $f[2],
                    'is_core' => false,
                    'is_mandatory' => $f[3],
                    'options_json' => $f[4] ?? null,
                    'display_order' => 10 + $i,
                ]
            );
        }

        FormDocument::updateOrCreate(
            ['form_id' => $form->id, 'document_name' => 'Proof of Land or Water-use Right'],
            ['is_mandatory' => true, 'allowed_extensions' => 'pdf,jpg,jpeg,png', 'max_size_mb' => 5, 'display_order' => 2]
        );
    }
}
