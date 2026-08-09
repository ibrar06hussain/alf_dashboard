<?php

namespace Database\Seeders;

use App\Models\Applicant;
use App\Models\Application;
use App\Models\ApplicationFieldValue;
use App\Models\ApplicationStatusLog;
use App\Models\Department;
use App\Models\DepartmentStat;
use App\Models\District;
use App\Models\Scheme;
use App\Services\StatsAggregationService;
use Illuminate\Database\Seeder;

class DemoApplicationSeeder extends Seeder
{
    public function run(): void
    {
        $districts = District::orderBy('id')->get();
        $schemes = collect();
        foreach (Department::orderBy('display_order')->get() as $department) {
            $schemes = $schemes->merge(
                Scheme::query()
                    ->where('department_id', $department->id)
                    ->where('type', 'running')
                    ->where('status', 'active')
                    ->with('publishedForm.fields')
                    ->take(2)
                    ->get()
            );
        }

        if ($schemes->isEmpty() || $districts->isEmpty()) {
            return;
        }

        $statuses = ['pending', 'pending', 'district_verified', 'directorate_approved', 'disbursed', 'rejected'];
        $i = 0;

        foreach ($schemes as $scheme) {
            foreach ($districts->take(5) as $district) {
                $i++;
                $cnic = str_pad((string) (1000000000000 + $i), 13, '0', STR_PAD_LEFT);

                $applicant = Applicant::updateOrCreate(
                    ['cnic' => $cnic],
                    [
                        'full_name' => 'Demo Farmer '.$i,
                        'father_name' => 'Demo Father '.$i,
                        'phone' => '0300'.str_pad((string) $i, 7, '0', STR_PAD_LEFT),
                        'phone_verified' => true,
                        'district_id' => $district->id,
                        'tehsil' => $district->name.' Tehsil',
                        'land_location' => 'Demo location, '.$district->name,
                        'gender' => $i % 2 === 0 ? 'Female' : 'Male',
                    ]
                );

                $applicationNo = 'ALF-D'.$scheme->id.'-'.$district->id.'-'.$i;

                $application = Application::firstOrNew([
                    'scheme_id' => $scheme->id,
                    'applicant_id' => $applicant->id,
                ]);

                if (! $application->exists) {
                    $application->application_no = $applicationNo;
                }

                $application->fill([
                    'district_id' => $district->id,
                    'form_id' => $scheme->publishedForm?->id,
                    'status' => $statuses[$i % count($statuses)],
                    'submitted_at' => now()->subDays($i % 20),
                    'verification_note' => in_array($statuses[$i % count($statuses)], ['district_verified', 'directorate_approved', 'disbursed'], true)
                        ? 'Demo verification note'
                        : null,
                ])->save();

                ApplicationStatusLog::firstOrCreate(
                    [
                        'application_id' => $application->id,
                        'to_status' => $application->status,
                    ],
                    ['note' => 'Demo seeded application.']
                );

                if ($scheme->publishedForm) {
                    foreach ($scheme->publishedForm->fields->where('is_core', false)->take(3) as $field) {
                        ApplicationFieldValue::updateOrCreate(
                            [
                                'application_id' => $application->id,
                                'form_field_id' => $field->id,
                            ],
                            [
                                'value' => match ($field->field_type) {
                                    'number' => (string) (5 + ($i % 10)),
                                    'radio', 'switch' => 'Yes',
                                    default => 'Demo value',
                                },
                            ]
                        );
                    }
                }
            }
        }

        foreach (Department::all() as $department) {
            DepartmentStat::updateOrCreate(
                [
                    'department_id' => $department->id,
                    'stat_label' => 'Applications Received (Live)',
                ],
                [
                    'stat_value' => '0',
                    'icon' => 'fa-solid fa-folder',
                    'source' => 'auto',
                    'auto_metric' => 'applications_total',
                    'display_order' => 90,
                    'is_active' => true,
                ]
            );

            DepartmentStat::updateOrCreate(
                [
                    'department_id' => $department->id,
                    'stat_label' => 'Approved / Disbursed (Live)',
                ],
                [
                    'stat_value' => '0',
                    'icon' => 'fa-solid fa-circle-check',
                    'source' => 'auto',
                    'auto_metric' => 'applications_approved',
                    'display_order' => 91,
                    'is_active' => true,
                ]
            );
        }

        app(StatsAggregationService::class)->refreshAll();
    }
}
