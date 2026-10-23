<?php

namespace Tests\Feature;

use App\Support\Cnic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CnicVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Config::set('services.survey_external', [
            'base_url' => 'https://survey.example.com/api/external',
            'username' => 'testuser',
            'password' => 'secret123',
            'auth_path' => 'auth/login',
            'lookup_path' => 'solar/member/lookup',
        ]);
    }

    public function test_cnic_helper_formats_dashed_and_cleans(): void
    {
        $this->assertSame('12345-1234567-1', Cnic::dashed('1234512345671'));
        $this->assertSame('12345-1234567-1', Cnic::dashed('12345-1234567-1'));
        $this->assertSame('1234512345671', Cnic::clean('12345-1234567-1'));
        $this->assertTrue(Cnic::isValid('1234512345671'));
        $this->assertTrue(Cnic::isValid('12345-1234567-1'));
        $this->assertFalse(Cnic::isValid('12345'));
    }

    public function test_verify_endpoint_validates_13_digits(): void
    {
        $response = $this->postJson(route('cnic.verify'), [
            'cnic' => '12345',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['cnic']);
    }

    public function test_verify_endpoint_successful_lookup(): void
    {
        Http::fake([
            'https://survey.example.com/api/external/auth/login' => Http::response([
                'success' => true,
                'data' => ['access_token' => 'mock_token_abc'],
            ], 200),
            'https://survey.example.com/api/external/solar/member/lookup' => Http::response([
                'success' => true,
                'message' => 'Member record found',
                'data' => [
                    'full_name' => 'Ali Khan',
                    'father_name' => 'Muhammad Khan',
                    'cnic' => '12345-1234567-1',
                    'gender' => 'Male',
                    'district' => 'Gilgit',
                ],
            ], 200),
        ]);

        $response = $this->postJson(route('cnic.verify'), [
            'cnic' => '1234512345671',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'full_name' => 'Ali Khan',
                    'father_name' => 'Muhammad Khan',
                ],
            ]);

        Http::assertSent(function ($request) {
            if ($request->url() === 'https://survey.example.com/api/external/solar/member/lookup') {
                return $request['cnic'] === '12345-1234567-1'
                    && $request->hasHeader('Authorization', 'Bearer mock_token_abc');
            }
            return true;
        });
    }

    public function test_verify_endpoint_handles_record_not_found(): void
    {
        Http::fake([
            'https://survey.example.com/api/external/auth/login' => Http::response([
                'success' => true,
                'data' => ['access_token' => 'mock_token_abc'],
            ], 200),
            'https://survey.example.com/api/external/solar/member/lookup' => Http::response([
                'success' => false,
                'message' => 'Member not found in survey database',
            ], 200),
        ]);

        $response = $this->postJson(route('cnic.verify'), [
            'cnic' => '1234512345671',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => false,
                'message' => 'Member with the provided CNIC not found in the system. Please enter correct cnic.',
            ]);
    }

    public function test_application_submission_blocked_when_cnic_not_verified(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');

        $department = \App\Models\Department::firstOrCreate(
            ['slug' => 'agriculture-cnic-test'],
            ['name' => 'Agriculture CNIC Test', 'is_active' => true]
        );
        $district = \App\Models\District::firstOrCreate(
            ['name' => 'Gilgit Test'],
            ['code' => 'GLT2', 'is_active' => true]
        );
        $scheme = \App\Models\Scheme::create([
            'department_id' => $department->id,
            'title' => 'CNIC Test Scheme',
            'slug' => 'cnic-test-scheme',
            'type' => 'running',
            'scope' => 'gb',
            'status' => 'active',
            'start_date' => now()->subDay(),
            'last_date' => now()->addMonth(),
        ]);
        $form = \App\Models\Form::create([
            'scheme_id' => $scheme->id,
            'title' => 'Form',
            'version' => 1,
            'is_published' => true,
        ]);
        foreach (\App\Support\CoreFormFields::definitions() as $f) {
            \App\Models\FormField::create(array_merge($f, ['form_id' => $form->id]));
        }

        Http::fake([
            'https://survey.example.com/api/external/auth/login' => Http::response([
                'success' => true,
                'data' => ['access_token' => 'mock_token'],
            ], 200),
            'https://survey.example.com/api/external/solar/member/lookup' => Http::response([
                'success' => false,
                'message' => 'Member not found',
            ], 200),
        ]);

        $payload = [
            'full_name' => 'Test User',
            'father_name' => 'Test Father',
            'cnic' => '1234512345671',
            'phone' => '03001234567',
            'district_id' => $district->id,
            'tehsil' => 'Gilgit',
            'land_location' => 'Gilgit City',
        ];

        $response = $this->post(route('schemes.apply.store', $scheme->slug), $payload);

        $response->assertSessionHasErrors(['cnic']);
        $this->assertEquals(0, \App\Models\Application::where('scheme_id', $scheme->id)->count());
    }
}
