<?php

namespace App\Support\Invoicing;

use RuntimeException;

/**
 * The invoice cannot be issued with the data at hand (settings, client).
 * Nothing is sent to ARCA; the message says what to fix.
 */
class InvoicingException extends RuntimeException {}
