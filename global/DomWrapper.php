<?php
class DomWrapper{
    private $NodeElement;

    public function __construct($NodeElement){
        if($NodeElement == null){
            throw new \Exception("Tried to create a ".__CLASS__." but argument passed in was null.\n\n".Backtrace());
        }
        if(!($NodeElement instanceof Behat\Mink\Element\NodeElement
            || $NodeElement instanceof Behat\Mink\Element\DocumentElement)){
            throw new \Exception("Tried to create a ".__CLASS__." but argument passed in was a ".get_class($NodeElement).".\n\n".BackTrace());
        }
        $this->NodeElement = $NodeElement;
    }

    public function Ancestor($Tag){
        $NodeElement = $this->NodeElement;
        do {
            $NodeElement = $NodeElement->getParent();
            if($NodeElement->getTagName() == $Tag){
                return new self($NodeElement);
            }
        } while($NodeElement != null);
        throw new \Exception("Could not find an ancestor with tag $Tag\n\n".Backtrace());
    }

    public function Attribute($Attribute){
        return $this->NodeElement->getAttribute($Attribute);
    }

    public function Blur(){
        return $this->NodeElement->blur();
    }

    public function Click(){
        # Try multiple times to avoid error: Element is not clickable at point
        $MaxAttempts = 25;
        for($i = 0; $i <= $MaxAttempts; $i++){
            try{
                $this->NodeElement->click();
                break;
            }catch(\Exception $e){
                echo "\nFailed to click on attempt $i\n";
                if($i == $MaxAttempts){
                    throw $e;
                }
                sleep(1);
            }
        }
    }

    public function DomsByCss($CssIdentifier){
        $Attempts = 10;
        $Doms = null;
        while($Attempts-- >= 0 && ($Doms == null || sizeof($Doms) == 0)){

            $Doms = $this->NodeElement->findAll('css', $CssIdentifier);

            # Just waiting for an extra second sometimes corrects issues with finding doms
            if($Doms == NULL || sizeof($Doms) == 0){
                sleep(1);
                if($Attempts == 0){
                    throw new \Exception("Doms with identifier [[".$CssIdentifier."]] were not found.\n".Backtrace());
                }
            }
        }

        # Wrap the doms
        $WrappedDoms = array();
        foreach($Doms as $NodeElement){
            $WrappedDoms[] = new self($NodeElement);
        }

        return $WrappedDoms;
    }

    public function DomBy(){
        $args = func_get_args();
        # If got one argument that's an array, wrap it accordingly
        if(sizeof($args) == 1 && is_array($args)){
            $args = $args[0];
        }

        $Doms = $this->DomsBy($args);
        return $Doms[0];
    }

    public function DomsBy(){ #*DTA TODO Simplifier's DomBy() should just call this, where the NodeElement is HTML or something. That way we don't have to support two sets of equivalent code.
        $args = func_get_args();

        # If got one argument that's an array, wrap it accordingly
        if(sizeof($args) == 1 && is_array($args)){
            $args = $args[0];
        }

        if(sizeof($args) == 0 || sizeof($args)%2 != 0){
            throw new \Exception("Arguments passed to ".__METHOD__." were improperly defined.\n\n".Backtrace());
        }

        $TagName = "*";
        $Xpath = "";
        $VisibleOnly = false;

        for($i=0;$i<sizeof($args);$i+=2){
            $Name = $args[$i];
            $Value = $args[$i+1];

            if($i>0 && $Name == "tag"){
                throw new \Exception(__METHOD__."If you define a tag, it must be the first argument.\n\n".Backtrace());
            }

            switch($Name){
                case "doesn't have class":
                    $Xpath .= "[not(contains(concat(' ', @class, ' '), ' $Value ')]";
                    break;
                case "has class":
                    $Xpath .= "[contains(concat(' ', @class, ' '), ' $Value ')]";
                    break;
                case "has text":
                    if(strpos($Value,'"') !== false && strpos($Value,'\'') !== false){
                        throw new \Exception(__METHOD__." Cannot pass quote AND apostrophe in text for 'has text'. Offending value: $Value\n\n".BackTrace());
                    }
                    #$Xpath .= "[contains(text(),'$Value')]";
                    if(strpos($Value,'\'') !== false){
                        $Xpath .= "[text()[contains(.,\"$Value\")]]";
                    } else {
                        $Xpath .= "[text()[contains(.,'$Value')]]";
                    }
                    break;
                case "id":
                    $Xpath .= "[@id='$Value']";
                    break;
                case "is text":
                    if(strpos($Value,'"') !== false && strpos($Value,'\'') !== false){
                        throw new \Exception(__METHOD__." Cannot pass quote AND apostrophe in text for 'is text'. Offending value: $Value\n\n".BackTrace());
                    }
                    if(strpos($Value,'\'') !== false){
                        $Xpath .= "[text()=\"$Value\"]";
                    } else {
                        $Xpath .= "[text()='$Value']";
                    }
                    break;
                case "name":
                    $Xpath .= "[@name='$Value']";
                    break;
                case "tag":
                    $TagName = $Value; 
                    break;
                case "type":
                    $Xpath .= "[@type='$Value']";
                    break;
                case "value":
                    if(strpos($Value,'"') !== false && strpos($Value,'\'') !== false){
                        throw new \Exception(__METHOD__." Cannot pass quote AND apostrophe in text for 'value'. Offending value: $Value\n\n".BackTrace());
                    }
                    #$Xpath .= "[contains(text(),'$Value')]";
                    if(strpos($Value,'\'') !== false){
                        $Xpath .= "[@value=\"$Value\"]";
                    } else {
                        $Xpath .= "[@value='$Value']";
                    }
                    break;
                case "visible only":
                    if(strtolower($Value) == "yes"){
                        $VisibleOnly = true;
                    } else {
                        throw new \Exception(__METHOD__." Value '$Value' is not valid for option 'visible only'.");
                    }
                    break;
                default:
                    throw new \Exception(__METHOD__." Name '$Name' is not recognized.\n\n".Backtrace());
            }
        }

        $Xpath = "//".$TagName.$Xpath;

        $Attempts = 20;
        $NodeElements = null;
        for($i = 1; $i <= $Attempts; $i++){
            try{
                $NodeElements = $this->NodeElement->findAll('xpath', $Xpath);
                if($NodeElements == null || (is_array($NodeElements) && sizeof($NodeElements) == 0)){
                    throw new \Exception("Throwing exception just to go into the CATCH statement");
                }
            } catch(\Exception $e){
                DebugEcho("\nWARNING: ".__FUNCTION__."() failed attempt ".$i." / ".$Attempts.". Sleeping to try again...\n");
                if($i >= $Attempts){
                    throw new \Exception($e->getMessage()."\nFailed to select element with identifier $Xpath\nArgs were ".print_r($args,true)."\n\n".Backtrace());
                }
                sleep(1);
            }
        }

        $Doms = array();
        foreach($NodeElements as $NodeElement){
            if($VisibleOnly){
                if($NodeElement->isVisible()){
                    $Doms[] = new self($NodeElement);
                }
            } else {
                $Doms[] = new self($NodeElement);
            }
        }
        return $Doms;
    }

    public function IsVisible(){
        return $this->NodeElement->isVisible();
    }

    # Returns a mink NodeElement
    public function NodeElement(){
        return $this->NodeElement;
    }

    public static function Page(){
        $NodeElement = \Simplifier::$F->getSession()->getPage();
        return new self($NodeElement);
    }

    public function SetValue($value){
        $MaxAttempts = 10;
        for($Attempts = 0; $Attempts <= $MaxAttempts; $Attempts++){
            try{
                switch($this->NodeElement->getTagName()){
                    case "a":
                        if($value == "click"){ $this->NodeElement->click(); }
                        else { throw new \Exception(__METHOD__.': Links do not accept value "'.$value.'".'); }
                        break;
                    case "input":
                        switch($this->NodeElement->getAttribute("type")){
                            case "checkbox":
                            case "radio":
                                $value = strtolower($value);
                                if(!$this->NodeElement->isChecked() && $value == "check"){ $this->NodeElement->click(); }
                                else if($this->NodeElement->isChecked() && $value == "uncheck"){ $this->NodeElement->click(); }
                                if($value != "check" && $value != "uncheck"){ throw new \Exception(__METHOD__.'(): Inputs of type checkbox do not accept value "'.$value.'".'); }
                                break;
                            case "password":
                            case "email":
                            case "tel":
                            case "text":
                                $this->NodeElement->setValue($value);
                                break;
                            case "file":
                                $AbsolutePath = FileUploadPath($value);
                                $this->NodeElement->AttachFile($AbsolutePath);
                                break;
                            default:
                                throw new \Exception(__METHOD__.': Inputs of type '.$this->NodeElement->getAttribute('type').' are not implemented yet');
                        }
                        break;
                    case "select":
                        $this->NodeElement->selectOption($value);
                        break;
                    case "textarea":
                        $this->NodeElement->setValue($value);
                        break;
                    default:
                        throw new \Exception(__METHOD__.': Elements of type '.$this->NodeElement->getTagName().' are not implemented yet');
                }
                break;
            } catch(\Exception $e){
                DebugEcho("Tried to set value but couldn't; trying again\n");
                if($Attempts == $MaxAttempts){
                    throw $e;
                }
                sleep(1);
            }
        }
    }

    public function Text(){
        return $this->NodeElement->getText();
    }
}