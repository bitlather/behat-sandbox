<?php
# This file makes writing tests much cleaner and easier to work with
class Simplifier{
    # Only code in this file should access these variables 
    public static 
        $F,                        # Feature context object
        $ApiV1BaseUrl,             # Base URL for all API v1 (swagger) requests
        $ApiV2BaseUrl,             # Base URL for all API v2 requests
        $ApiVerifiesSsl,           # Whether API curl requests should verify SSL
        $TestId,                   # Unicode timestamp that represents when scenario started to run
        $StartTimers,              # StartTimer() and StopTimer() use this
        $Memory,                   # Where Remember(), Recall(), etc store their values
        $CanQueryDatabase = false, # Can test directly query the database?
        $dbc,                      # Database connection
        $ExistingAccountEmail,     # Who to log in as for running tests on an account that exists
        $ExistingAccountPassword;  # Who to log in as for running tests on an account that exists

    #*DTA when a customer exists test is run, verify it doesnt say something went wrong or don't use a gmail address. If don't use a gmail address, tell user to turn on test transactions. If something went wrong, tell user to verify migrations. PLAN: verify url after confirm is sent. If fail, tell user that they need to turn on test transactions to disable the spam blocker AND they may need to update their migration.

    public static function IncludeConstants(){
        require(getcwd()."/constants/disabilities.php");
        require(getcwd()."/constants/genders.php");
        require(getcwd()."/constants/races.php");
    }

    public static function Initialize($featureContext, $contextParameters, $suiteDirectory){
        date_default_timezone_set("America/New_York");

        self::$F = $featureContext;
        self::$Memory = array();
        self::$TestId = time();
        self::$StartTimers = array();

ini_set('memory_limit','100M'); # INCREASE MEMORY ALLOCATED TO RUNNING THIS TEST - TEMPORARY FIX

        # Necessary for API requests
        if(isset($contextParameters['api_v1_base_url'])){
            self::$ApiV1BaseUrl = $contextParameters['api_v1_base_url'];
        }
        if(isset($contextParameters['api_v2_base_url'])){
            self::$ApiV2BaseUrl = $contextParameters['api_v2_base_url'];
        }
        if(isset($contextParameters['api_verifies_ssl'])){
            self::$ApiVerifiesSsl = $contextParameters['api_verifies_ssl'];
        } else {
            self::$ApiVerifiesSsl = true;
        }

        # Set up database connection if allowed
        if(!isset($contextParameters['disable_db_query']) || !$contextParameters['disable_db_query']){
            $host = $contextParameters['db_host'];
            $user = $contextParameters['db_user'];
            $password = $contextParameters['db_password'];
            self::$dbc = new PDO('mysql:host='.$host.';dbname=resumator_db;', $user, $password);
            self::$dbc->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$CanQueryDatabase = true;
        }

        # Set up account to log into for running some tests
        if(isset($contextParameters['existing_account_email'], $contextParameters['existing_account_password'])){
            self::$ExistingAccountEmail    = $contextParameters['existing_account_email'];
            self::$ExistingAccountPassword = $contextParameters['existing_account_password'];
        }

        # Include order matters
        self::IncludeDirectory('global');
        self::IncludeDirectory($suiteDirectory.'/bootstrap_app');
        if(is_dir($suiteDirectory.'/bootstrap_api_v1')){
            self::IncludeDirectory($suiteDirectory.'/bootstrap_api_v1');
        }
        if(is_dir($suiteDirectory.'/bootstrap_api_v2')){
            self::IncludeDirectory($suiteDirectory.'/bootstrap_api_v2');
        }
        self::IncludeDirectory($suiteDirectory.'/bootstrap');
    }

    public static function ApiV1BaseUrl(){
        if(strlen(self::$ApiV1BaseUrl) == 0){
            throw new \Exception("API v1 base URL was not configured in behat.yml");
        }
        return self::$ApiV1BaseUrl;
    }

    public static function ApiV2BaseUrl($Email, $Password){
        if(strlen(self::$ApiV2BaseUrl) == 0){
            throw new \Exception("API v2 base URL was not configured in behat.yml");
        }

        if(!StartsWith(self::$ApiV2BaseUrl, "https://")){
            throw new \Exception("API v2 base URL must be configured in behat.yml to start with 'https://'");
        }

        $Hostname = substr(self::$ApiV2BaseUrl, strlen("https://"));

        $Url = "https://".urlencode($Email).":".urlencode($Password)."@".$Hostname;

        return $Url;
    }

    public static function ApiVerifiesSsl(){
        return self::$ApiVerifiesSsl;
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

define("API_ATTEMPTS", 5); #*DTA put elsewhere, maybe simplifier class
define("API_SLEEP_SECONDS", 3);

function GETv1($endpoint, $data = array()){#*DTA add to documentation
    $data["apikey"] = Recall("APIv1 Key"); #*DTA document that this gets passed automatically

    for($attempts = API_ATTEMPTS; $attempts > 0; $attempts--){

        $url = \Simplifier::ApiV1BaseUrl() . $endpoint;

        $curl = curl_init();

        $url = sprintf("%s?%s", $url, http_build_query($data));

        #*DTA make this better; use parts from POSTv1()
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, \Simplifier::ApiVerifiesSsl());
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, \Simplifier::ApiVerifiesSsl());

        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);

        $result = curl_exec($curl);

        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        curl_close($curl);

        //*DTA should probably move this sort of logic elsewhere for handling GET too, so it's all in one place
        if($status == 0 && strlen($result) == 0){
            throw new \Exception("Failed to GET. Perhaps there is an SSL issue on the server? You can ignore SSL issues in behat.yml.");
        }

        # If status is 503 then continue because I think it is a byproduct of rate limiting.
        if($status != 200 && $status != 503){
            throw new \Exception("Failed to GET. Http response code: ".$status);
        }

        if(strpos($result, "503 Service Temporarily Unavailable") === FALSE && strlen($result) > 0){ //*DTA could just check status=503, or move this above the check for 200
            # Return results if success
            $json = json_decode($result, true);
            if(json_last_error() != JSON_ERROR_NONE){
                throw new \Exception("Failed to GET. Result was not json. Result:\n\n".print_r($result,true));
            }
            return $json;
        }

        sleep(API_SLEEP_SECONDS);
    }
    # API request failed too many times
    throw new \Exception("GET attempt failed too many times.");
}

function GETv2($endpoint, $Email, $Password, $data = array()){
    if(StartsWith($endpoint, "/")){
        DebugEcho("WARNING: ".__METHOD__." You passed an unecessary slash '/' at the beginning of the endpoint. ");
        $endpoint = substr($endpoint, 1);
    }
    $url = \Simplifier::ApiV2BaseUrl($Email, $Password) . $endpoint;

    DebugEcho("GETv2 requests $url /// endpoint: $endpoint");

    #echo"URL: ",$url,"\n";
    $curl = curl_init();

    $url = sprintf("%s?%s", $url, http_build_query($data));

    #*DTA make this better; use parts from POSTv1()
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, \Simplifier::ApiVerifiesSsl());
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, \Simplifier::ApiVerifiesSsl());

    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);

    $result = curl_exec($curl);

    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

    curl_close($curl);

    #echo "STATUS: ",$status;
    #echo"\nRESULT: \n";print_r($result);

    $ResultJson = json_decode($result, true);
    if(json_last_error() != JSON_ERROR_NONE){
        throw new \Exception("Failed to GET. Result was not json. Result:\n\n".print_r($result,true));
    }

    return array(
        "httpStatusCode" => $status,
        "result" => $ResultJson);
}

function DELETEv2($endpoint, $Email, $Password, $data = array()){
    $url = \Simplifier::ApiV2BaseUrl($Email, $Password) . $endpoint;

    #echo"URL: ",$url,"\n";

    $curl = curl_init();

    #$url = sprintf("%s?%s", $url, http_build_query($data));

    #echo"URL: ",$url,"\n";

    $data_string = json_encode(array()); # For some reason our API requires data be sent, like a POST request
    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'Content-Length: ' . strlen($data_string)));
    curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
    curl_setopt($curl, CURLOPT_POST, TRUE);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);

    #*DTA make this better; use parts from POSTv1()
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, \Simplifier::ApiVerifiesSsl());
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, \Simplifier::ApiVerifiesSsl());

    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "DELETE");
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);

    $result = curl_exec($curl);

    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

    curl_close($curl);

    #echo "STATUS: ",$status;
    #echo"\nRESULT: \n";print_r($result);

    $ResultJson = json_decode($result, true);
    if(json_last_error() != JSON_ERROR_NONE){
        throw new \Exception("Failed to DELETE. Result was not json. Result:\n\n".print_r($result,true));
    }

    return array(
        "httpStatusCode" => $status,
        "result" => $ResultJson);
}

function POSTv1($endpoint, $data = array()){
    $data["apikey"] = Recall("APIv1 Key"); #*DTA document that this gets passed automatically
    for($attempts = API_ATTEMPTS; $attempts > 0; $attempts--){

        $url = \Simplifier::ApiV1BaseUrl() . $endpoint;

        $curl = curl_init();

        $data_string = json_encode($data);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($data_string)));
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
        curl_setopt($curl, CURLOPT_POST, TRUE);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);

        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, \Simplifier::ApiVerifiesSsl());
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, \Simplifier::ApiVerifiesSsl());

        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);

        $result = curl_exec($curl);

        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

#echo"POST STATUS ",$status,"\n";
#echo"POST RESULT ";print_r($result);echo"\n";

        //*DTA should probably move this sort of logic elsewhere for handling GET too, so it's all in one place
        if($status == 0 && strlen($result) == 0){
            throw new \Exception("Failed to POST. Perhaps there is an SSL issue on the server? You can ignore SSL issues in behat.yml.");
        }

        # If status is 503 then continue because I think it is a byproduct of rate limiting.
        if($status != 200 && $status != 503){
            throw new \Exception("Failed to POST. Http response code: ".$status);
        }

        curl_close($curl);

        if(strpos($result, "503 Service Temporarily Unavailable") === FALSE && strlen($result) > 0){ //*DTA could just check status=503, or move this above the check for 200
            # Return results if success
            $json = json_decode($result, true);
            if(json_last_error() != JSON_ERROR_NONE){
                throw new \Exception("Failed to POST. Result was not json.");
            }
            return $json;
        }

        sleep(API_SLEEP_SECONDS);
    }
    # API request failed too many times
    throw new \Exception("POST attempt failed too many times.");
}

function POSTv2($endpoint, $Email, $Password, $data = array()){
    $url = \Simplifier::ApiV2BaseUrl($Email, $Password) . $endpoint;

    #echo"URL: ",$url,"\n";

    $curl = curl_init();

    $data_string = json_encode($data);
    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'Content-Length: ' . strlen($data_string)));
    curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
    curl_setopt($curl, CURLOPT_POST, TRUE);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);

    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, \Simplifier::ApiVerifiesSsl());
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, \Simplifier::ApiVerifiesSsl());

    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);

    $result = curl_exec($curl);

    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

    #echo "STATUS: ",$status;
    #echo"\nRESULT: \n";print_r($result);

    $ResultJson = json_decode($result, true);
    if(json_last_error() != JSON_ERROR_NONE){
        throw new \Exception("Failed to POST. Result was not json. Result:\n\n".print_r($result,true));
    }

    return array(
        "httpStatusCode" => $status,
        "result" => $ResultJson);
}

// Do a cURL call to GET, POST, PUT, or DELETE over HTTP(S)
//
// $url: e.g. https://api.theresumator.com
// $method: 'POST', 'PUT', 'DELETE', or 'GET'
// $data: array("param" => "value") ==> index.php?param=value
// $json_encoding: TRUE if json_encode on outbound and json_decode on inbound
function curl_call($method, $url, $data = FALSE, $json_encoding=FALSE) {
#*DTA not used anywhere... can we kill this?
    //*DTA TODO fix up to match other functions then remove this.
    $curl = curl_init();

    switch ($method) {

    case 'POST':
        if ($data) {
            $data_string = json_encode($data);
            curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json',
                                                  'Content-Length: ' . strlen($data_string)));
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
            curl_setopt($curl, CURLOPT_POST, TRUE);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
        } else {
            return "{'error':'Data must be provided for a POST'}";
        }
        break;

     case 'PUT':
        curl_setopt($curl, CURLOPT_PUT, TRUE);
        break;
 
     case 'DELETE':
        if($data)
        {
            $data_string = json_encode($data);
            curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json',
                                                  'Content-Length: ' . strlen($data_string)));
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }else {
            return "{'error':'Data must be provided for a DELETE'}";
        }
        break;
 
     default:  // Including 'GET'
        if ($data) {
            $url = sprintf("%s?%s", $url, http_build_query($data));
        }
    }
    //die($url);
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
 
     $curl_return = curl_exec($curl);
  curl_close($curl);
 
    if ($json_encoding) {
        return json_decode($curl_return, TRUE);
    } else {
        return $curl_return;
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
    if($expected != $actual){ #*DTA should be !==
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

function Click($CssIdentifier){
    if(!is_string($CssIdentifier) && get_class($CssIdentifier) == "Behat\Mink\Element\NodeElement"){
        # If someone passed a NodeElement instead of an identifier...
        WrapIt($CssIdentifier)->Click();
        return;
    }
    if(!is_string($CssIdentifier)){
        throw new \Exception("Incorrect parameter type passed to Click() function.");
    }
#*DTA TODO WE SHOULD SPIN UNTIL THE DOM IS VISIBLE.
#Now, we sometimes have to do a WaitUntilDomIsVisible(), like here:
    #     WaitUntilDomIsVisible(".job-dropdown");
    #     Click(".job-dropdown");
    #
    Dom($CssIdentifier)->Click();
}

function CurrentDomain(){ //*lgm This exists somewhere within behat, but I am having trouble find it again.
    $url = \Simplifier::$F->getSession()->getCurrentUrl();
    return substr($url, 0, strpos($url, "/", 8));
}

function CurrentEnvironment(){
    #*CDT: For returning the domain only - no subdomains - primarily for widgets.feature
    $url = \Simplifier::$F->getMinkParameter('base_url');
    DebugEcho("\n*DTA need to document this in .md AND verify substr stuff is ok\n");
    return substr($url, strpos($url,'.')+1); // Get everything from base_url after the first periodß
}

function CurrentRelativeUrl(){
    return substr(CurrentUrl(), strlen(CurrentDomain()));
}

function CurrentUrl(){
    return \Simplifier::$F->getSession()->getCurrentUrl();
}

function DebugEcho($Text){#*DTA document in md
    if(true){
        echo $Text;
    }
}

function Dom($CssIdentifier){
    $NodeElement = \Simplifier::$F->getSession()->getPage();

    $Wrapper = WrapIt($NodeElement);

    $WrappedDoms = $Wrapper->DomsByCss($CssIdentifier);

    return $WrappedDoms[0];
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

function DomByTextContains($TagName, $Text){ #*DTA DELETE!!!!
    # $TagName   Type of element, ie "div" or "a"
    # $Text      Text to search for
    $doms = DomsByTextContains($TagName, $Text);
    return $doms[0];
}
function DomsByTextContains($tagName, $text, $SecondsToWait = 15){ #*DTA TODO CONVERT TO DomWrapper
    # $tagName        Type of element, ie "div" or "a"
    # $text           Text to search for
    # $SecondsToWait  How many seconds to wait for dom
    if(strlen($text) == 0){
        throw new \Exception("Text argument is missing. Did you accidentally pass the text to tagName parameter?");
    }

    $OneTenthOfASecond = 100000;
    $StartTime = time();
    $Selector = '//'.$tagName.'[contains(text(),"'.$text.'")]';  //*DTA probably has problems if selecting text with quotes in it

    while(true){
        $Doms = \Simplifier::$F->getSession()->getPage()->findAll('xpath', $Selector);
        $TimeSinceStart = time() - $StartTime;
        if($TimeSinceStart > $SecondsToWait){
            throw new \Exception("Waiting for text \n  $text\nfailed after about $SecondsToWait seconds.\n\n".Backtrace());
        }
        try{
            $set = array();
            foreach($Doms as $dom){
                if($dom->isVisible()){
                    $set[] = $dom;
                }
            }
            if(sizeof($set) > 0){ #DANGEROUS!!!! But we rely on it for clicking save button when create workflow...
                return $set;
            }
            usleep($OneTenthOfASecond);
        } catch(\Exception $e){
            usleep($OneTenthOfASecond);
        }
    }
}
function Doms($CssIdentifier){
    $NodeElement = \Simplifier::$F->getSession()->getPage();

    $Wrapper = WrapIt($NodeElement);

    return $Wrapper->DomsByCss($CssIdentifier);
}

function DumpRemembered(){//*DTA PUT IN SIMPLIFIER.MD
    echo"\n";
    foreach(\Simplifier::$Memory as $Key => $Value){
        echo $Key," = ", $Value,"\n";
    }
}

function Email($alias = '', $useTestId = true){ //*DTA grep resumator.automated.tests and replace with this function
    $alias = str_replace("'","",$alias); // Do not use apostrophes in email
    $alias = str_replace("\n","",$alias);
    $alias = str_replace("é", "e", $alias);

    if($useTestId){
        $alias .= ($alias ? "_" : "" ) .TestId();
    }
    return 'b+'.strtolower($alias).'@theresumator.com'; # Emails are stored in database as lowercase, anyway, so this helps with validating API, etc

    // FOR LOGGING INTO GMAIL: return 'resumator.automated.tests+'.TestId().'_'.$alias.'@gmail.com';
}

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

# arguments should be grouped in twos: 1. css identifier, 2. value
function FormFill(){
    $args = func_get_args();
    if(sizeof($args) == 0 || sizeof($args)%2 != 0){
        throw new \Exception(__FUNCTION__.'(): Arguments passed to fill were improperly defined.');
    }
    for($i=0;$i<sizeof($args);$i+=2){
        $Dom = Dom($args[$i+0]);
        $Value = $args[$i+1];
        $Dom->SetValue($Value);
    }
}

function Javascript($code){
    \Simplifier::$F->getSession()->evaluateScript($code);
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

function Query($query,$arguments){
    if(!\Simplifier::$CanQueryDatabase){
        throw new \Exception("You cannot query the database using your selected behat profile.");
    }
    $prepare = \Simplifier::$dbc->prepare($query);
    $prepare->execute($arguments);
    # Use ->fetch() or ->fetchAll() to get values
    return $prepare;
}

function Random($length){
    # Ripped from developurr
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
            'trace' => '*DTA TODO store where this variable was set',//TODO
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

function UniqueFemaleName(){
    return \Name::GetUniqueFemaleName();
}

function UniqueMaleName(){
    return \Name::GetUniqueMaleName();
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
#TODO PUT IN SIMPLIFIER! 
# AND PUT IN DOMWRAPPER WITH ABILITY TO SET ERROR MESSAGE! 
#Replace AssertPageHasText or WaitUntilPageHasText with this, so you can optimize checks.

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

function WaitUntilPageHasTextOr($TextArray, $SecondsToWait = 10){
    #*DTA This is a terrible waste of time because it will assert the first one 20 times before trying to assert the next one
    #*DTA This is a terrible waste of time because it will assert the first one 20 times before trying to assert the next one
    #*DTA This is a terrible waste of time because it will assert the first one 20 times before trying to assert the next one

    $OneTenthOfASecond = 100000;
    $StartTime = time();

    while(true){
        $TimeSinceStart = time() - $StartTime;
        if($TimeSinceStart > $SecondsToWait){
            throw new \Exception("Waiting for any text in \n  ".print_r($TextArray,true)."\nfailed after about $SecondsToWait seconds.");
        }

        foreach($TextArray as $Text){
            try{
                AssertPageHasText($Text); # Note that this adds some overhead to the test so it should always take more than $Seconds seconds to fail
                return;
            } catch(\Exception $e){
            }
            usleep($OneTenthOfASecond);
        }
    }
}

function WaitUntilDomIsText($Dom, $Text, $SecondsToWait = 10){
    $OneTenthOfASecond = 100000;
    $StartTime = time();

    while(true){
        $TimeSinceStart = time() - $StartTime;
        if($TimeSinceStart > $SecondsToWait){
            throw new \Exception("Waiting for text \n  $Text\nfailed after about $SecondsToWait seconds. The dom ended with containing text:\n".$Dom->getText());
        }
        if($Dom->getText() == $Text){
            return;
        }
        usleep($OneTenthOfASecond);
    }
}

function WaitUntilDomIsVisible($cssIdentifier, $SecondsToWait = 10){
    $OneTenthOfASecond = 100000;
    $StartTime = time();

    while(true){
        $TimeSinceStart = time() - $StartTime;
        if($TimeSinceStart > $SecondsToWait){
            throw new \Exception("Waiting for dom \n  $cssIdentifier\nto be visible failed after about $SecondsToWait seconds.");
        }
        $Dom = \Simplifier::$F->getSession()->getPage()->find('css', $cssIdentifier);
        if($Dom != null && $Dom->isVisible()){
            return;
        }
        usleep($OneTenthOfASecond);
    }
}
function WrapIt($NodeElement){#*DTA PUT IN SIMPLIFIER.MD
    return new \DomWrapper($NodeElement);
}