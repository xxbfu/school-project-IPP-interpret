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



class FrameAccessError extends IPPException
{
    public function __construct(string $message = "Frame Access Error", ?Throwable $previous = null)
    {
        parent::__construct($message, ReturnCode::FRAME_ACCESS_ERROR, $previous);
    }
}
