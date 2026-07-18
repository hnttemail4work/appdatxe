<?php

namespace Tests\Unit;

use App\Support\DriverDefaultPassword;
use Tests\TestCase;

class DriverDefaultPasswordTest extends TestCase
{
    public function test_plain_from_phone_uses_last_six_digits(): void
    {
        $this->assertSame('000003', DriverDefaultPassword::plainFromPhone('0900000003'));
        $this->assertSame('000002', DriverDefaultPassword::plainFromPhone('1000000002'));
    }

    public function test_plain_from_short_phone_is_padded(): void
    {
        $this->assertSame('001234', DriverDefaultPassword::plainFromPhone('1234'));
    }

    public function test_random_plain_is_six_digits(): void
    {
        $plain = DriverDefaultPassword::randomPlain();

        $this->assertSame(6, strlen($plain));
        $this->assertMatchesRegularExpression('/^\d{6}$/', $plain);
    }
}
