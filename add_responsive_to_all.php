<?php
/**
 * Add Responsive CSS and JS to All HTML Files
 * This script automatically adds responsive.css and responsive.js to all HTML files
 */

$frontendDir = __DIR__ . '/frontend';
$pagesDir = $frontendDir . '/pages';

// Files to process
$files = array_merge(
    glob($frontendDir . '/*.html'),
    glob($pagesDir . '/*.html')
);

$responsiveCss = '<link rel="stylesheet" href="../assets/css/responsive.css">';
$responsiveJs = '<script src="../assets/js/responsive.js"></script>';

$responsiveCssRoot = '<link rel="stylesheet" href="assets/css/responsive.css">';
$responsiveJsRoot = '<script src="assets/js/responsive.js"></script>';

$updatedCount = 0;
$skippedCount = 0;

foreach ($files as $file) {
    $content = file_get_contents($file);
    $original = $content;
    $isRootFile = strpos($file, '/pages/') === false;

    // Skip backup files
    if (strpos($file, 'backup') !== false || strpos($file, '_old') !== false) {
        echo "Skipped (backup): " . basename($file) . "\n";
        $skippedCount++;
        continue;
    }

    // Check if already has responsive CSS
    if (strpos($content, 'responsive.css') !== false) {
        echo "Skipped (already has responsive): " . basename($file) . "\n";
        $skippedCount++;
        continue;
    }

    // Fix duplicate viewport tags
    $viewportCount = substr_count($content, '<meta name="viewport"');
    if ($viewportCount > 1) {
        // Remove all viewport tags first
        $content = preg_replace('/<meta name="viewport"[^>]*>/i', '', $content);
        // Add one correct viewport tag
        $content = preg_replace(
            '/(<meta charset="UTF-8">)/i',
            '$1' . "\n\t" . '<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">',
            $content,
            1
        );
    } elseif ($viewportCount === 0) {
        // Add viewport tag if missing
        $content = preg_replace(
            '/(<meta charset="UTF-8">)/i',
            '$1' . "\n\t" . '<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">',
            $content,
            1
        );
    } else {
        // Update existing viewport tag
        $content = preg_replace(
            '/<meta name="viewport" content="[^"]*">/i',
            '<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">',
            $content
        );
    }

    // Add responsive CSS after first stylesheet or before </head>
    if (preg_match('/<link[^>]*rel="stylesheet"[^>]*>/', $content)) {
        // Add after first stylesheet
        $cssToAdd = $isRootFile ? $responsiveCssRoot : $responsiveCss;
        $content = preg_replace(
            '/(<link[^>]*rel="stylesheet"[^>]*>)/i',
            '$1' . "\n\t" . $cssToAdd,
            $content,
            1
        );
    } else {
        // Add before </head>
        $cssToAdd = $isRootFile ? $responsiveCssRoot : $responsiveCss;
        $content = preg_replace(
            '/(<\/head>)/i',
            "\t" . $cssToAdd . "\n" . '$1',
            $content
        );
    }

    // Add responsive JS before </body>
    $jsToAdd = $isRootFile ? $responsiveJsRoot : $responsiveJs;
    if (strpos($content, '</body>') !== false) {
        $content = preg_replace(
            '/(<\/body>)/i',
            "\t" . $jsToAdd . "\n" . '$1',
            $content
        );
    }

    // Only write if content changed
    if ($content !== $original) {
        file_put_contents($file, $content);
        echo "Updated: " . basename($file) . "\n";
        $updatedCount++;
    } else {
        echo "No changes needed: " . basename($file) . "\n";
        $skippedCount++;
    }
}

echo "\n=== Summary ===\n";
echo "Updated: $updatedCount files\n";
echo "Skipped: $skippedCount files\n";
echo "Total processed: " . ($updatedCount + $skippedCount) . " files\n";
?>
