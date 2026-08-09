<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Scheme;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProposedSchemeSeeder extends Seeder
{
    public function run(): void
    {
        $agriculture = Department::where('slug', 'agriculture')->first();
        $livestock = Department::where('slug', 'livestock')->first();
        $fisheries = Department::where('slug', 'fisheries')->first();

        $proposals = [
            [
                'department' => $fisheries,
                'title' => 'Backyard Mini Fish Farming for Women',
                'budget' => 31600000,
                'display_order' => 1,
                'description' => "A flagship proposal to expand household trout / mini fish farming among women entrepreneurs across Gilgit-Baltistan.\n\nThe package supports construction of backyard units, starter fingerlings, basic feed, and hands-on training so women can generate supplemental income while strengthening local protein supply.\n\nEstimated envelope: PKR 31.6 million.",
                'eligibility_criteria' => "Women applicants who are bona fide residents of Gilgit-Baltistan with a valid CNIC.\nPreference for households with suitable water access and space for a mini unit.\nOne application per household.",
                'terms_conditions' => 'Support is subject to CM / Cabinet approval and available budget. Beneficiaries must follow department technical guidelines and allow monitoring visits.',
            ],
            [
                'department' => $fisheries,
                'title' => 'Soft Loan Scheme for Private Fish Farmers',
                'budget' => 50000000,
                'display_order' => 2,
                'description' => "Proposed soft-loan facility to de-risk private capital for trout farm expansion, hatchery upgrades, and cold-chain / marketing improvements.\n\nThe scheme aims to raise productivity of existing private farms and attract youth into commercial aquaculture through affordable financing linked to Fisheries Department technical appraisal.\n\nEstimated envelope: PKR 50 million.",
                'eligibility_criteria' => "Registered or registerable private fish farmers in Gilgit-Baltistan.\nValid CNIC and business / farm details.\nWillingness to accept technical appraisal and repayment schedule as notified.",
                'terms_conditions' => 'Loan terms, mark-up (if any), and security requirements will be notified after approval. Misuse of funds may lead to recovery action and blacklisting.',
            ],
            [
                'department' => $fisheries,
                'title' => 'Trout Enhancement via Cost-Sharing Partnership',
                'budget' => 90000000,
                'display_order' => 3,
                'description' => "Cost-sharing partnership to enhance trout production through improved inputs, raceway / pond upgrades, and quality fingerling supply.\n\nPublic contribution will match farmer investment as notified, targeting higher annual production and export-ready quality from GB trout clusters.\n\nEstimated envelope: PKR 90 million.",
                'eligibility_criteria' => "Active trout farmers in Gilgit-Baltistan with demonstrable production capacity.\nValid CNIC and farm location details.\nAbility to meet the farmer cost-share portion.",
                'terms_conditions' => 'Cost-share ratios and eligible items will be notified after scheme approval. False declarations will result in rejection and recovery of any disbursed support.',
            ],
            [
                'department' => $agriculture,
                'title' => 'Farmer Trainings & Field Days',
                'budget' => 5000000,
                'display_order' => 4,
                'description' => "Proposed capacity-building programme of seasonal farmer trainings, field days, and agri exhibitions across districts.\n\nFocus areas include climate-smart practices, orchard management, tunnel farming, and quality seed usage so farmers can adopt improved techniques before the next cropping cycle.\n\nEstimated envelope: PKR 5 million.",
                'eligibility_criteria' => 'Bona fide farmers of Gilgit-Baltistan. District Agriculture offices will nominate / register participants for each event.',
                'terms_conditions' => 'Participation is subject to seat availability and departmental schedules after scheme approval.',
            ],
            [
                'department' => $livestock,
                'title' => 'High-Altitude Yak Value-Chain Development',
                'budget' => 25000000,
                'display_order' => 5,
                'description' => "Proposed package for high-altitude yak / zou communities covering fodder support, basic animal health coverage, and market linkage for dairy and fibre products.\n\nIntended to stabilise livelihoods in remote valleys while documenting livestock data for future targeted schemes.\n\nEstimated envelope: PKR 25 million.",
                'eligibility_criteria' => 'Yak / zou herders who are residents of designated high-altitude valleys of Gilgit-Baltistan with a valid CNIC.',
                'terms_conditions' => 'Benefits will be district-notified after approval. Duplicate claims and false livestock counts will lead to disqualification.',
            ],
        ];

        foreach ($proposals as $item) {
            if (! $item['department']) {
                continue;
            }

            $slug = Str::slug($item['title']);

            Scheme::updateOrCreate(
                ['slug' => $slug],
                [
                    'department_id' => $item['department']->id,
                    'title' => $item['title'],
                    'type' => 'proposed',
                    'scope' => 'gb',
                    'budget' => $item['budget'],
                    'start_date' => null,
                    'last_date' => null,
                    'status' => 'active',
                    'description' => $item['description'],
                    'eligibility_criteria' => $item['eligibility_criteria'],
                    'terms_conditions' => $item['terms_conditions'],
                    'is_featured' => true,
                    'display_order' => $item['display_order'],
                ]
            );
        }
    }
}
