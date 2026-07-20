<?php

namespace Tests\Unit;

use App\Support\AddressQueryNormalizer;
use PHPUnit\Framework\TestCase;

class AddressQueryNormalizerTest extends TestCase
{
    public function test_rewrites_khu_pho_to_kp(): void
    {
        $this->assertSame(
            'Sông Lò Vôi, kp Lâm Viên Đồng Đình, Cần Giờ',
            AddressQueryNormalizer::normalize('Sông Lò Vôi, khu phố Lâm Viên Đồng Đình, Cần Giờ'),
        );
        $this->assertSame(
            'kp 1, Cần Giờ',
            AddressQueryNormalizer::normalize('khu pho 1, Cần Giờ'),
        );
    }
}
