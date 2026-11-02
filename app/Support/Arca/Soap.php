<?php

namespace App\Support\Arca;

use DOMDocument;
use DOMElement;
use DOMNode;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Minimal SOAP 1.1 over Laravel's HTTP client: ARCA's services take a handful
 * of plain elements, so building the XML by hand keeps it testable with
 * Http::fake() and avoids depending on ext-soap.
 */
class Soap
{
    /**
     * Posts the body inside an envelope and returns the first element of the
     * response body (the operation's response).
     *
     * @throws ArcaException when ARCA answers with a fault or something that is not SOAP
     * @throws ConnectionException when ARCA does not answer
     */
    public static function call(string $url, string $action, string $body): DOMElement
    {
        $envelope = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body>'
            .$body
            .'</soap:Body></soap:Envelope>';

        $response = Http::connectTimeout(10)
            ->timeout((int) config('arca.timeout'))
            // ARCA's servers use Diffie-Hellman keys that OpenSSL 3 rejects at its default level.
            ->withOptions(['curl' => [CURLOPT_SSL_CIPHER_LIST => 'DEFAULT@SECLEVEL=1']])
            ->withHeaders(['SOAPAction' => '"'.$action.'"'])
            ->withBody($envelope, 'text/xml; charset=utf-8')
            ->post($url);

        $document = new DOMDocument;

        if (trim($response->body()) === '' || ! @$document->loadXML($response->body())) {
            throw new ArcaException("ARCA respondió con un error (HTTP {$response->status()}).");
        }

        $body = $document->getElementsByTagNameNS('http://schemas.xmlsoap.org/soap/envelope/', 'Body')->item(0);
        $operation = $body instanceof DOMElement ? self::children($body)[0] ?? null : null;

        if ($operation === null) {
            throw new ArcaException("ARCA respondió con un error (HTTP {$response->status()}).");
        }

        if ($operation->localName === 'Fault') {
            $fault = self::toArray($operation);

            throw new ArcaException(
                'ARCA: '.trim(is_array($fault) ? (string) ($fault['faultstring'] ?? '') : $fault),
                is_array($fault) ? (string) ($fault['faultcode'] ?? '') : null,
            );
        }

        return $operation;
    }

    /**
     * Element names to XML, in order; nulls are left out.
     *
     * @param  array<string, mixed>  $data
     */
    public static function xml(array $data): string
    {
        $xml = '';

        foreach ($data as $name => $value) {
            if ($value === null) {
                continue;
            }

            $xml .= "<{$name}>"
                .(is_array($value) ? self::xml($value) : htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8'))
                ."</{$name}>";
        }

        return $xml;
    }

    /**
     * Element tree to arrays keyed by local name (namespaces dropped). A name
     * repeated among siblings becomes a list; leaves become their text.
     *
     * @return array<string, mixed>|string
     */
    public static function toArray(DOMNode $node): array|string
    {
        $children = self::children($node);

        if ($children === []) {
            return $node->textContent;
        }

        $counts = array_count_values(array_map(fn (DOMElement $child) => (string) $child->localName, $children));
        $result = [];

        foreach ($children as $child) {
            $name = (string) $child->localName;

            if ($counts[$name] > 1) {
                $result[$name][] = self::toArray($child);
            } else {
                $result[$name] = self::toArray($child);
            }
        }

        return $result;
    }

    /**
     * A repeated element as a list, whether ARCA sent it once, many times or not at all.
     *
     * @return list<mixed>
     */
    public static function list(mixed $value): array
    {
        return match (true) {
            $value === null || $value === '' => [],
            is_array($value) && array_is_list($value) => $value,
            default => [$value],
        };
    }

    /**
     * @return list<DOMElement>
     */
    private static function children(DOMNode $node): array
    {
        $children = [];

        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $children[] = $child;
            }
        }

        return $children;
    }
}
