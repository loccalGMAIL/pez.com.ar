<?php

namespace Tests\Feature;

use App\Enums\InvoiceType;
use App\Support\Arca\ArcaException;
use App\Support\Arca\Wsaa;
use App\Support\Arca\Wsfe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\FakesArca;
use Tests\TestCase;

class ArcaConnectionTest extends TestCase
{
    use FakesArca, RefreshDatabase;

    public function test_signs_the_login_request_with_the_certificate(): void
    {
        $this->useArcaCertificate();
        $wsaa = new Wsaa;
        $request = $wsaa->loginTicketRequest();

        $signed = $wsaa->sign($request);

        $cms = tempnam(sys_get_temp_dir(), 'cms');
        $content = tempnam(sys_get_temp_dir(), 'content');
        file_put_contents($cms, base64_decode($signed));
        $this->assertTrue(openssl_cms_verify($cms, OPENSSL_CMS_NOVERIFY, null, [], null, $content, null, null, OPENSSL_ENCODING_DER));
        $this->assertSame($request, file_get_contents($content));
        $this->assertStringContainsString('<service>wsfe</service>', $request);
        @unlink($cms);
        @unlink($content);
    }

    public function test_logs_in_once_and_reuses_the_ticket(): void
    {
        $this->enableInvoicing();
        $this->fakeArca(['FECompUltimoAutorizado' => [self::lastAuthorized(7), self::lastAuthorized(7)]]);
        $wsfe = app(Wsfe::class);

        $this->assertSame(7, $wsfe->lastAuthorized(3, InvoiceType::FacturaC));
        $this->assertSame(7, $wsfe->lastAuthorized(3, InvoiceType::FacturaC));

        Http::assertSentCount(3);
        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'LoginCms') && str_contains($request->body(), '<in0>'));
        $this->assertStringContainsString('<Token>TOKEN-123</Token><Sign>SIGN-456</Sign><Cuit>20304050609</Cuit>', $this->arcaRequests('FECompUltimoAutorizado')[0]);
    }

    public function test_a_missing_certificate_says_where_to_set_it(): void
    {
        $this->enableInvoicing();
        config(['arca.certificate' => '/nonexistent/arca.crt']);
        Http::preventStrayRequests();

        $this->expectException(ArcaException::class);
        $this->expectExceptionMessage('ARCA_CERT_PATH');

        app(Wsfe::class)->lastAuthorized(3, InvoiceType::FacturaC);
    }

    public function test_a_lost_login_ticket_explains_that_it_must_expire(): void
    {
        $this->enableInvoicing();
        $this->useArcaCertificate();
        Http::preventStrayRequests();
        Http::fake(['wsaahomo.afip.gov.ar/*' => Http::response(
            '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"><soapenv:Body><soapenv:Fault>'
                .'<faultcode xmlns:ns1="http://xml.apache.org/axis/">ns1:coe.alreadyAuthenticated</faultcode>'
                .'<faultstring>El CEE ya posee un TA valido para el acceso al WSN solicitado</faultstring>'
                .'</soapenv:Fault></soapenv:Body></soapenv:Envelope>',
            500,
        )]);

        $this->expectException(ArcaException::class);
        $this->expectExceptionMessage('esperar a que venza');

        app(Wsfe::class)->lastAuthorized(3, InvoiceType::FacturaC);
    }

    public function test_wsfe_errors_are_reported_with_their_code(): void
    {
        $this->enableInvoicing();
        $this->fakeArca(['FECompUltimoAutorizado' => [self::error('10015', 'El punto de venta no se encuentra habilitado')]]);

        $this->expectException(ArcaException::class);
        $this->expectExceptionMessage('ARCA: 10015: El punto de venta no se encuentra habilitado');

        app(Wsfe::class)->lastAuthorized(3, InvoiceType::FacturaC);
    }
}
