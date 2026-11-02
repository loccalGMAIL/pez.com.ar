<?php

namespace App\Support\Arca;

use App\Enums\InvoiceType;
use App\Rules\Cuit;
use App\Support\Settings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;

/**
 * ARCA's electronic invoicing service (WSFEv1).
 */
class Wsfe
{
    private const NAMESPACE = 'http://ar.gov.afip.dif.FEV1/';

    /** FECompConsultar: the voucher does not exist. */
    private const NOT_FOUND = '602';

    public function __construct(private readonly Wsaa $wsaa) {}

    /**
     * Health of ARCA's servers (FEDummy); needs no credentials.
     *
     * @return array<string, string>
     */
    public function serverStatus(): array
    {
        $result = $this->call('FEDummy', authenticated: false);

        return array_map(fn ($value) => is_string($value) ? $value : '', $result);
    }

    /**
     * Last number ARCA authorized for the point of sale and type (0 when none).
     *
     * @throws ArcaException
     * @throws ConnectionException
     */
    public function lastAuthorized(int $pointOfSale, InvoiceType $type): int
    {
        $result = $this->call('FECompUltimoAutorizado', [
            'PtoVta' => $pointOfSale,
            'CbteTipo' => $type->value,
        ]);

        $this->throwErrors($result);

        return (int) ($result['CbteNro'] ?? 0);
    }

    /**
     * Asks for the CAE of one voucher. A rejection is a result, not an exception.
     *
     * @param  array<string, mixed>  $detail  FECAEDetRequest elements, in ARCA's order
     *
     * @throws ArcaException
     * @throws ConnectionException
     */
    public function requestCae(int $pointOfSale, InvoiceType $type, array $detail): CaeResult
    {
        $result = $this->call('FECAESolicitar', [
            'FeCAEReq' => [
                'FeCabReq' => [
                    'CantReg' => 1,
                    'PtoVta' => $pointOfSale,
                    'CbteTipo' => $type->value,
                ],
                'FeDetReq' => [
                    'FECAEDetRequest' => $detail,
                ],
            ],
        ]);

        $response = $result['FeDetResp']['FECAEDetResponse'] ?? [];
        $response = is_array($response) && array_is_list($response) ? ($response[0] ?? []) : $response;
        $response = is_array($response) ? $response : [];
        $approved = ($response['Resultado'] ?? $result['FeCabResp']['Resultado'] ?? null) === 'A';
        $cae = trim((string) ($response['CAE'] ?? ''));

        return new CaeResult(
            approved: $approved && $cae !== '',
            cae: $cae === '' ? null : $cae,
            caeDueOn: self::date($response['CAEFchVto'] ?? null),
            messages: [
                ...self::messages($result['Errors']['Err'] ?? null),
                ...self::messages($response['Observaciones']['Obs'] ?? null),
            ],
            response: $result,
        );
    }

    /**
     * A voucher as ARCA registered it, or null when ARCA has no such number.
     *
     * @return array<string, mixed>|null
     *
     * @throws ArcaException
     * @throws ConnectionException
     */
    public function find(int $pointOfSale, InvoiceType $type, int $number): ?array
    {
        $result = $this->call('FECompConsultar', [
            'FeCompConsReq' => [
                'CbteTipo' => $type->value,
                'CbteNro' => $number,
                'PtoVta' => $pointOfSale,
            ],
        ]);

        $errors = Soap::list($result['Errors']['Err'] ?? null);

        if (collect($errors)->contains(fn ($error) => is_array($error) && (string) ($error['Code'] ?? '') === self::NOT_FOUND)) {
            return null;
        }

        $this->throwErrors($result);

        $voucher = $result['ResultGet'] ?? null;

        return is_array($voucher) ? $voucher : null;
    }

    /**
     * ARCA's dates (yyyymmdd) as dates; anything else as null.
     */
    public static function date(mixed $value): ?Carbon
    {
        $value = is_string($value) || is_int($value) ? trim((string) $value) : '';

        return preg_match('/^\d{8}$/', $value) === 1 ? Carbon::createFromFormat('!Ymd', $value) : null;
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>
     */
    private function call(string $method, array $parameters = [], bool $authenticated = true): array
    {
        if ($authenticated) {
            $parameters = ['Auth' => $this->auth(), ...$parameters];
        }

        $response = Soap::call(
            (string) config('arca.endpoints.'.config('arca.environment').'.wsfe'),
            self::NAMESPACE.$method,
            '<'.$method.' xmlns="'.self::NAMESPACE.'">'.Soap::xml($parameters).'</'.$method.'>',
        );

        $result = Soap::toArray($response);
        $result = is_array($result) ? ($result[$method.'Result'] ?? []) : [];

        return is_array($result) ? $result : [];
    }

    /**
     * @return array{Token: string, Sign: string, Cuit: string}
     */
    private function auth(): array
    {
        $cuit = Cuit::normalize((string) Settings::get('company.cuit'));

        if (strlen($cuit) !== 11) {
            throw new ArcaException('Cargá el CUIT de la empresa en Configuración para conectarte con ARCA.');
        }

        $credentials = $this->wsaa->credentials();

        return ['Token' => $credentials['token'], 'Sign' => $credentials['sign'], 'Cuit' => $cuit];
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function throwErrors(array $result): void
    {
        $errors = self::messages($result['Errors']['Err'] ?? null);

        if ($errors !== []) {
            throw new ArcaException('ARCA: '.implode(' / ', $errors));
        }
    }

    /**
     * @return list<string>
     */
    private static function messages(mixed $entries): array
    {
        return collect(Soap::list($entries))
            ->filter(fn ($entry) => is_array($entry))
            ->map(fn (array $entry) => trim(($entry['Code'] ?? '').': '.($entry['Msg'] ?? ''), ': '))
            ->values()
            ->all();
    }
}
