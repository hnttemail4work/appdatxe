<?php

namespace Tests\Unit;

use App\Support\AuthMessages;
use App\Support\AuthPhone;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class AuthPhoneTest extends TestCase
{
    public function test_normalize_strips_non_digits_and_converts_84_prefix(): void
    {
        $this->assertSame('0901234567', AuthPhone::normalize('090 123 4567'));
        $this->assertSame('0901234567', AuthPhone::normalize('+84 901 234 567'));
        $this->assertSame('0901234567', AuthPhone::normalize('84901234567'));
        $this->assertSame('', AuthPhone::normalize('   '));
    }

    public function test_is_valid_accepts_vn_mobile_lengths(): void
    {
        $this->assertTrue(AuthPhone::isValid('0901234567'));
        $this->assertTrue(AuthPhone::isValid('012345678'));
        $this->assertTrue(AuthPhone::isValid('01234567890'));
        $this->assertFalse(AuthPhone::isValid(''));
        $this->assertFalse(AuthPhone::isValid('901234567'));
        $this->assertFalse(AuthPhone::isValid('123'));
        $this->assertFalse(AuthPhone::isValid('abcdefghij'));
    }

    public function test_rules_reject_invalid_phone_with_shared_message(): void
    {
        $validator = Validator::make(
            ['phone' => '123'],
            ['phone' => AuthPhone::rules()],
        );

        $this->assertTrue($validator->fails());
        $this->assertSame(AuthMessages::PHONE_INVALID, $validator->errors()->first('phone'));
    }

    public function test_rules_require_phone(): void
    {
        $validator = Validator::make(
            ['phone' => ''],
            ['phone' => AuthPhone::rules()],
            AuthMessages::phone(),
        );

        $this->assertTrue($validator->fails());
        $this->assertSame(AuthMessages::PHONE_REQUIRED, $validator->errors()->first('phone'));
    }

    public function test_rules_accept_valid_phone(): void
    {
        $validator = Validator::make(
            ['phone' => '0901234567'],
            ['phone' => AuthPhone::rules()],
        );

        $this->assertFalse($validator->fails());
    }
}
