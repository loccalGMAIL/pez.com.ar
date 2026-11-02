<?php

namespace Tests\Feature;

use App\Filament\Pages\Settings as SettingsPage;
use App\Models\User;
use App\Support\Arca\ArcaException;
use App\Support\Arca\Certificate;
use App\Support\Settings;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class ArcaCertificateTest extends TestCase
{
    use RefreshDatabase;

    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir().'/pez-arca-certificate-'.Str::random(8);
        config([
            'arca.certificate' => "{$this->directory}/arca.crt",
            'arca.key' => "{$this->directory}/arca.key",
        ]);
    }

    protected function tearDown(): void
    {
        foreach (glob("{$this->directory}/*") ?: [] as $file) {
            unlink($file);
        }
        @rmdir($this->directory);

        parent::tearDown();
    }

    /**
     * What ARCA does with the request: a certificate for its public key, signed with ARCA's own key.
     */
    private function issueCertificate(string $request, int $days = 730): string
    {
        $arcaKey = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_x509_export(openssl_csr_sign($request, null, $arcaKey, $days), $certificate);

        return $certificate;
    }

    private function settingsAction(string $name): TestAction
    {
        return TestAction::make($name)->schemaComponent('certificate', schema: 'form');
    }

    public function test_generates_a_request_for_the_company_cuit_keeping_the_certificate_in_use(): void
    {
        Settings::set(['company.cuit' => '20-30405060-9']);

        $request = Certificate::generateRequest('pezsistema', 'PEZ Servicios IT');

        $subject = openssl_csr_get_subject($request);
        $this->assertSame(['C' => 'AR', 'O' => 'PEZ Servicios IT', 'CN' => 'pezsistema', 'serialNumber' => 'CUIT 20304050609'], $subject);
        $this->assertSame($request, Certificate::pendingRequest());
        $this->assertFileDoesNotExist(config('arca.key'));
    }

    public function test_installs_the_certificate_issued_for_the_request(): void
    {
        Settings::set(['company.cuit' => '20-30405060-9']);
        $certificate = $this->issueCertificate(Certificate::generateRequest('pezsistema', 'PEZ'));

        $installed = Certificate::install($certificate);

        $this->assertSame('pezsistema', $installed['alias']);
        $this->assertSame('20304050609', $installed['cuit']);
        $this->assertTrue(Certificate::installed()['matches_key']);
        $this->assertNull(Certificate::pendingRequest());
        $this->assertSame('0600', substr(sprintf('%o', fileperms(config('arca.key'))), -4));
    }

    public function test_accepts_the_certificate_as_a_der_file(): void
    {
        Settings::set(['company.cuit' => '20-30405060-9']);
        $pem = $this->issueCertificate(Certificate::generateRequest('pezsistema', 'PEZ'));
        $der = base64_decode((string) preg_replace('/-----[A-Z ]+-----|\s/', '', $pem));

        $this->assertSame('pezsistema', Certificate::install($der)['alias']);
    }

    public function test_rejects_a_certificate_for_another_request(): void
    {
        Settings::set(['company.cuit' => '20-30405060-9']);
        Certificate::generateRequest('pezsistema', 'PEZ');
        $otherKey = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $otherRequest = openssl_csr_new(['commonName' => 'otro', 'serialNumber' => 'CUIT 20304050609'], $otherKey);
        openssl_csr_export($otherRequest, $otherRequestPem);

        $this->expectException(ArcaException::class);
        $this->expectExceptionMessage('no corresponde al último pedido');

        Certificate::install($this->issueCertificate($otherRequestPem));
    }

    public function test_rejects_a_certificate_for_another_cuit(): void
    {
        Settings::set(['company.cuit' => '20-30405060-9']);
        $certificate = $this->issueCertificate(Certificate::generateRequest('pezsistema', 'PEZ'));
        Settings::set(['company.cuit' => '30-71234567-1']);

        $this->expectException(ArcaException::class);
        $this->expectExceptionMessage('es para el CUIT 20-30405060-9');

        Certificate::install($certificate);
    }

    public function test_rejects_an_expired_certificate(): void
    {
        Settings::set(['company.cuit' => '20-30405060-9']);
        $certificate = $this->issueCertificate(Certificate::generateRequest('pezsistema', 'PEZ'), days: 30);
        $this->travel(31)->days();

        $this->expectException(ArcaException::class);
        $this->expectExceptionMessage('venció');

        Certificate::install($certificate);
    }

    public function test_needs_the_company_cuit(): void
    {
        $this->expectException(ArcaException::class);
        $this->expectExceptionMessage('CUIT de la empresa');

        Certificate::generateRequest('pezsistema', 'PEZ');
    }

    public function test_generates_and_downloads_the_request_from_the_settings(): void
    {
        $this->actingAs(User::factory()->create());
        Settings::set(['company.cuit' => '20-30405060-9']);

        Livewire::test(SettingsPage::class)
            ->callAction($this->settingsAction('generateCertificateRequest'), data: ['alias' => 'pezsistema', 'organization' => 'PEZ'])
            ->assertNotified('Pedido generado')
            ->assertFileDownloaded('pezsistema.csr')
            ->assertActionVisible($this->settingsAction('downloadCertificateRequest'));

        $this->assertNotNull(Certificate::pendingRequest());
    }

    public function test_rejects_an_alias_with_spaces(): void
    {
        $this->actingAs(User::factory()->create());
        Settings::set(['company.cuit' => '20-30405060-9']);

        Livewire::test(SettingsPage::class)
            ->callAction($this->settingsAction('generateCertificateRequest'), data: ['alias' => 'pez sistema'])
            ->assertHasActionErrors(['alias' => 'regex']);

        $this->assertNull(Certificate::pendingRequest());
    }

    public function test_installs_the_pasted_certificate_from_the_settings(): void
    {
        $this->actingAs(User::factory()->create());
        Settings::set(['company.cuit' => '20-30405060-9']);
        $certificate = $this->issueCertificate(Certificate::generateRequest('pezsistema', 'PEZ'));

        Livewire::test(SettingsPage::class)
            ->callAction($this->settingsAction('installCertificate'), data: ['text' => $certificate])
            ->assertNotified('Certificado instalado');

        $this->assertSame('pezsistema', Certificate::installed()['alias']);
    }

    public function test_explains_why_a_certificate_was_not_installed(): void
    {
        $this->actingAs(User::factory()->create());
        Settings::set(['company.cuit' => '20-30405060-9']);

        Livewire::test(SettingsPage::class)
            ->callAction($this->settingsAction('installCertificate'), data: ['text' => 'cualquier cosa'])
            ->assertNotified('No se pudo instalar el certificado');

        $this->assertNull(Certificate::installed());
    }
}
