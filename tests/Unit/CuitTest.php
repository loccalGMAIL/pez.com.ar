<?php

namespace Tests\Unit;

use App\Rules\Cuit;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CuitTest extends TestCase
{
    /**
     * @return array<string, array{string, bool}>
     */
    public static function cuits(): array
    {
        return [
            'con guiones' => ['20-12345678-6', true],
            'sin guiones' => ['20123456786', true],
            'empresa' => ['30-71234567-1', true],
            'dígito verificador incorrecto' => ['20-12345678-5', false],
            'corto' => ['20-1234567-6', false],
            'letras' => ['20-1234567A-6', false],
            'vacío' => ['', false],
        ];
    }

    #[DataProvider('cuits')]
    public function test_validates_check_digit(string $cuit, bool $valid): void
    {
        $this->assertSame($valid, Cuit::isValid($cuit));
    }

    public function test_formats_digits_with_dashes(): void
    {
        $this->assertSame('20-12345678-6', Cuit::format('20123456786'));
        $this->assertSame('20123456786', Cuit::normalize('20-12345678-6'));
    }
}
