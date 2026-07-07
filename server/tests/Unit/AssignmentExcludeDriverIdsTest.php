<?php

namespace Tests\Unit;

use App\Models\Schedule;
use App\Services\DriverTripRequestService;
use Illuminate\Support\Collection;
use Tests\TestCase;

class AssignmentExcludeDriverIdsTest extends TestCase
{
    public function test_empty_phone_returns_empty_exclude_list(): void
    {
        $service = app(DriverTripRequestService::class);
        $schedule = new Schedule(['id' => 99]);

        $result = $service->assignmentExcludeDriverIds($schedule, '');

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertTrue($result->isEmpty());
    }
}
