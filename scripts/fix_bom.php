<?php
// Script to remove UTF-8 BOM from file

$file = 'C:\\xampp\\htdocs\\smartsys\\api\\v1\\handlers\\SalesHandler.php';
$content = file_get_contents($file);

// Check for UTF-8 BOM
$bom = "\xEF\xBB\xBF";
if (strpos($content, $bom) === 0) {
    $content = substr($content, 3);
    file_put_contents($file, $content);
    echo "✅ BOM removed successfully\n";
} else {
    echo "✓ No BOM detected\n";
}

// Test PHP lint
$output = [];
$return_var = 0;
exec('php -l "' . $file . '"', $output, $return_var);

if ($return_var === 0) {
    echo "✅ PHP lint: PASSED\n";
    foreach ($output as $line) {
        echo "   $line\n";
    }
} else {
    echo "❌ PHP lint: FAILED\n";
    foreach ($output as $line) {
        echo "   $line\n";
    }
}
?>
