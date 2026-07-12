<?php

namespace Tests\Unit;

use App\Rules\UniqueNormalizedPhone;
use App\Support\AuthIdentifier;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class UniqueNormalizedPhoneTest extends TestCase
{
    public function test_normalizes_vietnamese_phone_variants(): void
    {
        $this->assertSame('0909123456', AuthIdentifier::normalizePhone('+84 909 123 456'));
        $this->assertSame('0909123456', AuthIdentifier::normalizePhone('84909123456'));
    }

    public function test_rejects_invalid_phone_format(): void
    {
        $validator = Validator::make(
            ['phone' => 'abc'],
            ['phone' => ['required', new UniqueNormalizedPhone()]],
        );

        $this->assertTrue($validator->fails());
        $this->assertStringContainsString('hợp lệ', (string) $validator->errors()->first('phone'));
    }

    public function test_rejects_short_phone_number(): void
    {
        $validator = Validator::make(
            ['phone' => '09091'],
            ['phone' => ['required', new UniqueNormalizedPhone()]],
        );

        $this->assertTrue($validator->fails());
        $this->assertStringContainsString('định dạng', (string) $validator->errors()->first('phone'));
    }

    public function test_skips_empty_optional_phone(): void
    {
        $validator = Validator::make(
            ['phone' => ''],
            ['phone' => ['nullable', new UniqueNormalizedPhone()]],
        );

        $this->assertFalse($validator->fails());
    }
}
