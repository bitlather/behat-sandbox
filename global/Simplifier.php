<?php
# This file makes writing tests much cleaner and easier to work with
class Simplifier{
    # Only code in this file should access these variables 
    public static 
        $F,                        # Feature context object
        $TestId,                   # Unicode timestamp that represents when scenario started to run
        $StartTimers,              # StartTimer() and StopTimer() use this
        $Memory;                   # Where Remember(), Recall(), etc store their values

#    public static function IncludeConstants(){
#        require(getcwd()."/constants/disabilities.php");
#        require(getcwd()."/constants/genders.php");
#        require(getcwd()."/constants/races.php");
#    }

    public static function Initialize($featureContext, $contextParameters, $suiteDirectory){
        date_default_timezone_set("America/New_York");

        self::$F = $featureContext;
        self::$Memory = array();
        self::$TestId = time();
        self::$StartTimers = array();

        ini_set('memory_limit','100M');

        # Include order matters
        self::IncludeDirectory('global');
        self::IncludeDirectory($suiteDirectory.'/bootstrap');
    }

    private static function IncludeDirectory($path) {
        $dir      = new RecursiveDirectoryIterator($path);
        $iterator = new RecursiveIteratorIterator($dir);
        foreach ($iterator as $file) {
            $fname = $file->getFilename();
                if (preg_match('%\.php$%', $fname)) {
                    require_once($file->getPathname());
                }
            }
    }
}


function GetAlertText(){
    # https://gist.github.com/benjaminlazarecki/2888851
    return \Simplifier::$F->getSession()->getDriver()->getWebDriverSession()->getAlert_text();
}


function AcceptAlert(){
    # Accept javascript alerts that pop up on page
    # https://gist.github.com/benjaminlazarecki/2888851
    \Simplifier::$F->getSession()->getDriver()->getWebDriverSession()->accept_alert();
}

function AssertAlertText($text){
    AssertEqual($text, GetAlertText(), "Alert message did not match expectations.");
}

function AssertEqual($expected, $actual, $description=''){
    if($expected != $actual){
        throw new \Exception("Expected value > $expected\ndid not equal\nactual value >   $actual\n$description");
    }
}

function AssertNotEqual($expected, $actual, $description=''){
    if($expected === $actual){
        throw new \Exception("Expected value > $expected\nShould not equal\nactual value >   $actual\nBut it did..\n$description");
    }
}

function AssertNotUrl($expected, $description=''){
    AssertNotEqual(CurrentDomain().$expected, CurrentUrl(), $description);
}

function AssertUrl($expected, $description=''){
    $Attempts = 20; # Seems Gmail needs this for verifying new trials
    for($i = 1; $i <= $Attempts; $i++){
        try{
            AssertEqual(CurrentDomain().$expected, CurrentUrl(), $description);
        } catch(\Exception $e){
            DebugEcho("\nWARNING: ".__FUNCTION__."() failed attempt ".$i." / ".$Attempts.". Sleeping to try again...\n");
            if($i >= $Attempts){
                throw new \Exception($e->getMessage()."\n\n".Backtrace());
            }
            sleep(1);
        }
    }
}

function AttachFile($cssIdentifier, $filePath){
    \Simplifier::$F->getSession()->getPage()->attachFileToField($cssIdentifier, $filePath);
}

function AssertPageDoesNotHaveText($text, $explanation='No additional explanation provided.'){
    $Attempts = 3;
    for($i = 1; $i <= $Attempts; $i++){
        try{
            \Simplifier::$F->assertPageNotContainsText($text);
        } catch(\Exception $e){
            DebugEcho("\nWARNING: ".__FUNCTION__."() failed attempt ".$i." / ".$Attempts.". Sleeping to try again...\n");
            if($i >= $Attempts){
                throw new \Exception($e->getMessage()."\n".$explanation."\n\n".Backtrace());
            }
            sleep(1);
        }
    }
}

function AssertPageHasText($text){
    # It seems that sometimes assertion fails because behat is too eager.
    # Give it a few attempts before you consider this a true failure.
    $Attempts = 20;
    for($i = 1; $i <= $Attempts; $i++){
        try{
            \Simplifier::$F->assertPageContainsText($text);
        }
        catch(\Exception $e){
            DebugEcho("\nWARNING: ".__FUNCTION__."() failed attempt ".$i." / ".$Attempts." for text"
                . " [[ ".$text." ]]."
                . " Trying again...\n");
            if($i >= $Attempts){
                throw new \Exception($e->getMessage()."\n".Backtrace());
            }
            sleep(1);
        }
    }
}

function CastArray($Hash){
    $Args = array();
    foreach($Hash as $Key => $Value){

        if(EndsWith($Key, " key")){

            $Key = substr($Key, 0, strlen($Key) - strlen(" key"));
            $Args[$Key] = Recall($Value);

        } else if(EndsWith($Key, " int")){
            $Key = substr($Key, 0, strlen($Key) - strlen(" int"));
            $IntegerToStringValue = ((integer)$Value)."";

            if($IntegerToStringValue !== $Value){
                throw new \Exception("Key $Key expected value to be integer but '$Value' was passed");
            }

            $Args[$Key] = (integer)$Value;

        } else if(EndsWith($Key, " bool")){

            $Key = substr($Key, 0, strlen($Key) - strlen(" bool"));
            switch($Value){
                case "true":  $Args[$Key] = true;  break;
                case "false": $Args[$Key] = false; break;
                case "null":  $Args[$Key] = null;  break;
                default:
                    throw new \Exception("Key $Key expects value to be {true,false,null} but '$Value' was passed.");
            }

        } else {

            $Args[$Key] = $Value;

        }
    }
    return $Args;
}

function CurrentDomain(){ //*lgm This exists somewhere within behat, but I am having trouble find it again.
    $url = \Simplifier::$F->getSession()->getCurrentUrl();
    return substr($url, 0, strpos($url, "/", 8));
}

function CurrentEnvironment(){
    #*CDT: For returning the domain only - no subdomains - primarily for widgets.feature
    $url = \Simplifier::$F->getMinkParameter('base_url');
    return substr($url, strpos($url,'.')+1); // Get everything from base_url after the first period
}

function CurrentRelativeUrl(){
    return substr(CurrentUrl(), strlen(CurrentDomain()));
}

function CurrentUrl(){
    return \Simplifier::$F->getSession()->getCurrentUrl();
}

function DebugEcho($Text){
    if(true){
        echo $Text;
    }
}

function DomBy(){
    $args = func_get_args();

    $NodeElement = \Simplifier::$F->getSession()->getPage();

    $Wrapper = WrapIt($NodeElement);

    return $Wrapper->DomBy($args);
}

function DomsBy(){
    $args = func_get_args();

    $NodeElement = \Simplifier::$F->getSession()->getPage();

    $Wrapper = WrapIt($NodeElement);

    return $Wrapper->DomsBy($args);
}

function DumpRemembered(){
    echo"\n";
    foreach(\Simplifier::$Memory as $Key => $Value){
        echo $Key," = ", $Value,"\n";
    }
}

#function Email($alias = '', $useTestId = true){
#    $alias = str_replace("'","",$alias);    # Do not use apostrophes in email
#    $alias = str_replace("\n","",$alias);
#    $alias = str_replace("é", "e", $alias);
#
#    if($useTestId){
#        $alias .= ($alias ? "_" : "" ) .TestId();
#    }
#    return 'sometrhing+'.strtolower($alias).'@gmail.com';
#}

# String comparison function
function EndsWith($haystack, $needle){
    $length = strlen($needle);
    if($length == 0){
        return true;
    }
    return (substr($haystack, -$length) === $needle);
}

function FileUploadPath($relativePath){
    return getcwd()."/fileUploads/".$relativePath;
}

function Forget(){
    $args = func_get_args();
    if(sizeof($args) == 0){
        throw new \Exception('Arguments passed to Forget() were improperly defined.');
    }
    for($i=0;$i<sizeof($args);$i++){
        unset(\Simplifier::$Memory[$args[$i]]);
    }
}

function Javascript($code){
    return \Simplifier::$F->getSession()->evaluateScript($code);
}

function Backtrace(){
    $BacktraceText = "";
    $BacktraceObject = debug_backtrace();
    foreach($BacktraceObject as $Step) {
        $BacktraceText .= sprintf("\nFILE: %s \n - FUNCTION: %s \n - LINE: %s", 
            OverrideDefault($Step, "file"     , ""),
            OverrideDefault($Step, "function" , ""),
            OverrideDefault($Step, "line"     , ""));
    }
    return $BacktraceText;
}

function OverrideDefault($Hash, $Key, $Default){
    if(isset($Hash[$Key])){
        return $Hash[$Key];
    }
    return $Default;
}

function Random($length){
    # Returns alphanumeric string
    $randstr = "";
    for($i=0; $i<$length; $i++){
        $randnum = mt_rand(0,61);
        if($randnum < 10){
            $randstr .= chr($randnum+48);
        }else if($randnum < 36){
            $randstr .= chr($randnum+55);
        }else{
            $randstr .= chr($randnum+61);
        }
    }
    return $randstr;
}

function Recall($name){
    if(!isset(\Simplifier::$Memory[$name])){
        throw new \Exception("[[$name]] was not defined or forgotten.\n".Backtrace());
    }
    return \Simplifier::$Memory[$name]['value'];
}

# arguments should be grouped in twos: 1. name, 2. value
function Remember(){
    $args = func_get_args();
    if(sizeof($args) == 0 || sizeof($args)%2 != 0){
        throw new \Exception('Arguments passed to Remember were improperly defined.');
    }
    for($i=0;$i<sizeof($args);$i+=2){
        $name = $args[$i];
        $value = $args[$i+1];
        if(strlen($value) == 0){
            #*DTA write this up. As an example, use theseApplicantsApplied().
            echo"WARNING: You tried to remember a null value in key '$name'."
            . "\n  It seems selenium does not always block as you may expect."
            . "\n  Ex: Apply to job. Query for prospect ID. Remember prospect ID."
            . "\n  In this case, the query may happen before the row exists."
            . "\n  Consider adding a loop that sleeps and retries your query until a value is returned."
            . "\n";
        }else{
            Variables::VerifyFormat($name);
        }
        if(isset(\Simplifier::$Memory[$name]) && Recall($name) != $value){
            throw new \Exception($name.' was already memorized as a different value. You must forget it, first, or use a different variable name. Trace of where this variable was last set: '.\Simplifier::$Memory[$name]['trace']);
        }
        \Simplifier::$Memory[$name] = array(
            'value' => $value,
            'trace' => '*DTA TODO store where this variable was set',
            );
    }
}

function TakeScreenshot($FileName){
    # http://classically.me/blogs/selenium-screenshot-behat-scenario-step-failure
    $Driver = \Simplifier::$F->getSession()->getDriver();
    file_put_contents($FileName, $Driver->getScreenshot());
    return $Driver->getScreenshot();
}

function TakeAndShowScreenshot(){
    $FileName = 'screenshot.png';
    TakeScreenshot($FileName);
    exec('open -a "Preview.app" ' . $FileName); # Show screenshot (works on osx only)
}

function ScrollTo($x, $y){
    \Simplifier::$F->getSession()->evaluateScript("window.scrollTo($x,$y);");
}

function SecretValue($Group, $Key){
    $ConstantsFilePath = "../behat_constants.json";

    if(!file_exists($ConstantsFilePath)){
        throw new \Exception("$ConstantsFilePath does not exist. All secret values must be stored here.");
    }

    $ConstantsString = file_get_contents($ConstantsFilePath);
    $Constants = json_decode($ConstantsString, true);

    if(!isset($Constants[$Group]) || !isset($Constants[$Group][$Key])){
        throw new \Exception("Secret value was not found."
            ."\n$ConstantsFilePath"
            ."\nExpected value: { \"$Group\" : { \"$Key\" } }");
    }

    return $Constants[$Group][$Key];
}

function SendEmail($To, $From, $Subject, $Body, $Headers=''){
    $Headers = "From: $From\r\n"
        .$Headers."\r\n"
        ."MIME-Version: 1.0\r\n"
        ."Content-Type: text/html; charset=ISO-8859-1\r\n";
    mail($To, $Subject, $Body, $Headers);
}

# String comparison function
function StartsWith($haystack, $needle){
    return !strncmp($haystack, $needle, strlen($needle));
}

function StartTimer($TimerName){
    if(isset(\Simplifier::$StartTimers[$TimerName])){
        throw new \Exception("Timer $TimerName was already started.");
    }
    \Simplifier::$StartTimers[$TimerName] = microtime(true);
}

function StopTimer($TimerName){
    if(!isset(\Simplifier::$StartTimers[$TimerName])){
        throw new \Exception("Timer $TimerName was never started.");
    }
    $EndTime = microtime(true);
    $StartTime = \Simplifier::$StartTimers[$TimerName];
    $Seconds = round($EndTime - $StartTime, 3);
    Remember("Timer: $TimerName", "$Seconds sec");
}

function StringContains($haystack, $needle){
    return strpos($haystack, $needle) !== FALSE;
}

function SwitchToIFrame($iFrameName){
    $MaxAttempts = 15;
    for($Attempts = 0; $Attempts <= $MaxAttempts; $Attempts++){
        try{
            \Simplifier::$F->getSession()->switchToIFrame($iFrameName);
            break;
        } catch (\Exception $e){
            if($Attempts == $MaxAttempts){
                throw $e;
            }
            DebugEcho("\nFailed to switch to iframe '$iFrameName'; sleep and try again...\n");
            sleep(1);
        }
    }
}

function SwitchToWindow($windowName){
    \Simplifier::$F->getSession()->switchToWindow($windowName);
}

function TestId(){
    return \Simplifier::$TestId;
}

function Visit($url){
    \Simplifier::$F->visit($url);
}

function Wait($seconds){
    sleep( $seconds );
}

function WaitForAjax(){
    \Simplifier::$F->getSession()->wait(10, '(0 === jQuery.active)');
}

function WaitForCKEditor(){
    # It seems that when testing in Chrome, CKEditor takes ~5 seconds to load
    # Give it a few attempts before you consider this a true failure.
    $Attempts = 20;
    for($i = 1; $i <= $Attempts; $i++){
        try{
            $CKEditor = DomsBy("tag"      , "iframe",
                               "has class", "cke_wysiwyg_frame" );
        }
        catch(\Exception $e){
            DebugEcho("\nWARNING: ".__FUNCTION__."() failed attempt ".$i." / ".$Attempts." for text"
                . " [[ ".$text." ]]."
                . " Trying again...\n");
            if($i >= $Attempts){
                throw new \Exception($e->getMessage()."\n".Backtrace());
            }
            sleep(1);
        }
    }
}

function WaitForDom(){
    # Replace AssertPageHasText or WaitUntilPageHasText with this, so you can optimize checks.

    $args = func_get_args();

    $NodeElement = \Simplifier::$F->getSession()->getPage();

    $Wrapper = WrapIt($NodeElement);

    return $Wrapper->DomBy($args);
}

function WaitUntilNotEqual($Value1, $Value2, $ErrorMessage){
    $SecondsToWait = 60;
    $OneTenthOfASecond = 100000;
    $StartTime = time();

    while(true){
        $TimeSinceStart = time() - $StartTime;
        if($Value1 != $Value2){
            return;
        }
        if($TimeSinceStart > $SecondsToWait){
            throw new \Exception("Waiting for \n  $Value1 != $Value2\nfailed after about $SecondsToWait seconds.\n\n$ErrorMessage");
        }
        try{
            AssertPageHasText($Text); # Note that this adds some overhead to the test so it should always take more than $Seconds seconds to fail
            return;
        } catch(\Exception $e){
            usleep($OneTenthOfASecond);
        }
    }
}

function WaitUntilPageHasText($Text, $SecondsToWait = 10){
    $OneTenthOfASecond = 100000;
    $StartTime = time();

    while(true){
        $TimeSinceStart = time() - $StartTime;
        if($TimeSinceStart > $SecondsToWait){
            throw new \Exception("Waiting for text \n  $Text\nfailed after about $SecondsToWait seconds.");
        }
        try{
            AssertPageHasText($Text); # Note that this adds some overhead to the test so it should always take more than $Seconds seconds to fail
            return;
        } catch(\Exception $e){
            usleep($OneTenthOfASecond);
        }
    }
}

function WrapIt($NodeElement){#*DTA PUT IN SIMPLIFIER.MD
    return new \DomWrapper($NodeElement);
}