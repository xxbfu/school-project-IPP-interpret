<?php
/**
 * IPP - Project Interpret.php
 * 
 * @author Adam Juliš
 * 
 * The frame. It storages values, modify it and read
 * 
 */

namespace IPP\Student;

use IPP\Student\Exception\VariableAccessError;


class Frame {

    /** @var array<string, array{type: mixed, value: mixed}> 
     * It stores variables for the frame in array of array, key is name of
     * variable and every variable has type and value 
    */
    private $variables;

    public function __construct()
    {
        $this->variables = [];
    }

    public function isExistVar(string $id) : bool {
        if(array_key_exists($id, $this->variables)){
            return true;
        }
        return false;
    }

    public function loadVar(string $id, mixed $type = null, mixed $value = null) : void{

        //defined, update value
        if($this->isExistVar($id)){
            $this->variables[$id]['type'] = $type;
            $this->variables[$id]['value'] = $value;   
        }

        else{
            $this->variables[$id] = [
                'type' => $type,
                'value' => $value,
            ];
        }
    }

    /** @return array{type: mixed, value: mixed} */
    public function getVar(string $id) : array{

        if (!isset($this->variables[$id])) {
            throw new VariableAccessError("Variable is not declared, cant get variable");
        }

        return $this->variables[$id];
    }

    public function getVarValue(string $id) : mixed{

        if (!isset($this->variables[$id])) {
            throw new VariableAccessError("Variable is not declared, cant get value");
        }

        return $this->variables[$id]['value'];
    }



    public function getVarType(string $id) : mixed{

        if (!isset($this->variables[$id])) {
            throw new VariableAccessError("Variable is not declared, cant get type");
        }

        return $this->variables[$id]['type'];
    }

}