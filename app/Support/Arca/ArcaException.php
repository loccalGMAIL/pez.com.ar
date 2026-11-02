<?php

namespace App\Support\Arca;

use RuntimeException;

/**
 * ARCA answered with an error (SOAP fault, WSFE error list) or the
 * connection could not be set up (missing certificate, invalid key).
 * The message is in Spanish and safe to show in the panel.
 */
class ArcaException extends RuntimeException
{
    public function __construct(string $message, public readonly ?string $faultCode = null)
    {
        parent::__construct($message);
    }
}
