<?php

namespace Tests\Unit;

use App\Support\Cpf;
use Tests\TestCase;

class CpfTest extends TestCase
{
    public function test_format_masked_from_digits_only(): void
    {
        $this->assertSame('529.982.247-25', Cpf::formatMasked('52998224725'));
    }

    public function test_format_masked_from_already_masked(): void
    {
        $this->assertSame('529.982.247-25', Cpf::formatMasked('529.982.247-25'));
    }

    public function test_format_masked_returns_null_when_incomplete(): void
    {
        $this->assertNull(Cpf::formatMasked('5299822472'));
    }

    public function test_known_valid_checksum(): void
    {
        $this->assertTrue(Cpf::isValid('529.982.247-25'));
        $this->assertTrue(Cpf::isValidChecksum('52998224725'));
    }

    public function test_invalid_checksum(): void
    {
        $this->assertFalse(Cpf::isValid('529.982.247-26'));
        $this->assertFalse(Cpf::isValidChecksum('52998224726'));
    }

    public function test_rejects_all_same_digit(): void
    {
        $this->assertFalse(Cpf::isValid('111.111.111-11'));
        $this->assertFalse(Cpf::isValidChecksum('11111111111'));
    }
}
