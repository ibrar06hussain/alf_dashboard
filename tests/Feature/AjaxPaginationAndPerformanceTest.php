<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\Application;
use App\Models\Bill;
use App\Models\Department;
use App\Models\District;
use App\Models\FarmerOrganization;
use App\Models\Scheme;
use App\Models\SmsLog;
use App\Models\User;
use Database\Seeders\RbacHierarchySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AjaxPaginationAndPerformanceTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;
    private District $gilgit;
    private Department $dept;
    private Scheme $scheme;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacHierarchySeeder::class);

        $this->gilgit = District::firstOrCreate(['id' => 1], ['name' => 'Gilgit', 'code' => 'GLT', 'is_active' => true]);
        $this->dept = Department::firstOrCreate(['id' => 1], ['name' => 'Agriculture', 'slug' => 'agriculture', 'is_active' => true]);
        $this->scheme = Scheme::create([
            'title' => 'Wheat Seed Pagination Test Scheme',
            'slug' => 'wheat-seed-pagination-test',
            'department_id' => $this->dept->id,
            'type' => 'running',
            'scope' => 'gb',
            'status' => 'active',
        ]);

        $this->superAdmin = User::factory()->create(['is_active' => true]);
        $this->superAdmin->assignRole('Super Admin');
    }

    public function test_admin_and_public_layouts_include_universal_ajax_pagination_handler(): void
    {
        $this->actingAs($this->superAdmin)
            ->get(route('admin.applications.index'))
            ->assertOk()
            ->assertSee('X-Alf-Ajax-Pagination', false)
            ->assertSee('alf-ajax-paginating', false);

        $this->get(route('schemes.index'))
            ->assertOk()
            ->assertSee('X-Alf-Ajax-Pagination', false)
            ->assertSee('alf-ajax-paginating', false);
    }

    public function test_applications_page_returns_accurate_stats_and_paginates_cleanly_via_ajax_header(): void
    {
        for ($i = 1; $i <= 30; $i++) {
            $applicant = Applicant::create([
                'cnic' => '715010000' . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'full_name' => "Farmer {$i}",
                'father_name' => "Father {$i}",
                'phone' => '0345000' . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'district_id' => $this->gilgit->id,
            ]);

            Application::create([
                'application_no' => 'ALF-PG-' . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'scheme_id' => $this->scheme->id,
                'applicant_id' => $applicant->id,
                'district_id' => $this->gilgit->id,
                'department_id' => $this->dept->id,
                'status' => $i <= 10 ? 'directorate_approved' : 'pending',
                'current_stage' => $i <= 10 ? Application::STAGE_CHALLAN_ISSUED : Application::STAGE_SUBMITTED,
                'is_unassigned' => $i > 25,
                'submitted_at' => now()->subMinutes(30 - $i),
            ]);
        }

        // Full load renders KPI stats accurately via consolidated query
        $fullResp = $this->actingAs($this->superAdmin)
            ->get(route('admin.applications.index'))
            ->assertOk()
            ->assertSee('30')
            ->assertSee('ALF-PG-0030');

        // AJAX page 2 request works smoothly and renders page 2 items
        $this->actingAs($this->superAdmin)
            ->withHeader('X-Alf-Ajax-Pagination', '1')
            ->get(route('admin.applications.index', ['page' => 2]))
            ->assertOk()
            ->assertSee('ALF-PG-0001')
            ->assertDontSee('ALF-PG-0030');
    }

    public function test_sms_logs_ajax_pagination_skips_loading_applicants_and_renders_page_2(): void
    {
        Applicant::create([
            'cnic' => '7150199990001',
            'full_name' => 'Bulk Selector Applicant Marker',
            'father_name' => 'Father',
            'phone' => '03459990001',
            'district_id' => $this->gilgit->id,
        ]);

        for ($i = 1; $i <= 25; $i++) {
            SmsLog::create([
                'event_key' => 'submitted',
                'recipient_phone' => '92345000' . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'recipient_name' => "SMS Log Person {$i}",
                'language' => 'en',
                'is_unicode' => false,
                'message' => "Test message body {$i}",
                'status' => 'simulated',
                'sent_at' => now()->subMinutes(25 - $i),
            ]);
        }

        // Full load renders the registered applicant selector
        $this->actingAs($this->superAdmin)
            ->get(route('admin.sms-templates.index'))
            ->assertOk()
            ->assertSee('Bulk Selector Applicant Marker');

        // AJAX pagination click for SMS Logs skips loading bulk applicant list for speed
        $this->actingAs($this->superAdmin)
            ->withHeader('X-Alf-Ajax-Pagination', '1')
            ->get(route('admin.sms-templates.index', ['page' => 2]))
            ->assertOk()
            ->assertSee('Test message body')
            ->assertDontSee('Bulk Selector Applicant Marker');
    }

    public function test_gbpay_and_organizations_and_stock_and_audit_logs_ajax_pagination_respond_cleanly(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            FarmerOrganization::create([
                'district_id' => $this->gilgit->id,
                'sheet_code' => 'GLT',
                'division_name' => 'Gilgit',
                'district_name' => 'Gilgit',
                'village_name' => "Test Village {$i}",
                'organization_name' => "VO {$i}",
                'is_vo_formed' => true,
                'is_active' => true,
            ]);
        }

        $routes = [
            route('admin.gbpay.index', ['page' => 1]),
            route('admin.organizations.index', ['page' => 1]),
            route('organizations.index', ['page' => 1]),
            route('admin.users.index', ['page' => 1]),
            route('admin.audit-logs.index', ['page' => 1]),
            route('admin.schemes.index', ['page' => 1]),
            route('admin.interests.index', ['page' => 1]),
            route('admin.stock.disbursements', ['eligible_page' => 1]),
            route('admin.stock.disbursements', ['tab' => 'history', 'history_page' => 1]),
            route('admin.whatsapp-templates.index', ['page' => 1]),
            route('schemes.index', ['page' => 1]),
        ];

        foreach ($routes as $url) {
            $resp = str_contains($url, '/admin/')
                ? $this->actingAs($this->superAdmin)->withHeader('X-Alf-Ajax-Pagination', '1')->get($url)
                : $this->withHeader('X-Alf-Ajax-Pagination', '1')->get($url);

            $resp->assertOk();
        }
    }
}
