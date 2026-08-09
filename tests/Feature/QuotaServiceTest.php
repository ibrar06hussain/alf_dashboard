<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\Application;
use App\Models\Department;
use App\Models\District;
use App\Models\Scheme;
use App\Services\QuotaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuotaServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_district_quota_blocks_when_full(): void
    {
        $department = Department::create(['name' => 'Agri', 'slug' => 'agri', 'is_active' => true]);
        $district = District::create(['name' => 'Gilgit', 'code' => 'GLT', 'is_active' => true]);

        $scheme = Scheme::create([
            'department_id' => $department->id,
            'title' => 'Quota Scheme',
            'slug' => 'quota-scheme',
            'type' => 'running',
            'scope' => 'district',
            'status' => 'active',
        ]);

        $scheme->districts()->attach($district->id, ['quota' => 1]);

        $applicant = Applicant::create([
            'cnic' => '1111111111111',
            'full_name' => 'A',
            'phone' => '03000000000',
            'district_id' => $district->id,
        ]);

        Application::create([
            'application_no' => 'ALF-Q-1',
            'scheme_id' => $scheme->id,
            'applicant_id' => $applicant->id,
            'district_id' => $district->id,
            'status' => 'directorate_approved',
            'submitted_at' => now(),
        ]);

        $service = app(QuotaService::class);

        $this->assertFalse($service->canApprove($scheme, $district->id));
        $this->assertTrue($service->status($scheme, $district->id)['exceeded']);
    }

    public function test_gb_scope_has_no_quota_limit(): void
    {
        $department = Department::create(['name' => 'Agri', 'slug' => 'agri2', 'is_active' => true]);
        $district = District::create(['name' => 'Hunza', 'code' => 'HNZ', 'is_active' => true]);

        $scheme = Scheme::create([
            'department_id' => $department->id,
            'title' => 'GB Scheme',
            'slug' => 'gb-scheme',
            'type' => 'running',
            'scope' => 'gb',
            'status' => 'active',
        ]);

        $this->assertTrue(app(QuotaService::class)->canApprove($scheme, $district->id));
        $this->assertNull(app(QuotaService::class)->status($scheme, $district->id));
    }
}
