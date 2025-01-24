<?php
/**
 * IPP - Project Interpret.php
 * 
 * @author Adam Juliš
 * 
 * Methods for manipulating with strings
 * 
 * Bit controversial, because static methods, it should be re-design but
 * for author it seems as elegant solution, because its used in more class but 
 * rarely
 */

namespace IPP\Student;

use IPP\Student\Exception\StringOperationError;

class StringMethod{

    /**
     *  Get value of char in added string on the exact position defined by index  
     */
    public static function getOrdValueFromString(string $string, int $index): int {
        
        if ($index < 0 || $index >= strlen($string)) {
            throw new StringOperationError("getOrdValueFromString - length of string is shorter then index");
        }

        $char = mb_substr($string, $index, 1, 'UTF-8');
        return mb_ord($char, 'UTF-8');
    }

    /**
     * Convert integer value to ASCII char
     */
    public static function int2char(int $int): string {
        return mb_chr($int, 'UTF-8');
    }

    /**
     * Find it in string and replace it for the char from ASCII
    */
     public static function replaceEscapeSequences(string $string) : mixed{
        
        return preg_replace_callback(   
                '/\\\\(\d{3})/', // looking for \XXX, where XXX are numbers
                function ($matches) {

                    $number = $matches[1];
                    
                    //out of ascii char interval
                    if ($number < 0 || $number > 255) {
                        throw new StringOperationError("replaceEscapeSequences - index out of interval 0-255");
                    }

                    return chr((int) $number);
                },
                $string
        );
    }
}