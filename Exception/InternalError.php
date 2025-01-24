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

class InternalError extends IPPException
{
    public function __construct(string $message = "Internal Error", ?Throwable $previous = null)
    {
        parent::__construct($message, ReturnCode::INTERNAL_ERROR, $previous);
    }
}
