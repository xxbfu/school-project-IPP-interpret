<?php
/**
 * IPP - Project Interpret.php
 * 
 * @author Adam Juliš
 * 
 * DataStack and his methods
 */

namespace IPP\Student;

use IPP\Student\Exception\ValueError;

class DataStack {

    /** @var array<array{value: mixed, type: string}> 
     * It stores values and their types in LIFO
    */
    private $dataStack;
    
    public function __construct(){
        $this->dataStack = [];
    }

    /**
     * Add value to top of the stack
     */
    public function push(mixed $value, string $type) : void{
        $this->dataStack[] = [
            'value' => $value,
            'type' => $type
            ];
    }

    /**
     * Return array value and type
     * 
     *  @return array{value: mixed, type: string}
     */
    public function pop() : array {

        if (empty($this->dataStack)) {
            throw new ValueError("Stack is empty");
        }

        return array_pop($this->dataStack);
    }

    /**
    * 
    * Returns last value from the stack or null,
    * if it's empty
    * 
    * Useful for break for example
    *
    * @return array{value: mixed, type: mixed}
    */
    public function top() : array {

        if (empty($this->dataStack)) {
            return [
                'value' => null,
                'type' => null
                ];
        }

        return end($this->dataStack);
    }

}