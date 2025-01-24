<?php
/**
 * IPP - Project Interpret.php
 * 
 * @author Adam Juliš
 * 
 * This class contains methods handling all 
 * possible command in IPPCode24 language. 
 * 
 */

namespace IPP\Student;

use DOMElement;
use IPP\Core\AbstractInterpreter;

use IPP\Student\Exception\InternalError;
use IPP\Student\Exception\InvalidSourceStructure;
use IPP\Student\Exception\OperandTypeError;
use IPP\Student\Exception\SemanticError;
use IPP\Student\Exception\StringOperationError;
use IPP\Student\Exception\ValueError;
use IPP\Student\Exception\OperandValueError;


class Interpreter extends AbstractInterpreter
{
    /** @var DOMElement[] 
     * Every line of IPPCode24 is loaded to this array
     * It contains all information of instruction
    */
    protected $instructionsByOrders = [];

     /** @var int 
      * Holds the current position in the loaded code 
      * using the stored order number  
      * It can be modified with any method for process 
      * instruction
    */
    protected $currentOrder;

    /** @var Frames|null
     * Object allowing all frame work, initialization 
     * created Global Frame
     */
    protected $frames;

    /** @var DataStack|null 
     * Object allowing all stack work, initialization 
     * created stack for store values
    */
    protected $stack;
    
    /** @var array<string,int> 
     * Every label before main loop of interpret is
     * pre-loaded into this array, it stores name of 
     * label (key) and its Order number (position in code)
    */
    protected $labels = [];

    /** @var array<int> 
     * Every call what is executed saved his position (current
     * order) into this array for possibly later use with
     * instruction Return
    */
    protected $calls = [];

    /**
    *   The only public method for run interpret
    */
    public function execute(): int
    {

        $this->loadXml();

        $this->mainLoop();

        return 0;
    }

    /**
     * Check if the format of Xml is valid
     * If "Label" instruction detected, it's stored to $labels
     * Then load every instruction from Xml to $instructionsByOrders 
     * and sort it via number of order 
     */
    private function loadXml() : void{

        $dom = $this->source->getDOMDocument();

        //check if any unusual instruction there
        foreach ($dom->documentElement->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE && $child->nodeName !== "instruction") {
                throw new InvalidSourceStructure("Unknown instruction");
            }
        }

        //Check order number (as duplicity) and load labels
        foreach ($dom->getElementsByTagName('instruction') as $instructByOrder) {
            
            $currentOrder = trim($instructByOrder->getAttribute('order'));

            if(!preg_match(Regex::POSITIVE_NUMBER, $currentOrder)){
                throw new InvalidSourceStructure("Order number is not positive number");
            }

            if(array_key_exists($currentOrder, $this->instructionsByOrders)){
                throw new InvalidSourceStructure("Duplicity order number");
            }

            if($this->parseInstruction($instructByOrder->getAttribute('opcode')) == "processLabel"){
                
                $arg1 = $instructByOrder->getElementsByTagName('arg1');

                if ($arg1->length > 0) {
                    
                    $labelId = trim($arg1->item(0)->nodeValue);

                    if(array_key_exists($labelId, $this->labels)){
                        throw new SemanticError("Duplicity label");
                    }

                    $this->labels[$labelId] = (int)$currentOrder; 
                }

                else{
                    throw new InvalidSourceStructure("Void argument for label instruction");
                }
            }

            $this->instructionsByOrders[$currentOrder] = $instructByOrder;
        }

        ksort($this->instructionsByOrders); 

        //order 0 is forbidden
        if(isset($this->instructionsByOrders[0]))
            throw new InvalidSourceStructure("Order number is 0");
    }

    /**
     * It goes through the whole loaded Xml ($instructionsByOrders), until the
     * currentOrder reach last instruction.
     */
    private function mainLoop() : void{

        $this->frames = new Frames();
        $this->stack = new DataStack();
        
        
        //go until last command
        $this->currentOrder = 0;

        while($this->currentOrder <=  array_key_last($this->instructionsByOrders)){
        
            if(!array_key_exists($this->currentOrder, $this->instructionsByOrders)){
                $this->currentOrder++;
                continue;
            }

            $currentInstruction = $this->instructionsByOrders[$this->currentOrder];

            $processNameFunction = $this->parseInstruction($currentInstruction->getAttribute('opcode'));
            
            $this->$processNameFunction($currentInstruction);

            //always skip label or returning to the call anyway
            $this->currentOrder++;
        }
    }

    /**
     * Create correct name of method from loaded opcode from $instructionsByOrders
     * 
     * @return string - name of function for handle instruction
     */
    private function parseInstruction(string $opcode): string{

        $opcode = strtolower($opcode);
        $opcode = ucfirst($opcode);
        
        $processName = "process" . $opcode;

        if(!method_exists($this, $processName)){
            throw new InvalidSourceStructure("Unknown opcode");
        }
        
        return $processName;

    }

    /**
     * Helper method for extract information about arguments of instruction
     * 
     * Returns array, but it depend on the type:
     * 
     * for variable returns [0] - frame, [1] - name, [2] - type
     * for constant returns [0] - constant, [1] - null, [2] - type 
     * 
     * @param array{value: string, type: string} $arg Number of expected arguments.
     * @return array{ mixed,  ?string, string} Array of extracted arguments.
     */
    private function extractFrameVariableType(array $arg) : array {

        if ($arg['type'] == 'var') {
            $frameAndVariable = explode("@", $arg['value']);
            return [$frameAndVariable[0], $frameAndVariable[1], $arg['type']];
        } 
        else {
            return [$arg['value'], null, $arg['type']];
        }
    }

    /**
     * Extracts arguments from a DOMElement instruction.
     *
     * @param DOMElement $instruction representing the instruction.
     * @param int $numArg Number of expected arguments.
     * @return array<int, array{value: string, type: string}> $args Array of extracted arguments.
     */
    private function extractArguments(DOMElement $instruction, int $numArg): array {

        $args = [];
        $allArgs = $instruction->getElementsByTagName('*');

        //Parser should not allowed this xml 
        if ($allArgs->length > $numArg) {
            throw new InvalidSourceStructure("Too much arguments for operand");
        }

        for ($i = 1; $i <= $numArg; $i++) {
            $arg = $instruction->getElementsByTagName("arg$i")->item(0);

            if (!$arg) {
                throw new InvalidSourceStructure("Missing arguments for operand");
            }

            $args[] = [
                'value' => trim($arg->nodeValue),
                'type' => $arg->getAttribute('type'),
            ];
        }

        return $args;
    }

    /**
     * Extract value from symbol and check type
     * 
     * if required type is missing, then don't check type and only load value
     * 
     * @param array{0: string, 1: string|null, 2: string} $variableOrConst
     * @param string|null $requiredType
     */
    public function validateTypeAndReturnValue(array $variableOrConst, string $requiredType = null) : mixed{

        if($variableOrConst[2] == 'var'){
            list($type, $value) = $this->frames->getVarTypeAndValue($variableOrConst[0], $variableOrConst[1]);
        }

        else{
            $type = $variableOrConst[2];
            $value = $variableOrConst[0];
        }
        
        if (!is_null($requiredType) && $type != $requiredType){
            throw new OperandTypeError("Type of the symbol is incorrect");
        }

        if($type == 'string'){
            $value = StringMethod::replaceEscapeSequences($value);
        }

        return $value;
    }

    /**
     * Method for process Arithmetic instructions
     * 
     * In $type is string for choose type of operation, if operation is done it stores the
     * value to the variable in first argument of instruction 
     * 
     */
    private function processArithmeticInstruction(DOMElement $instruction, string $type) : void{
        
        $args = $this->extractArguments($instruction, 3);

        list($frame, $nameId) = $this->extractFrameVariableType($args[0]);
        $variable1 = $this->extractFrameVariableType($args[1]);
        $variable2 = $this->extractFrameVariableType($args[2]);

        $value1 = $this->validateTypeAndReturnValue($variable1, 'int');       
        $value2 = $this->validateTypeAndReturnValue($variable2, 'int'); 
        
        $value = 0;
        switch($type){
            case 'add':
                $value = $value1 + $value2;
                break;
            case 'sub':
                $value = $value1 - $value2;
                break;
            case 'mul':
                $value = $value1 * $value2;
                break;
            case 'idiv':
                if($value2 == '0'){
                    throw new OperandValueError("Divide with 0 is not allowed, anywhere");
                }
                $value = intdiv($value1,$value2);
                break;
            default:
                throw new InternalError("processArithmeticInstruction - wrong using of function, unknown case");
        }

        $this->frames->replaceVar([$frame, $nameId, 'int'], [$value, '', 'int']);
    }

    private function processAdd(DOMElement $instruction) : void{
        
        $this->processArithmeticInstruction($instruction, 'add');
    }

    private function processSub(DOMElement $instruction) : void{

        $this->processArithmeticInstruction($instruction, 'sub');
    }

    private function processMul(DOMElement $instruction) : void{
        
        $this->processArithmeticInstruction($instruction, 'mul');
    }

    private function processIdiv(DOMElement $instruction) : void{
        
        $this->processArithmeticInstruction($instruction, 'idiv');
    }

    private function processMove(DOMElement $instruction): void{

        $args = $this->extractArguments($instruction, 2);

        $frameAndVariable = $this->extractFrameVariableType($args[0]);
        $constantAndType = $this->extractFrameVariableType($args[1]);
        
        $this->frames->replaceVar($frameAndVariable,$constantAndType);

        if($this->frames->getVarValue($frameAndVariable[0],$frameAndVariable[1]) === null){
            throw new ValueError("Variable exist but not initialized");
        }
        
        return; 
    }

    private function processDefvar(DOMElement $instruction): void{

        $args = $this->extractArguments($instruction, 1);

        list($frame, $name) = $this->extractFrameVariableType($args[0]);

        $this->frames->loadVar($frame, $name);
    }

    /**
     * First have to check type of symbol what should be printed, choose correct method
     * for it
     */
    private function processWrite(DOMElement $instruction): void{

        $args = $this->extractArguments($instruction, 1);

        list($frameOrConst, $name, $type) = $this->extractFrameVariableType($args[0]);
    
        if ($type == 'var') {
            $type = $this->frames->getVarType($frameOrConst, $name);
            $value = $this->frames->getVarValue($frameOrConst, $name);
        } 
        
        else {
            $value = $frameOrConst;
        }

        //Different types of writeTYPE requested different input type
        $type = ucfirst($type);

        if($type == 'Var'){
            throw new InternalError("Error Processing write - var in type ");  
        }

        if ($type == 'Bool') {
            if ($value == "true"){
                $this->stdout->writeBool(true);
            }
            else{
                $this->stdout->writeBool(false);
            }
            return;
        }

        else if ($type == 'Int'){
            $value = (int) $value;
        }

        else if ($type == 'Nil') {
            $value = "";
            $this->stdout->writeString($value);
            return;
        }
        else if($type == 'String'){
            
            $value = StringMethod::replaceEscapeSequences($value);
            
            $this->stdout->writeString($value);
            
            return;
        }
        $type = "write" . $type;
        $this->stdout->$type($value);
    }

    private function processDprint(DOMElement $instruction): void{

        $args = $this->extractArguments($instruction, 1);

        list($frameOrConst, $name, $type) = $this->extractFrameVariableType($args[0]);
    
        if ($type == 'var') {
            $type = $this->frames->getVarType($frameOrConst, $name);
            $value = $this->frames->getVarValue($frameOrConst, $name);
        } 
        
        else {
            $value = $frameOrConst;
        }

        //Different types of writeTYPE requested different input type
        $type = ucfirst($type);

        if ($type == 'Bool') {
            if ($value == "true"){
                $this->stderr->writeBool(true);
            }
            else{
                $this->stderr->writeBool(false);
            }
            return;
        }

        else if ($type == 'Int'){
            $value = (int) $value;
        }

        else if ($type == 'Nil') {
            $value = "";
            $this->stderr->writeString($value);
            return;
        }
        else if($type == 'String'){
            
            $value = StringMethod::replaceEscapeSequences($value);
            
            $this->stderr->writeString($value);
            
            return;
        }
        $type = "write" . $type;
        $this->stderr->$type($value);
    }

    private function processBreak(DOMElement $instruction) : void{

        $topOfStack = $this->stack->top();
        $topOfStack = $topOfStack['value'];
        $message = "Actual order: $this->currentOrder,\n
                    last value on data stack: $topOfStack,\n
                    for more information please edit:
                    Interpret.php -> processBreak\n";


        $this->stderr->writeString($message);
    }

    private function processCreateframe(DOMElement $instruction): void{

        $this->frames->createFrame();
    }

    private function processPushframe(DOMElement $instruction): void{

        $this->frames->pushFrame();
    }

    private function processPopframe(DOMElement $instruction): void{

        $this->frames->popFrame();
    }

    private function processLabel(DOMElement $instruction) : void{
        //already done while loading instruction
    }

    private function processCall(DOMElement $instruction) : void{

        $arg = $this->extractArguments($instruction, 1);

        $label = $arg[0]['value'];

        if(!array_key_exists($label, $this->labels)){
            throw new SemanticError("CALL - label not defined");
        };

        //save next order number for Return instruction
        //not needed add +1 to the order, because main loop do it
        array_push($this->calls, $this->currentOrder);

        //jump to the label order number
        $this->currentOrder = $this->labels[$label];
    }

    private function processJump(DOMElement $instruction) : void{

        $arg = $this->extractArguments($instruction, 1);

        $label = $arg[0]['value'];

        if(!array_key_exists($label, $this->labels)){
            throw new SemanticError("JUMP - label not defined");
        };

        // jump to the label order number
        $this->currentOrder = $this->labels[$label];
    }

    /**
     * Helper method for comparing instructions (as GT, LT, EQ, NEQ)
     * 
     * In $operation is loaded type of operation for loaded symbols, if its valid,
     * return true, else false
     * 
     * Structure of method helps with easy expandable
     * 
     * @param array{value: string, type: string}  $symbol1
     * @param array{value: string, type: string}  $symbol2
     */
    private function compareSymbols($symbol1, $symbol2, string $operation) : bool {
       
        list($frameOrConst1, $name1, $type1) = $this->extractFrameVariableType($symbol1);
        list($frameOrConst2, $name2, $type2) = $this->extractFrameVariableType($symbol2);
        
        //If first symbol is variable, get into $type its type
        if($type1 == 'var' ){
            $type1Loaded = $this->frames->getVarType($frameOrConst1, $name1);
        }
        else{
            $type1Loaded = $type1;
        }
        if($type2 == 'var'){
            $type2Loaded = $this->frames->getVarType($frameOrConst2, $name2);
        }
        else{
            $type2Loaded = $type2;
        }

        if($type1Loaded == 'nil' || $type2Loaded == 'nil'){
            if($operation == 'eq'){
                return $type1Loaded == $type2Loaded;
            }
            elseif ($operation == 'neq') {
                return $type1Loaded != $type2Loaded;
            } 
            throw new OperandTypeError("Unsupported operation for nil type: $operation");
        }
        else{
            $value1 = $this->validateTypeAndReturnValue([$frameOrConst1, $name1, $type1]);
            $value2 = $this->validateTypeAndReturnValue([$frameOrConst2, $name2, $type2], $type1Loaded);
        }

        if ($operation == 'eq') {
            return $value1 == $value2;
        } 
        elseif ($operation == 'neq') {
            return $value1 != $value2;
        } 
        elseif ($operation == 'lt') {
            if ($type1Loaded == 'string'){
                return strcmp($value1, $value2) < 0;
            }
            //bool or int
            return $value1 < $value2;
        } 
        elseif ($operation == 'gt') {
            if ($type1Loaded == 'string'){
                return strcmp($value1, $value2) > 0;
            }
            //bool or int
            return $value1 > $value2;
        }

        throw new OperandTypeError("Unsupported operation: $operation");
    }

    private function processJumpifeq(DOMElement $instruction) : void{

        $args = $this->extractArguments($instruction, 3);
        
        $label = $args[0]['value'];

        if(!array_key_exists($label, $this->labels)){
            throw new SemanticError("JUMP - label not defined");
        };

        if($this->compareSymbols($args[1], $args[2], 'eq')){
            $this->currentOrder = $this->labels[$label];
        };
    }

    private function processLt(DOMElement $instruction) : void{

        $args = $this->extractArguments($instruction, 3);
        
        list($frame, $name, ) = $this->extractFrameVariableType($args[0]);

        $bool = false;
        if($this->compareSymbols($args[1], $args[2], 'lt')){
            $bool = true;
        };

        $this->frames->replaceVar([$frame, $name, ''], [$bool, '', 'bool']);
    }

    private function processGt(DOMElement $instruction) : void{

        $args = $this->extractArguments($instruction, 3);
        
        list($frame, $name, ) = $this->extractFrameVariableType($args[0]);

        $bool = false;
        if($this->compareSymbols($args[1], $args[2], 'gt')){
            $bool = true;
        };

        $this->frames->replaceVar([$frame, $name, ''], [$bool, '', 'bool']);
    }

    private function processEq(DOMElement $instruction) : void{

        $args = $this->extractArguments($instruction, 3);
        
        list($frame, $name, ) = $this->extractFrameVariableType($args[0]);

        $bool = false;
        if($this->compareSymbols($args[1], $args[2], 'eq')){
            $bool = true;
        };

        $this->frames->replaceVar([$frame, $name, ''], [$bool, '', 'bool']);
    }

    private function processAnd(DOMElement $instruction) : void{

        $args = $this->extractArguments($instruction, 3);
        
        list($frame, $name, ) = $this->extractFrameVariableType($args[0]);
        $frameAndVariable = $this->extractFrameVariableType($args[1]);
        $frameAndVariable2 = $this->extractFrameVariableType($args[2]);
        
        $value = $this->validateTypeAndReturnValue($frameAndVariable, 'bool');
        $value2 = $this->validateTypeAndReturnValue($frameAndVariable2, 'bool');

        $bool = false;

        if($value == 'true' && $value2 == 'true'){
            $bool = true;
        };

        $this->frames->replaceVar([$frame, $name, ''], [$bool, '', 'bool']);
    }

    private function processOr(DOMElement $instruction) : void{

        $args = $this->extractArguments($instruction, 3);
        
        list($frame, $name, ) = $this->extractFrameVariableType($args[0]);
        $frameAndVariable = $this->extractFrameVariableType($args[1]);
        $frameAndVariable2 = $this->extractFrameVariableType($args[2]);
        
        $value = $this->validateTypeAndReturnValue($frameAndVariable, 'bool');
        $value2 = $this->validateTypeAndReturnValue($frameAndVariable2, 'bool');

        $bool = true;

        if($value == 'false' && $value2 == 'false'){
            $bool = false;
        };

        $this->frames->replaceVar([$frame, $name, ''], [$bool, '', 'bool']);
    }

    private function processNot(DOMElement $instruction) : void{

        $args = $this->extractArguments($instruction, 2);
        
        list($frame, $name, ) = $this->extractFrameVariableType($args[0]);
        $frameAndVariable = $this->extractFrameVariableType($args[1]);
        
        $value = $this->validateTypeAndReturnValue($frameAndVariable, 'bool');

        $bool = true;

        if($value == 'true'){
            $bool = false;
        };

        $this->frames->replaceVar([$frame, $name, ''], [$bool, '', 'bool']);
    }

    private function processJumpifneq(DOMElement $instruction) : void{

        $args = $this->extractArguments($instruction, 3);
        
        $label = $args[0]['value'];

        if(!array_key_exists($label, $this->labels)){
            throw new SemanticError("JUMP - label not defined");
        };
        if($this->compareSymbols($args[1], $args[2], 'neq')){
            $this->currentOrder = $this->labels[$label];
        };
    }


    private function processReturn(DOMElement $instruction) : void{
        
        if(empty($this->calls)){
            throw new ValueError("Stack of calls is empty, couldn't return");
        }

        $this->currentOrder = array_pop($this->calls);
    }

    private function processPushs(DOMElement $instruction) : void{
        
        $args = $this->extractArguments($instruction, 1);
        list($frameOrConst, $name, $type) = $this->extractFrameVariableType($args[0]);

        $value = $frameOrConst;

        if ($type == 'var') {
            $value = $this->frames->getVarValue($frameOrConst, $name);
            $type = $this->frames->getVarType($frameOrConst, $name);
        }

        $this->stack->push($value, $type);
    }

    private function processPops(DOMElement $instruction) : void{
        
        $args = $this->extractArguments($instruction, 1);
        list($frameOrConst, $name, ) = $this->extractFrameVariableType($args[0]);

        $valueAndType = $this->stack->pop();

        $value = $valueAndType['value'];
        $type = $valueAndType['type'];

        $this->frames->replaceVar([$frameOrConst, $name, ''], [$value, '', $type]);
    }

    private function processExit(DOMElement $instruction) : void{
        
        $arg = $this->extractArguments($instruction, 1);

        $frameAndVariable = $this->extractFrameVariableType($arg[0]);
        $code = $this->validateTypeAndReturnValue($frameAndVariable, 'int');

        if (!preg_match(Regex::EXIT, $code)) {
            throw new OperandValueError("Exit code is allowed only in interval 0-9");
        }
    
        exit((int)$code);
    }

    private function processRead(DOMElement $instruction) : void{
        
        $args = $this->extractArguments($instruction, 2);

        $frameAndVariable= $this->extractFrameVariableType($args[0]);

        $typeForRead = ucfirst($args[1]['value']);

        if (!preg_match(Regex::TYPE, $args[1]['value'])){
            throw new InvalidSourceStructure("Read second argument should be only TYPE");
        }
        $readMethod = "read" . $typeForRead;

        $inputString = $this->input->$readMethod();

        $type = $args[1]['value'];

        //if readMethod detects error
        if(is_null($inputString)){
            $inputString = "nil";
            $type = "nil";
        }

        $this->frames->replaceVar($frameAndVariable,[$inputString, '', $type]);   
    }

    private function processInt2char(DOMElement $instruction) : void{
        
        $args = $this->extractArguments($instruction, 2);

        $frameAndVariable = $this->extractFrameVariableType($args[0]);
        $constantAndType = $this->extractFrameVariableType($args[1]);

        $frameOrConst = $constantAndType[0];
        $type = $constantAndType[2];

        $const = $frameOrConst;

        if ($type == 'var'){
            $const = $this->frames->getVarValue($frameOrConst, $constantAndType[1]);
            $type = $this->frames->getVarType($frameOrConst, $constantAndType[1]);
        }
        
        if(!($type == 'int')){
            throw new OperandTypeError("Integer for conversion required, but received: $type");
        }

        // valid unicode char from ASCII
        if ($const < 0 || $const > 255) {
            throw new StringOperationError("Number between 0-255 is allowed, but received: $const");
        }

        $char = StringMethod::int2char($const);

        $this->frames->replaceVar([$frameAndVariable[0], $frameAndVariable[1], ''], [$char, '', 'string']);
    }


    private function processConcat(DOMElement $instruction) : void{
        
        $args = $this->extractArguments($instruction, 3);

        list($frame, $nameId) = $this->extractFrameVariableType($args[0]);
        $variable1 = $this->extractFrameVariableType($args[1]);
        $variable2 = $this->extractFrameVariableType($args[2]);

        $value1 = $this->validateTypeAndReturnValue($variable1, 'string');       
        $value2 = $this->validateTypeAndReturnValue($variable2, 'string'); 
        
        $value = $value1.$value2;

        $this->frames->replaceVar([$frame, $nameId, ''], [$value, '', 'string']);
    }

    private function processStrlen(DOMElement $instruction) : void{
        
        $args = $this->extractArguments($instruction, 2);

        list($frame, $nameId) = $this->extractFrameVariableType($args[0]);
        $variable1 = $this->extractFrameVariableType($args[1]);

        $value1 = $this->validateTypeAndReturnValue($variable1, 'string');       
        
        $value = strlen($value1);

        $this->frames->replaceVar([$frame, $nameId, ''], [$value, '', 'int']);
    }

    private function processGetchar(DOMElement $instruction) : void{
        
        $args = $this->extractArguments($instruction, 3);

        list($frame, $nameId) = $this->extractFrameVariableType($args[0]);
        $variable1 = $this->extractFrameVariableType($args[1]);
        $variable2 = $this->extractFrameVariableType($args[2]);
        
        $value1 = $this->validateTypeAndReturnValue($variable1, 'string'); 
        $value2 = $this->validateTypeAndReturnValue($variable2, 'int');       
        
        if(strlen($value1) <= $value2 || 0 > $value2){
            throw new StringOperationError("GETCHAR - length of string is shorter then index");
        }
        
        $value = $value1[$value2];

        $this->frames->replaceVar([$frame, $nameId, ''], [$value, '', 'string']);
    }

    private function processSetchar(DOMElement $instruction) : void{
        
        $args = $this->extractArguments($instruction, 3);

        list($frame, $nameId, $type) = $this->extractFrameVariableType($args[0]);
        $variable1 = $this->extractFrameVariableType($args[1]);
        $variable2 = $this->extractFrameVariableType($args[2]);
        
        $value0 = $this->validateTypeAndReturnValue([$frame, $nameId, $type], 'string'); 
        $value1 = $this->validateTypeAndReturnValue($variable1, 'int');       
        $value2 = $this->validateTypeAndReturnValue($variable2, 'string'); 

        if((strlen($value0) <= $value1) || !strlen($value2) || ($value1 < 0) ){
            throw new StringOperationError("SETCHAR - length of string is shorter then index");
        }
        
        $value0[$value1] = $value2[0];

        $this->frames->replaceVar([$frame, $nameId, ''], [$value0, '', 'string']);
    }


    private function processType(DOMElement $instruction) : void{
        
        $args = $this->extractArguments($instruction, 2);

        list($frame, $nameId) = $this->extractFrameVariableType($args[0]);
        list($frameOrConst, $nameOrNull, $type) = $this->extractFrameVariableType($args[1]);

        if($type == 'var'){
            $type = $this->frames->getVarType($frameOrConst, $nameOrNull);
            if (is_null($type)){
                $type = "";
            }
        }

        $this->frames->replaceVar([$frame, $nameId, ''], [$type, '', 'string']);
    }



    private function processStri2int(DOMElement $instruction) : void{
        
        $args = $this->extractArguments($instruction, 3);

        list($frame, $nameId) = $this->extractFrameVariableType($args[0]);

        $variable1 = $this->extractFrameVariableType($args[1]);
        $variable2= $this->extractFrameVariableType($args[2]);

        $string = $this->validateTypeAndReturnValue($variable1, 'string');
        $index = $this->validateTypeAndReturnValue($variable2, 'int');

        if ($index < 0 || $index >= strlen($string)) {
            throw new StringOperationError("STRI2INT - length of string is shorter then index");
        }

        $ordinalValue = StringMethod::getOrdValueFromString($string, $index);

        $this->frames->replaceVar([$frame, $nameId, 'int'], [$ordinalValue, '', 'int']);
    }
    
}