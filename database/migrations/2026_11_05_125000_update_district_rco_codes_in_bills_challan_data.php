<?php

use App\Models\Bill;
use App\Models\District;
use App\Models\PsidConfig;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Updates challan_data.rco in bills table to the specific Deputy Director Agriculture RCO code for each district.
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

            $rcoCode = PsidConfig::resolveDistrictRco($district);

            $challanData['rco'] = $rcoCode;

            $bill->challan_data = $challanData;
            $bill->saveQuietly();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $bills = Bill::all();

        foreach ($bills as $bill) {
            $challanData = $bill->challan_data;
            if (! is_array($challanData)) {
                continue;
            }

            $challanData['rco'] = 'GL1800';

            $bill->challan_data = $challanData;
            $bill->saveQuietly();
        }
    }
};

