<?php

namespace Tests\Unit;

use App\Support\CccdQrParser;
use PHPUnit\Framework\TestCase;

class CccdQrParserTest extends TestCase
{
    public function test_parses_standard_cccd_qr(): void
    {
        $raw = '079203012345||NGUYEN VAN A|15031990|Nam|Thanh pho Ho Chi Minh|01012021';
        $parsed = CccdQrParser::parse($raw);

        $this->assertNotNull($parsed);
        $this->assertSame('079203012345', $parsed['id_number']);
        $this->assertSame('Nguyen Van A', $parsed['name']);
        $this->assertSame('1990-03-15', $parsed['date_of_birth']);
        $this->assertSame('male', $parsed['gender']);
    }

    public function test_parses_female_and_slash_dob(): void
    {
        $raw = '001094002345||TRAN THI B|01/05/1995|Nữ|Ha Noi|15/08/2021';
        $parsed = CccdQrParser::parse($raw);

        $this->assertNotNull($parsed);
        $this->assertSame('1995-05-01', $parsed['date_of_birth']);
        $this->assertSame('female', $parsed['gender']);
    }

    public function test_rejects_garbage(): void
    {
        $this->assertNull(CccdQrParser::parse('hello'));
        $this->assertNull(CccdQrParser::parse('a|b|c'));
    }
}
