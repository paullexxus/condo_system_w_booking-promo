<?php
header('Content-Type: text/plain');

$file = 'login.php';
$content = file_get_contents($file);

echo "File: $file\n";
echo "Size: " . strlen($content) . " bytes\n";
echo "Lines: " . count(file($file)) . "\n\n";

// Try to parse it
$tokens = @token_get_all($content);

if ($tokens === false) {
    echo "ERROR: Could not tokenize file\n";
} else {
    echo "Tokens found: " . count($tokens) . "\n";
    
    // Count braces
    $open_braces = 0;
    $close_braces = 0;
    $open_parens = 0;
    $close_parens = 0;
    $open_brackets = 0;
    $close_brackets = 0;
    
    foreach ($tokens as $token) {
        if (is_array($token)) {
            continue;
        }
        
        if ($token === '{') $open_braces++;
        if ($token === '}') $close_braces++;
        if ($token === '(') $open_parens++;
        if ($token === ')') $close_parens++;
        if ($token === '[') $open_brackets++;
        if ($token === ']') $close_brackets++;
    }
    
    echo "\nBrace Count:\n";
    echo "  Curly { }: $open_braces / $close_braces " . ($open_braces === $close_braces ? "OK" : "MISMATCH!") . "\n";
    echo "  Parens ( ): $open_parens / $close_parens " . ($open_parens === $close_parens ? "OK" : "MISMATCH!") . "\n";
    echo "  Brackets [ ]: $open_brackets / $close_brackets " . ($open_brackets === $close_brackets ? "OK" : "MISMATCH!") . "\n";
    
    echo "\nFirst 5 tokens:\n";
    for ($i = 0; $i < min(5, count($tokens)); $i++) {
        $t = $tokens[$i];
        if (is_array($t)) {
            echo "  " . token_name($t[0]) . ": " . substr(str_replace("\n", "\\n", $t[1]), 0, 30) . "\n";
        } else {
            echo "  Symbol: $t\n";
        }
    }
}

echo "\n---\n";
echo "Now trying to include...\n";

ob_start();
try {
    include 'login.php';
    $output = ob_get_clean();
    echo "Success! Generated " . strlen($output) . " bytes\n";
} catch (Throwable $e) {
    $output = ob_get_clean();
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "At: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
?>
