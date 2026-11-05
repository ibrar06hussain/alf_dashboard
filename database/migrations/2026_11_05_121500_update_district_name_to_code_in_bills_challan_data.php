<?php

use App\Models\Bill;
use App\Models\District;
use App\Models\PsidConfig;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Updates challan_data.district_name in bills table to the code of the applicant's district (e.g. DM, GL, AT).
     */
    public function up(): void
    {
        $bills = Bill::with(['application.district', 'application.applicant.district'])->get();

        foreach ($bills as $bill) {
            $challanData = $bill->challan_data;
            if (! is_array($challanData)) {
                continue;
            }

            $application = $bill->application;
            $district = $application?->district
                ?? $application?->applicant?->district
                ?? ($application?->district_id ? District::find($application->district_id) : null);

            $districtCode = $district?->code
                ?? PsidConfig::getDistrictAbbreviation(district: $district)
                ?? 'GL';

            $challanData['district_name'] = $districtCode;

            $bill->challan_data = $challanData;
            $bill->saveQuietly();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $bills = Bill::with(['application.district', 'application.applicant.district'])->get();

        foreach ($bills as $bill) {
            $challanData = $bill->challan_data;
            if (! is_array($challanData)) {
                continue;
            }

            $application = $bill->application;
            $district = $application?->district
                ?? $application?->applicant?->district
                ?? ($application?->district_id ? District::find($application->district_id) : null);

            $challanData['district_name'] = $district?->name ?? 'Gilgit';

            $bill->challan_data = $challanData;
            $bill->saveQuietly();
        }
    }
};

