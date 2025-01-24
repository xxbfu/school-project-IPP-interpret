<?php
/**
 * IPP - Project Interpret.php
 * 
 * @author Adam Juliš
 * 
 * Exceptions part
 */

namespace IPP\Student\Exception;
use IPP\Core\ReturnCode;
use IPP\Core\Exception\IPPException;
use Throwable;

class IntegrationError extends IPPException
{
    public function __construct(string $message = "Integration Error", ?Throwable $previous = null)
    {
        parent::__construct($message, ReturnCode::INTEGRATION_ERROR, $previous);
    }
}
