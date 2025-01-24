<?php
/**
 * IPP - Project Interpret.php
 * 
 * @author Adam Juliš
 * 
 * Handles all frames and his variables and their methods
 * 
 * Interface for Frame
 */

namespace IPP\Student;

use IPP\Student\Exception\ValueError;
use IPP\Student\Exception\FrameAccessError;
use IPP\Student\Exception\VariableAccessError;
use IPP\Student\Exception\SemanticError;

class Frames
{
    /** @var Frame|null
     * Main frame, it exists for whole time of life of Frames object
     */
    private $GF;

    /** @var Frame|null\
     * If temporary frame is uninitialized, it holds null, else it
     * should be empty or stores the variables
     */
    private $TF;

    /** @var Frame[]|null 
     * Its array of local frames, rules are same as for TF
    */
    private $LF;

    public function __construct(){
        $this->GF = new Frame();
        $this->TF = null;
        $this->LF = [];
    }

    public function createFrame(): void {
        $this->TF = new Frame();
    }

    public function pushFrame() : void {
        
        if(empty($this->TF)){
            throw new FrameAccessError("TF is empty, can't be push");
        }
        
        array_push($this->LF, $this->TF);

        $this->TF = null;
    }

    public function popFrame() : void {

        if(empty($this->LF)){
            throw new FrameAccessError("LF is empty, can't be pop");
        }

        $this->TF = array_pop($this->LF);

    }
    
    /**
     * It handles all modification of variables 
     * 
     * The logic is, it gets in second argument type and value
     * what will be stored in the first argument
     * 
     * If second argument is variable, the its load from it
     * 
     * It checks all needed dependencies for correct operation
     * 
     * @param array{string,string|null,string|null}$dest
     * @param array{mixed,string|null,mixed}$src
     */
    public function replaceVar(array $dest,array $src) : void{

        $frame = $dest[0];
        $id = $dest[1];
        $type = $dest[2];

        $frame2OrValue = $src[0];

        $id2 = $src[1];
        $type2 = $src[2];

        //if destination variable exist in frame
        if (!$this->isExist($frame, $id)){
            throw new VariableAccessError("Variable is not declared");
        }
        
        $value = $frame2OrValue; 

        //source symbol should be variable, then extract its value and type
        if ($type2 == 'var'){

            //if it not exists in added frame
            if(!$this->isExist($frame2OrValue, $id2)){
                throw new VariableAccessError("Variable is not declared");
            }
            if($frame2OrValue == "LF"){
                $frame2OrValue = end($this->LF);
            }
            else{
                $frame2OrValue = $this->$frame2OrValue;
            }
            $value = $frame2OrValue->getVarValue($id2);
            $type2 = $frame2OrValue->getVarType($id2);
        }

        //search sequences and replace them with char from ascii
        else if($type2 == 'string'){
            $value = StringMethod::replaceEscapeSequences($value);
        }

        if($frame == "LF"){
            $frame = end($this->LF);
        }
        else{
            $frame = $this->$frame;
        }

        //load the value to the destination variable for added frame
        $frame->loadVar($id, $type2, $value);

    }

    /**
     * Load variable type and value
     */
    public function loadVar(string $frame, string $id) : void{

        if($this->isExist($frame, $id)){
            throw new SemanticError("Variable already exist, can't be redefined");
        }
        
        if($frame == "LF"){
            $frame = end($this->LF);
        }
        else{
            $frame = $this->$frame;
        }

        
        $frame->loadVar($id);
    }

    /**
     * Check if variable exist in the frame
     */
    public function isExist(string $frame, string $variable): bool{

        if($frame == "LF"){
            if (empty($this->LF)){
                throw new FrameAccessError("LF is empty, variable doesn't exist");
            }

            $lastLF = end($this->LF);

            return $lastLF->isExistVar($variable);

        }

        if (is_null($this->$frame)){
            throw new FrameAccessError("$frame is empty, variable doesn't exist");
        }
        
        if(!$this->$frame->isExistVar($variable)){
            return false;
        }
        
        return true;
    }

    /**
     * Get value and type of received variable
     * 
     * @return array{string, mixed}
     */
    public function getVarTypeAndValue(string $frame, string $id) : array{

        $typeAndValue[] = $this->getVarType($frame, $id);
        $typeAndValue[] = $this->getVarValue($frame, $id);

        return $typeAndValue;

    }

    public function getVarType(string $frame, string $id) : mixed{
        
        if(!$this->isExist($frame, $id)){
            throw new VariableAccessError("Variable is not declared");
        }
        if($frame == "LF"){
            $frame = end($this->LF);
        }
        else{
            $frame = $this->$frame;
        }

        return $frame->getVarType($id);
    
    }

    public function getVarValue(string $frame, string $id) : mixed{

        if(!$this->isExist($frame, $id)){
            throw new VariableAccessError("Variable is not declared, can't load");
        }

        if($frame == "LF"){
            $frame = end($this->LF);
        }
        else{
            $frame = $this->$frame;
        }
        
        $value = $frame->getVarValue($id);
        
        if($value === null){
            throw new ValueError("Variable exist but not initialized");
        }
        return $value;
        
    }

}
