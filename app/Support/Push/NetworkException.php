<?php

namespace App\Support\Push;

use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;
use RuntimeException;
use Throwable;

class NetworkException extends RuntimeException implements NetworkExceptionInterface
{
    public function __construct(private RequestInterface $request, Throwable $previous)
    {
        parent::__construct($previous->getMessage(), 0, $previous);
    }

    public function getRequest(): RequestInterface
    {
        return $this->request;
    }
}
