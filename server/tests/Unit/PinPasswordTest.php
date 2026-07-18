<?php

namespace Tests\Unit;

use App\Support\PinPassword;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PinPasswordTest extends TestCase
{
    public function test_accepts_exactly_six_digits(): void
    {
        $this->assertTrue(PinPassword::isValid('123456'));
        $this->assertFalse(PinPassword::isValid('12345'));
        $this->assertFalse(PinPassword::isValid('1234567'));
        $this->assertFalse(PinPassword::isValid('12a456'));
        $this->assertFalse(PinPassword::isValid(null));
    }

    public function test_assert_valid_throws_on_bad_pin(): void
    {
        $this->expectException(ValidationException::class);
        PinPassword::assertValid('abcdef');
    }

    public function test_hash_and_check_roundtrip(): void
    {
        $hash = PinPassword::hash('654321');
        $this->assertTrue(PinPassword::check('654321', $hash));
        $this->assertFalse(PinPassword::check('000000', $hash));
    }

    public function test_rules_include_digits_six(): void
    {
        $rules = PinPassword::rules(confirmed: true);
        $this->assertContains('digits:6', $rules);
        $this->assertContains('confirmed', $rules);
    }
}
