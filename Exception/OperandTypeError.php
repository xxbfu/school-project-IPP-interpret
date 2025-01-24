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

class OperandTypeError extends IPPException
{
    public function __construct(string $message = "Operand Type Error", ?Throwable $previous = null)
    {
        parent::__construct($message, ReturnCode::OPERAND_TYPE_ERROR, $previous);
    }
}
