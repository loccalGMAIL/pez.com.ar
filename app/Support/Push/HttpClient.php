<?php

namespace App\Support\Push;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * PSR-18 client for the web-push library on top of Laravel's HTTP client, so
 * requests share its configuration and tests fake them with Http::fake().
 */
class HttpClient implements ClientInterface
{
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        try {
            return Http::withHeaders($request->getHeaders())
                ->withBody((string) $request->getBody(), $request->getHeaderLine('Content-Type') ?: 'application/octet-stream')
                ->timeout(15)
                ->send($request->getMethod(), (string) $request->getUri())
                ->toPsrResponse();
        } catch (ConnectionException $exception) {
            throw new NetworkException($request, $exception);
        }
    }
}
