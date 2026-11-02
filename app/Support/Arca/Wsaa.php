<?php

namespace App\Support\Arca;

use DOMDocument;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * ARCA's authentication service. It trades a request signed with the
 * certificate for a token and a signature that are valid for 12 hours;
 * they are cached because ARCA refuses a new login while one is valid.
 */
class Wsaa
{
    public const SERVICE = 'wsfe';

    /**
     * @return array{token: string, sign: string}
     */
    public function credentials(): array
    {
        $cacheKey = 'arca.ticket.'.config('arca.environment').'.'.self::SERVICE;
        $ticket = Cache::get($cacheKey);

        if (is_array($ticket) && isset($ticket['token'], $ticket['sign'])) {
            return ['token' => (string) $ticket['token'], 'sign' => (string) $ticket['sign']];
        }

        $ticket = $this->login();

        Cache::put($cacheKey, ['token' => $ticket['token'], 'sign' => $ticket['sign']], $ticket['expires_at']->subMinutes(10));

        return ['token' => $ticket['token'], 'sign' => $ticket['sign']];
    }

    /**
     * Login ticket request (TRA). The window starts in the past to tolerate a skewed clock.
     */
    public function loginTicketRequest(): string
    {
        $now = now();

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<loginTicketRequest version="1.0"><header>'
            .'<uniqueId>'.$now->getTimestamp().'</uniqueId>'
            .'<generationTime>'.$now->copy()->subMinutes(10)->toIso8601String().'</generationTime>'
            .'<expirationTime>'.$now->copy()->addMinutes(10)->toIso8601String().'</expirationTime>'
            .'</header><service>'.self::SERVICE.'</service></loginTicketRequest>';
    }

    /**
     * Signs the request with the certificate and key: CMS with the content attached, base64 encoded.
     */
    public function sign(string $request): string
    {
        $certificate = (string) config('arca.certificate');
        $key = (string) config('arca.key');

        if (! is_readable($certificate) || ! is_readable($key)) {
            throw new ArcaException('No hay certificado de ARCA instalado para este ambiente: generalo e instalalo en Configuración → Facturación.');
        }

        $input = (string) tempnam(sys_get_temp_dir(), 'arca-tra');
        $output = (string) tempnam(sys_get_temp_dir(), 'arca-cms');

        try {
            file_put_contents($input, $request);

            $signed = openssl_cms_sign(
                $input,
                $output,
                'file://'.realpath($certificate),
                ['file://'.realpath($key), (string) config('arca.key_passphrase')],
                [],
                OPENSSL_CMS_BINARY,
                OPENSSL_ENCODING_DER,
            );

            if (! $signed) {
                throw new ArcaException('No se pudo firmar el pedido a ARCA: revisá que la clave corresponda al certificado.');
            }

            return base64_encode((string) file_get_contents($output));
        } finally {
            @unlink($input);
            @unlink($output);
        }
    }

    /**
     * @return array{token: string, sign: string, expires_at: Carbon}
     */
    private function login(): array
    {
        $endpoint = (string) config('arca.endpoints.'.config('arca.environment').'.wsaa');

        try {
            $response = Soap::call(
                $endpoint,
                '',
                '<loginCms xmlns="http://wsaa.view.sua.dvadac.desein.afip.gov"><in0>'
                    .$this->sign($this->loginTicketRequest())
                    .'</in0></loginCms>',
            );
        } catch (ArcaException $exception) {
            if (str_contains((string) $exception->faultCode, 'alreadyAuthenticated')) {
                throw new ArcaException('ARCA ya entregó un acceso vigente que no quedó guardado. Hay que esperar a que venza (hasta 12 horas) para volver a conectarse.', $exception->faultCode);
            }

            throw $exception;
        }

        $ticket = new DOMDocument;

        if (! @$ticket->loadXML(trim($response->textContent))) {
            throw new ArcaException('ARCA respondió el ingreso con un formato inesperado.');
        }

        $read = fn (string $tag) => trim((string) $ticket->getElementsByTagName($tag)->item(0)?->textContent);

        if ($read('token') === '' || $read('sign') === '') {
            throw new ArcaException('ARCA respondió el ingreso sin las credenciales.');
        }

        return [
            'token' => $read('token'),
            'sign' => $read('sign'),
            'expires_at' => $read('expirationTime') === '' ? now()->addHours(12) : Carbon::parse($read('expirationTime')),
        ];
    }
}
