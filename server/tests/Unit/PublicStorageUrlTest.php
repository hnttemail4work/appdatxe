<?php

namespace Tests\Unit;

use App\Support\PublicStorageUrl;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicStorageUrlTest extends TestCase
{
    public function test_returns_null_for_empty_path(): void
    {
        $this->assertNull(PublicStorageUrl::url(null));
        $this->assertNull(PublicStorageUrl::url(''));
    }

    public function test_builds_route_url_for_existing_file(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('drivers/1/portrait.jpg', 'fake');

        $url = PublicStorageUrl::url('drivers/1/portrait.jpg');

        $this->assertNotNull($url);
        $this->assertStringEndsWith('/storage/drivers/1/portrait.jpg', $url);
    }

    public function test_strips_storage_prefix_from_path(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('drivers/2/id.jpg', 'fake');

        $url = PublicStorageUrl::url('storage/drivers/2/id.jpg');

        $this->assertNotNull($url);
        $this->assertStringEndsWith('/storage/drivers/2/id.jpg', $url);
    }

    public function test_returns_null_when_file_missing(): void
    {
        Storage::fake('public');

        $this->assertNull(PublicStorageUrl::url('drivers/99/missing.jpg'));
    }
}
