<?php

namespace App\Support\Arca;

use App\Rules\Cuit;
use App\Support\Settings;
use Illuminate\Support\Carbon;
use OpenSSLCertificate;

/**
 * The certificate ARCA issues to connect to its services. The panel creates
 * the private key and the signing request (CSR) on the server, and installs
 * the certificate ARCA returns for it. The key never leaves the server.
 *
 * A new request is kept apart ("pending") until its certificate is
 * installed, so the certificate in use keeps working meanwhile.
 */
class Certificate
{
    public const DEFAULT_ALIAS = 'pezsistema';

    /**
     * Creates a new private key and its signing request, and returns the request (PEM).
     *
     * @throws ArcaException
     */
    public static function generateRequest(string $alias, string $organization): string
    {
        $cuit = self::companyCuit();

        // A minimal OpenSSL config: the server's default one adds placeholder fields ("Some-State").
        $config = (string) tempnam(sys_get_temp_dir(), 'arca-cnf');
        file_put_contents($config, "[ req ]\ndistinguished_name = req_distinguished_name\n[ req_distinguished_name ]\n");
        $options = ['config' => $config, 'private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA, 'digest_alg' => 'sha256'];

        try {
            $key = openssl_pkey_new($options);
            $request = $key === false ? false : openssl_csr_new([
                'countryName' => 'AR',
                'organizationName' => $organization,
                'commonName' => $alias,
                'serialNumber' => "CUIT {$cuit}",
            ], $key, $options);

            if ($key === false || $request === false || ! openssl_pkey_export($key, $keyPem, null, $options) || ! openssl_csr_export($request, $requestPem)) {
                throw new ArcaException('No se pudo generar el pedido: revisá que OpenSSL esté disponible en el servidor.');
            }
        } finally {
            @unlink($config);
        }

        self::write(self::pendingKeyPath(), $keyPem, 0600);
        self::write(self::requestPath(), $requestPem, 0644);

        return $requestPem;
    }

    /**
     * The request waiting for its certificate, if any.
     */
    public static function pendingRequest(): ?string
    {
        return is_readable(self::pendingKeyPath()) && is_readable(self::requestPath())
            ? (string) file_get_contents(self::requestPath())
            : null;
    }

    /**
     * Installs the certificate ARCA issued (PEM text or a DER file) after
     * checking it is for the company's CUIT, still valid and made for the
     * pending request (or for the key in use, when it is a renewal).
     *
     * @return array{alias: string, cuit: ?string, valid_until: Carbon, matches_key: bool}
     *
     * @throws ArcaException
     */
    public static function install(string $content): array
    {
        $pem = self::toPem($content);
        $certificate = @openssl_x509_read($pem);

        if ($certificate === false) {
            throw new ArcaException('El archivo no es un certificado válido: subí el .crt que entrega ARCA.');
        }

        $details = self::details($certificate);
        $cuit = self::companyCuit();

        if ($details['cuit'] !== $cuit) {
            throw new ArcaException('El certificado es para el CUIT '.($details['cuit'] ? Cuit::format($details['cuit']) : 'desconocido')
                .', no para el de la empresa ('.Cuit::format($cuit).').');
        }

        if ($details['valid_until']->isPast()) {
            throw new ArcaException('El certificado venció el '.$details['valid_until']->format('d/m/Y').'.');
        }

        $pendingKey = self::read(self::pendingKeyPath());
        $currentKey = self::read((string) config('arca.key'));

        if ($pendingKey !== null && openssl_x509_check_private_key($certificate, $pendingKey)) {
            self::write((string) config('arca.key'), $pendingKey, 0600);
            @unlink(self::pendingKeyPath());
            @unlink(self::requestPath());
        } elseif ($currentKey === null || ! openssl_x509_check_private_key($certificate, $currentKey)) {
            throw new ArcaException('El certificado no corresponde al último pedido generado. Generá un pedido nuevo y subí el certificado que ARCA entregue para ese pedido.');
        }

        self::write((string) config('arca.certificate'), $pem, 0644);

        return [...$details, 'matches_key' => true];
    }

    /**
     * The certificate in use, or null when there is none.
     *
     * @return array{alias: string, cuit: ?string, valid_until: Carbon, matches_key: bool}|null
     */
    public static function installed(): ?array
    {
        $pem = self::read((string) config('arca.certificate'));
        $certificate = $pem === null ? false : @openssl_x509_read($pem);

        if ($certificate === false) {
            return null;
        }

        $key = self::read((string) config('arca.key'));

        return [
            ...self::details($certificate),
            'matches_key' => $key !== null && openssl_x509_check_private_key($certificate, $key),
        ];
    }

    /**
     * @return array{alias: string, cuit: ?string, valid_until: Carbon}
     */
    private static function details(OpenSSLCertificate $certificate): array
    {
        $parsed = openssl_x509_parse($certificate) ?: [];
        $subject = is_array($parsed['subject'] ?? null) ? $parsed['subject'] : [];
        preg_match('/(\d{11})/', (string) ($subject['serialNumber'] ?? ''), $cuit);

        return [
            'alias' => (string) ($subject['CN'] ?? ''),
            'cuit' => $cuit[1] ?? null,
            'valid_until' => Carbon::createFromTimestamp((int) ($parsed['validTo_time_t'] ?? 0), config('app.timezone')),
        ];
    }

    private static function companyCuit(): string
    {
        $cuit = Cuit::normalize((string) Settings::get('company.cuit'));

        if (strlen($cuit) !== 11) {
            throw new ArcaException('Cargá y guardá el CUIT de la empresa (pestaña Empresa) antes de seguir.');
        }

        return $cuit;
    }

    /**
     * PEM as is; a DER file or bare base64 wrapped as PEM.
     */
    private static function toPem(string $content): string
    {
        if (str_contains($content, '-----BEGIN CERTIFICATE-----')) {
            return trim($content)."\n";
        }

        // A binary file is encoded as is: trimming could cut bytes off its ends.
        $body = preg_match('/^[A-Za-z0-9+\/=\s]+$/', $content) === 1
            ? (string) preg_replace('/\s+/', '', $content)
            : base64_encode($content);

        return "-----BEGIN CERTIFICATE-----\n".chunk_split($body, 64, "\n")."-----END CERTIFICATE-----\n";
    }

    private static function pendingKeyPath(): string
    {
        return dirname((string) config('arca.key')).'/pending.key';
    }

    private static function requestPath(): string
    {
        return dirname((string) config('arca.key')).'/pending.csr';
    }

    private static function read(string $path): ?string
    {
        return is_readable($path) ? (string) file_get_contents($path) : null;
    }

    private static function write(string $path, string $contents, int $permissions): void
    {
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0700, true);
        }

        file_put_contents($path, $contents);
        chmod($path, $permissions);
    }
}
