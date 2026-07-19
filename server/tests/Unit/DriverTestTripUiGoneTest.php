<?php

namespace Tests\Unit;

use Tests\TestCase;

/** Đảm bảo UI demo TEST nhận cuốc không còn trên dashboard TX. */
class DriverTestTripUiGoneTest extends TestCase
{
    public function test_driver_dashboard_source_has_no_test_trip_fab(): void
    {
        $panel = file_get_contents(resource_path('views/partials/driver-bottom-panel.blade.php'));
        $js = file_get_contents(public_path('js/driver-bottom-panel.js'));
        $css = file_get_contents(public_path('css/driver.css'));

        $this->assertStringNotContainsString('driver-test-trip-fab', $panel);
        $this->assertStringNotContainsString('TEST: Có cuốc', $panel);
        $this->assertStringNotContainsString('driver-trip-sheet-mock', $panel);
        $this->assertStringNotContainsString('setTestMode', $js);
        $this->assertStringNotContainsString('driver-trip-mock', $js);
        $this->assertStringNotContainsString('driver-test-trip-fab', $css);
        $this->assertFileDoesNotExist(resource_path('views/partials/driver-trip-sheet-mock.blade.php'));
    }
}
