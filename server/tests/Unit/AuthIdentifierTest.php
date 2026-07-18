<?php

namespace Tests\Unit;

use App\Support\AuthIdentifier;
use PHPUnit\Framework\TestCase;

class AuthIdentifierTest extends TestCase
{
    public function test_normalize_vietnam_phone_with_country_code(): void
    {
        $this->assertSame('0900000003', AuthIdentifier::normalizePhone('+84 900 000 003'));
    }

    public function test_normalize_domestic_phone(): void
    {
        $this->assertSame('0900000003', AuthIdentifier::normalizePhone('0900-000-003'));
    }
}
