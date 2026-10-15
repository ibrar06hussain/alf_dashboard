<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function test_fallback_storage_route_returns_404_for_missing_file(): void
    {
        $response = $this->get('/storage/non_existent_file_123.jpg');
        $response->assertStatus(404);
    }

    public function test_fallback_storage_route_blocks_path_traversal(): void
    {
        $response = $this->get('/storage/../.env');
        $response->assertStatus(403);
    }

    public function test_fallback_storage_route_serves_existing_file(): void
    {
        \Illuminate\Support\Facades\Storage::disk('public')->put('test_banner.txt', 'Banner Content');

        $response = $this->get('/storage/test_banner.txt');
        $response->assertStatus(200);
        $this->assertEquals('Banner Content', $response->streamedContent());

        \Illuminate\Support\Facades\Storage::disk('public')->delete('test_banner.txt');
    }
}
