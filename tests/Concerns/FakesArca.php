<?php

namespace Tests\Concerns;

use App\Support\Settings;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Fake ARCA services: a throwaway certificate, the login, and the WSFE
 * answers each test expects, in order. Any other request fails the test.
 */
trait FakesArca
{
    protected const COMPANY_CUIT = '20-30405060-9';

    /** @var array{certificate: string, key: string}|null */
    private static ?array $arcaCertificate = null;

    /**
     * Invoicing on, with the company CUIT and point of sale 3.
     *
     * @param  array<string, mixed>  $settings
     */
    protected function enableInvoicing(array $settings = []): void
    {
        Settings::set([
            'company.cuit' => self::COMPANY_CUIT,
            'invoicing.enabled' => true,
            'invoicing.point_of_sale' => 3,
            ...$settings,
        ]);
    }

    /**
     * Self-signed certificate and key, generated once per run.
     */
    protected function useArcaCertificate(): void
    {
        if (self::$arcaCertificate === null) {
            $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
            $request = openssl_csr_new(['commonName' => 'pez-test', 'serialNumber' => 'CUIT 20304050609'], $key);
            openssl_x509_export(openssl_csr_sign($request, null, $key, 1), $certificate);
            openssl_pkey_export($key, $privateKey);

            $directory = sys_get_temp_dir().'/pez-arca-'.getmypid();
            @mkdir($directory);
            file_put_contents("{$directory}/arca.crt", $certificate);
            file_put_contents("{$directory}/arca.key", $privateKey);

            self::$arcaCertificate = ['certificate' => "{$directory}/arca.crt", 'key' => "{$directory}/arca.key"];
        }

        config(['arca.certificate' => self::$arcaCertificate['certificate'], 'arca.key' => self::$arcaCertificate['key']]);
    }

    /**
     * WSFE answers still to give, per operation.
     *
     * @var array<string, list<string|callable(Request): mixed>>
     */
    private array $arcaAnswers = [];

    private bool $arcaFaked = false;

    /**
     * Sets the answers ARCA gives next. The fake is registered once per test
     * (the HTTP client keeps every fake it gets), and later calls replace the queue.
     *
     * @param  array<string, list<string|callable(Request): mixed>>  $wsfe  answers per operation: result XML or a callback
     */
    protected function fakeArca(array $wsfe = []): void
    {
        $this->arcaAnswers = $wsfe;

        if ($this->arcaFaked) {
            return;
        }

        $this->arcaFaked = true;
        $this->useArcaCertificate();
        // Authorized invoices archive their PDF.
        Storage::fake('local');

        Http::preventStrayRequests();
        Http::fake([
            'wsaahomo.afip.gov.ar/*' => Http::response(self::loginResponse()),
            'wswhomo.afip.gov.ar/*' => function (Request $request) {
                $operation = Str::afterLast(trim($request->header('SOAPAction')[0] ?? '', '"'), '/');

                if (($this->arcaAnswers[$operation] ?? []) === []) {
                    throw new RuntimeException("Unexpected call to ARCA: {$operation}");
                }

                $answer = array_shift($this->arcaAnswers[$operation]);

                return is_callable($answer) ? $answer($request) : Http::response(self::wsfeResponse($operation, $answer));
            },
        ]);
    }

    /**
     * Requests sent to a WSFE operation.
     *
     * @return list<string>
     */
    protected function arcaRequests(string $operation): array
    {
        return Http::recorded(fn (Request $request) => Str::endsWith(trim($request->header('SOAPAction')[0] ?? '', '"'), "/{$operation}"))
            ->map(fn (array $pair) => $pair[0]->body())
            ->values()
            ->all();
    }

    protected static function lastAuthorized(int $number): string
    {
        return "<PtoVta>3</PtoVta><CbteTipo>11</CbteTipo><CbteNro>{$number}</CbteNro>";
    }

    protected static function approved(string $cae = '76443213456789', string $dueOn = '20261112'): string
    {
        return '<FeCabResp><Resultado>A</Resultado></FeCabResp>'
            .'<FeDetResp><FECAEDetResponse><Resultado>A</Resultado>'
            ."<CAE>{$cae}</CAE><CAEFchVto>{$dueOn}</CAEFchVto>"
            .'</FECAEDetResponse></FeDetResp>';
    }

    protected static function rejected(string ...$observations): string
    {
        $obs = collect($observations)
            ->map(fn (string $text) => '<Obs><Code>'.Str::before($text, ':').'</Code><Msg>'.trim(Str::after($text, ':')).'</Msg></Obs>')
            ->join('');

        return '<FeCabResp><Resultado>R</Resultado></FeCabResp>'
            ."<FeDetResp><FECAEDetResponse><Resultado>R</Resultado><Observaciones>{$obs}</Observaciones></FECAEDetResponse></FeDetResp>";
    }

    protected static function error(string $code, string $message): string
    {
        return "<Errors><Err><Code>{$code}</Code><Msg>{$message}</Msg></Err></Errors>";
    }

    /**
     * FECompConsultar's answer for a voucher ARCA has.
     */
    protected static function found(int $number, float $total, string $documentNumber, string $cae = '76443213456789', string $date = '20261102'): string
    {
        return "<ResultGet><CbteDesde>{$number}</CbteDesde><CbteFch>{$date}</CbteFch><ImpTotal>{$total}</ImpTotal>"
            ."<DocNro>{$documentNumber}</DocNro><Resultado>A</Resultado><CodAutorizacion>{$cae}</CodAutorizacion><FchVto>20261112</FchVto></ResultGet>";
    }

    protected static function notFound(): string
    {
        return self::error('602', 'No existen datos en nuestros registros para los parametros ingresados.');
    }

    private static function wsfeResponse(string $operation, string $result): string
    {
        return '<?xml version="1.0" encoding="utf-8"?>'
            .'<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body>'
            ."<{$operation}Response xmlns=\"http://ar.gov.afip.dif.FEV1/\"><{$operation}Result>{$result}</{$operation}Result></{$operation}Response>"
            .'</soap:Body></soap:Envelope>';
    }

    protected static function loginResponse(string $expiration = '2099-01-01T00:00:00-03:00'): string
    {
        $ticket = '<?xml version="1.0" encoding="UTF-8"?><loginTicketResponse version="1.0"><header>'
            ."<expirationTime>{$expiration}</expirationTime></header>"
            .'<credentials><token>TOKEN-123</token><sign>SIGN-456</sign></credentials></loginTicketResponse>';

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"><soapenv:Body>'
            .'<loginCmsResponse xmlns="http://wsaa.view.sua.dvadac.desein.afip.gov"><loginCmsReturn>'
            .htmlspecialchars($ticket, ENT_XML1)
            .'</loginCmsReturn></loginCmsResponse></soapenv:Body></soapenv:Envelope>';
    }
}
