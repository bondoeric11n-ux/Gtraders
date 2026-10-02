<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1 style='color:red;'>PHP Syntax Checker</h1>";
echo "<pre style='background:#000;color:#0f0;padding:20px; font-family: monospace;'>";

echo "Testing admin_settings.php for syntax errors...\n\n";

try {
    $code = file_get_contents('admin_settings.php');
    // This forces PHP to parse the file without actually running it
    token_get_all($code, TOKEN_PARSE);
    
    echo "✅ SUCCESS: No syntax errors found in admin_settings.php!\n";
    echo "This means the Error 500 is a 'runtime' error (happening when you click the button).\n";
} catch (ParseError $e) {
    echo "❌ SYNTAX ERROR FOUND!\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "Line Number: " . $e->getLine() . "\n";
    echo "\nPlease open admin_settings.php and go to this exact line number.\n";
    echo "Look for a missing bracket } or a stray character like a backtick ` \n";
} catch (Throwable $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}

echo "</pre>";
?>