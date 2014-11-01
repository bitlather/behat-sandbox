Simplifier
==========

The simplifier makes writing common tasks easier and improves readability. The simplifier can only be used when writing PHP code that is tied to step definitions. 

For the most accurate information, review `Simplifier.php`.

If you find yourself writing too much code without the help of these functions then suggest we add it to the simplifier!

**Important note about $cssIdentifier**

When passing a cssIdentifier, to select a dom by attribute where the value contains `@`, `.`, `[` or `]`, simply encapsulate the value in single quotes. For example, to select `<div name="to[email][b@theresumator.com]">` you would pass `"[name='to[email][b@theresumator.com]']"`.


Alerts
------
See assertions for alert specific assertions.

#### AcceptAlert()
Clicks ok in a javascript alert popup.

#### GetAlertText()
Returns the message in a javascript alert popup.


Assertions
----------
#### AssertAlertText()
Asserts the message in a javascript alert popup.

```php
$text          # Expected text.
```

#### AssertEqual()
Asserts two things are equal.

```php
$expected      # Expected value.
$actual        # Actual value.
$description   # Descriptive message to show if assertion fails.
```

#### AssertNotEqual()
Asserts two things are not equal.

```php
$expected      # Expected value.
$actual        # Actual value.
$description   # Descriptive message to show if assertion fails.
```

#### AssertPageDoesNotHaveText()
Asserts the text is currently not present on the page.

```php
$text          # Unexpected text.
$explanation   # Appears if assertion fails to provide more context.
```

#### AssertPageHasText()
Asserts the text is currently present on the page. Test will run faster if you use functions like `WaitUntilDomIsText()` instead

```php
$text      # Expected text.
```

#### AssertNotUrl()
Asserts the current url is not some value..

```php
$expected      # Expected relative url. 
               # Example: "\app"
$description   # Error message to display if assertion fails.
```

#### AssertUrl()
Asserts the current url.

```php
$expected      # Expected relative url. 
               # Example: "\app"
$description   # Error message to display if assertion fails.
```

Debug
-----
#### Backtrace()
Returns a string backtrace.

Sample output:

      FILE: global/Simplifier.php 
       - FUNCTION: Backtrace 
       - LINE: 464
      FILE: smoke/bootstrap/EmailReport.php 
       - FUNCTION: Recall 
       - LINE: 42

#### DebugEcho()
Echoes debug text if switch turned on. Otherwise, suppress messages so output is easier to read.

```php
$Text       # Text to echo if DebugEcho is turned on
```

Dom elements
------------
These functions return a NodeElement object.

The NodeElement API: http://mink.behat.org/api/behat/mink/element/nodeelement.html

#### DomBy()
Returns the first dom that matches your identifier.

Takes any number of argument pairs.

    "tag"                 What tag you are selecting; "*" by default
    "doesn't have class"  Node does not have class
    "has text"            Node contains this text
    "has class"           Node has this class
    "id"                  Node has this id
    "is text"             Node is exactly this text
    "name"                Node has this name
    "visible only"        Only return nodes that are visible. Note this function will wait until at least one node is visible, and fail if zero are visible.
    
Example:

```php
DomBy(
    "tag"       , "span"           ,
    "has class" , "workflow-step"  ,
    "has text"  , "Phone Screened" );
```

#### DomsBy()
Returns all doms that match your identifier.

Takes any number of argument pairs.

    "tag"                 What tag you are selecting; "*" by default
    "doesn't have class"  Node does not have class
    "has text"            Node contains this text
    "has class"           Node has this class
    "id"                  Node has this id
    "is text"             Node is exactly this text
    "name"                Node has this name
    "visible only"        Only return nodes that are visible. Note this function will wait until at least one node is visible, and fail if zero are visible.

Example:

```php
DomBy(
    "tag"       , "span"           ,
    "has class" , "workflow-step"  ,
    "has text"  , "Phone Screened" );
```

Email
-----
#### Email()
Returns an email address that is tied to the email account used for automated testing. We can write code that logs into an email account and verifies emails were sent, so it is important that you always use this function when dealing with email addresses.

`Note:` Automatically removes apostrophes from $alias.

```php
$alias           # Alias that is appended to the email address.
$useTestId       # Appends TestId() to email to ensure uniqueness.
                 # Default is true. Pass false if you want prettier email addresses when uniqueness doesn't matter.
```

#### SendEmail()
Sends an email, assuming the machine the script runs on can call `mail()`.

```php
$To        # Recipient's email address
$From      # Sender's email address (anything you want)
$Subject   # Email subject
$Body      # HTML message
$Headers   # Special mail headers. Ex:
           #  "Reply-To: systems@theresumator.com"
           #  Note that multiple headers should be delimited with \r\n.
```

Files & uploads
---------------
#### AttachFile()
Attach a file to an input.

```php
$cssIdentifier   # A css path.
                 # Ex: #submit
                 # Ex: .container #submit
$filePath        # Path to file that will be attached.
```

#### FileUploadPath()
Returns an absolute path to a file in this repository.

```php
$relativePath    # Path to file. Use the file path that comes after behat/fileUploads/.
```

iFrames & windows
-----------------
The iFrame was tricky to select from the DOM while testing applying to a job with widget. Here is how you properly select an iFrame.

#### SwitchToIFrame()
Must pass iFrame name in order to be able to fill out forms & utilize DOM elements inside of it.

```php
$iFrameName    # Id/name of the iFrame (!!TODO VERIFY ID IS OK)
```

To click on a link inside of an iframe:

```php
SwitchToIFrame("iframe-name");
Click("#button-id");
```

#### SwitchToWindow()
Can be used to switch back from iframe.

```php
$windowName   # Name of window. Null to switch to parent of iframe.
```

Usage:

```php
SwitchToIFrame("iframe-name");
# Do stuff in iframe
SwitchToWindow(null);
# Do stuff in parent
```

Javascript
----------
#### Javascript()

Use this to execute javascript code. It can also return a value if you're expecting one.

```php
$code   # Javascript code. Be careful with quotes and newlines inside of quotes!
```


Memory
------
The memory functions were created to simplify working with scenarios across multiple steps. They are basically global variables.

#### DumpRemembered()
Echos every remembered key-value pair to show what keys you have access to at a specific point in the test. Good for debugging.

#### Remember()
Remembers a value. Throws an error if you try to remember two values by the same key. Also verifies keys against whitelisted values to maintain well named, reusable variables.

```php
$key     # Variable's name
$value   # Variable's value
```

You can remember multiple things with a single call:

```php
Remember(
  "Job Id: $JobName"         , $row['job_id']         ,
  "Job Board Code: $JobName" , $row['job_board_code'] );
```

#### Recall()
Reads a value. Throws an error if you try to recall a variable that is not defined.

```php
$key     # Variable's name
```

Example:

```php
Recall("Job Id: $JobName")
```

#### Forget()
Forgets a value. This should be used infrequently. For example, `Forget()` is good to use to forget the logged in user when the user signs out.

```php
$key     # Variable's name
```

Parameters
----------
#### OverrideDefault()
If an element exists in an array, return that value. Otherwise, return a default value.

Keeps code clean so we don't have to call a bunch of `isset()` functions. Is particularly useful with argument tables that have optional parameters for the test writer but required inputs from the app.

```php
$hash     # Associative array to check for a value
$key      # Key to look for inside of the hash
$default  # Value to return if $hash[$key] was not set
```

Example:

    OverrideDefault($Hash, "Job Type", "Full Time")
      If isset($Hash["Job Type"]) then return $Hash["Job Type"]
      Else return "Full Time"


Screenshots
-----------
#### TakeScreenshot()
Takes a screenshot.

```
$FileName   # Name of screenshot. End with .jpg or .png.
```

#### TakeAndShowScreenshot()
Takes a screenshot and displays it (Mac OSX only).

Scrolling
---------
#### ScrollTo()
Scrolls to a specific position on the page.

```php
$x    # X position
$y    # Y position
```

Secret values
-------------
#### SecretValue()
Returns a value that is hidden from the code repo.

This is especially useful for automating tests that log in to existing test accounts. For instance, we do not want to store special behat user passwords in our repository for security reasons. We store such values in a json file that is in the behat repo's parent directory. If someone wants to run a test that requires a secret value then they must find the value by asking other engineers and then add the key to their local behat constants file.

```php
$Group    # The first index.
$Key      # The second index.
```

Example json file:

```javascript
{
  "PasswordKeys": {
     "ADMIN_PASSWORD": "ThePassword",
     "USER_PASSWORD": "ThePassword"
     }
}
```

Example usage:

```php
$Password = SecretValue("PasswordKeys", "ADMIN_PASSWORD");
```

String manipulation
-------------------
#### EndsWith()
Returns true if a string ends with a sub string.

```php
$haystack   # String to search in.
$needle     # String to search for.
```

#### Random()
Returns a random alphanumeric string.

```php
$length   # How long the string should be.
```

#### StartsWith()
Returns true if a string starts with a sub string.

```php
$haystack   # String to search in.
$needle     # String to search for.
```

#### StringContains()
Returns true if a string contains another string.

```php
$haystack   # String to search in.
$needle     # String to search for.
```

Tables
------
#### CastArray()
Returns an array with values casted to the specified type. Especially useful for passing arguments to the API when a data type constraint exists.

```php
# INPUT

array(
    "argument1"       => "string value",
    "argument2a bool" => "true",
    "argument2b bool" => "false",
    "argument2c bool" => "null",
    "argument3 key"   => "User Id: Billy Idol"
    );

# OUTPUT

array(
    "argument1"  => "string value",
    "argument2a" => true,
    "argument2b" => false,
    "argument2c" => null,
    "argument3"  => # Value from Recall("User Id: Billy Idol")
    );
```

Test specific
-------------
#### TestId()
Returns the currently running scenario's test ID.


Timers
------
#### StartTimer()
Starts a timer.

```php
$TimerName   # Name of timer, for later reference. Every timer in a run must have a unique name.
```

#### StopTimer()
Stops a timer and calls `Remember("Timer: $TimerName")` for later reference.

```php
$TimerName   # Name of timer that was started earlier.
```

Urls
----
See assertions for url specific assertions.

#### CurrentDomain()
Returns the domain, ie `http://localhost:8080`

#### CurrentRelativeUrl()
Returns the URL after the domain, ie /app/resumes.

#### CurrentUrl()
Return the url for the currently visited page.

#### Visit()
Visit a page.

```php
$url   # What page to visit.
```

Waiting
-------
#### Wait()
Pause the test.

#### WaitForAjax()
Pause the test until all ajax requests have completed.

#### WaitForCKEditor()
Wait for ckeditor to load.

#### WaitUntilPageHasText()
Wait until some text appears on the page. This function sleeps a small amount of time and polls the page's contents until the text is found or too much time has elapsed. This is better than a simple `Wait()` or `sleep()` because they force the browser to pause the full amount of time specified.

DomBy() is a little faster, because you can wait for a specific dom, which means the entire page isn't searched.

```php
$Text            # Text to wait for
$SecondsToWait   # Number of seconds to wait. Note that the function may
                 # take longer than this to fail.
```
