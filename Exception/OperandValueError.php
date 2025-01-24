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

class OperandValueError extends IPPException
{
    public function __construct(string $message = "Operand Value Error", ?Throwable $previous = null)
    {
        parent::__construct($message, ReturnCode::OPERAND_VALUE_ERROR, $previous);
    }
}
