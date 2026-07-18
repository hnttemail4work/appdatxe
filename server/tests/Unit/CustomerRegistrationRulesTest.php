<?php

namespace Tests\Unit;

use App\Services\RegistrationService;
use App\Support\AuthMessages;
use App\Support\AuthPhone;
use App\Support\DriverFieldRules;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class CustomerRegistrationRulesTest extends TestCase
{
    public function test_customer_rules_require_phone_pin_cccd_and_terms(): void
    {
        $rules = app(RegistrationService::class)->customerRules();

        $this->assertArrayHasKey('phone', $rules);
        $this->assertArrayHasKey('password', $rules);
        $this->assertArrayHasKey('password_confirmation', $rules);
        $this->assertArrayHasKey('photo_id_card', $rules);
        $this->assertArrayHasKey('photo_id_card_back', $rules);
        $this->assertArrayHasKey('terms', $rules);
        $this->assertSame(['accepted'], $rules['terms']);
        $this->assertContains('digits:6', $rules['password']);

        $this->assertArrayNotHasKey('name', $rules);
        $this->assertArrayNotHasKey('age', $rules);
        $this->assertArrayNotHasKey('gender', $rules);
        $this->assertArrayNotHasKey('email', $rules);
    }

    public function test_id_card_photo_rules_shared_with_driver_pack(): void
    {
        $customer = DriverFieldRules::idCardPhotoRules(true);
        $driver = DriverFieldRules::registrationPhotoRules();

        $this->assertSame($customer['photo_id_card'], $driver['photo_id_card']);
        $this->assertSame($customer['photo_id_card_back'], $driver['photo_id_card_back']);
    }

    public function test_terms_must_be_accepted(): void
    {
        $validator = Validator::make(
            ['terms' => '0'],
            ['terms' => ['accepted']],
        );

        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('terms'));
    }

    public function test_id_card_files_must_be_images(): void
    {
        $validator = Validator::make(
            [
                'photo_id_card'      => UploadedFile::fake()->create('front.pdf', 100, 'application/pdf'),
                'photo_id_card_back' => UploadedFile::fake()->create('back.jpg', 100, 'image/jpeg'),
            ],
            DriverFieldRules::idCardPhotoRules(true),
        );

        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('photo_id_card'));
    }

    public function test_pin_must_be_six_digits_and_confirmed(): void
    {
        $pinRules = [
            'password'              => app(RegistrationService::class)->customerRules()['password'],
            'password_confirmation' => app(RegistrationService::class)->customerRules()['password_confirmation'],
        ];

        $fail = Validator::make([
            'password'              => '12345',
            'password_confirmation' => '12345',
        ], $pinRules);
        $this->assertTrue($fail->fails());
        $this->assertTrue($fail->errors()->has('password'));

        $mismatch = Validator::make([
            'password'              => '123456',
            'password_confirmation' => '654321',
        ], $pinRules);
        $this->assertTrue($mismatch->fails());
        $this->assertTrue($mismatch->errors()->has('password'));
    }

    public function test_customer_phone_uses_shared_auth_phone_rules(): void
    {
        $rules = app(RegistrationService::class)->customerRules();
        $phoneRules = $rules['phone'];

        $this->assertContains('required', $phoneRules);
        $this->assertTrue(collect($phoneRules)->contains(
            fn ($rule) => $rule instanceof \App\Rules\UniqueNormalizedPhone
        ));

        $invalid = Validator::make(
            ['phone' => 'abc'],
            ['phone' => $phoneRules],
        );
        $this->assertTrue($invalid->fails());
        $this->assertSame(AuthMessages::PHONE_INVALID, $invalid->errors()->first('phone'));

        $valid = Validator::make(
            ['phone' => '0901234567'],
            ['phone' => AuthPhone::rules()],
        );
        $this->assertFalse($valid->fails());
    }

    public function test_driver_register_phone_uses_auth_phone(): void
    {
        $phoneRules = DriverFieldRules::userFields(null, 'register')['phone'];

        $this->assertContains('required', $phoneRules);
        $this->assertTrue(collect($phoneRules)->contains(
            fn ($rule) => $rule instanceof \App\Rules\UniqueNormalizedPhone
        ));

        $invalid = Validator::make(
            ['phone' => '123'],
            ['phone' => $phoneRules],
        );
        $this->assertTrue($invalid->fails());
        $this->assertSame(AuthMessages::PHONE_INVALID, $invalid->errors()->first('phone'));
    }
}
