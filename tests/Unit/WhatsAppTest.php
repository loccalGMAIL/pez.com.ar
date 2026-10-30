<?php

namespace Tests\Unit;

use App\Support\WhatsApp;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class WhatsAppTest extends TestCase
{
    /**
     * @return array<string, array{?string, ?string}>
     */
    public static function phones(): array
    {
        return [
            'internacional completo' => ['+54 9 11 2345-6789', '5491123456789'],
            'internacional sin 9' => ['+54 11 2345-6789', '5491123456789'],
            'con 0 de área' => ['011 2345-6789', '5491123456789'],
            'interior' => ['(0351) 456-7890', '5493514567890'],
            'diez dígitos' => ['1123456789', '5491123456789'],
            'con 15 no se adivina' => ['011 15 2345-6789', null],
            'vacío' => ['', null],
            'nulo' => [null, null],
        ];
    }

    #[DataProvider('phones')]
    public function test_normalizes_argentine_phones(?string $phone, ?string $expected): void
    {
        $this->assertSame($expected, WhatsApp::phone($phone));
    }

    public function test_builds_the_link_with_encoded_text(): void
    {
        $this->assertSame('https://wa.me/5491123456789?text=Hola%20%24%201.000', WhatsApp::url('11 2345 6789', 'Hola $ 1.000'));
        $this->assertSame('https://wa.me/?text=Hola', WhatsApp::url(null, 'Hola'));
    }
}
