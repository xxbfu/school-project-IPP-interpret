<?php
/**
 * IPP - Project Interpret.php
 * 
 * @author Adam Juliš
 * 
 * Holds any regex what will be needed in project
 * 
 */

namespace IPP\Student;

abstract class Regex
{
    const string POSITIVE_NUMBER = '/^[1-9][0-9]*$/';
    const string FRAME           = '/^(GF|TF|LF)$/';
    const string TYPE            = '/^(string|int|bool|nil)$/';
    const string EXIT            = '/^[0-9]$/';
}