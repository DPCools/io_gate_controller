<?php
/**
 * Fix redirect syntax errors in index.php
 */

// Read the file
$content = file_get_contents('index.php');

// Fix missing closing parentheses in header redirects
$content = preg_replace(
    "/header\('Location: ' \. \\\$baseUrl \. '\/api-keys';/",
    "header('Location: ' . \$baseUrl . '/api-keys');",
    $content
);

// Fix any remaining non-base URL redirects
$content = preg_replace(
    "/header\('Location: \/login'\);/",
    "header('Location: ' . \$baseUrl . '/login');",
    $content
);

$content = preg_replace(
    "/header\('Location: \/'\);/",
    "header('Location: ' . \$baseUrl . '/');",
    $content
);

$content = preg_replace(
    "/header\('Location: \/devices'\);/",
    "header('Location: ' . \$baseUrl . '/devices');",
    $content
);

$content = preg_replace(
    "/header\('Location: \/gates'\);/",
    "header('Location: ' . \$baseUrl . '/gates');",
    $content
);

$content = preg_replace(
    "/header\('Location: \/api-keys'\);/",
    "header('Location: ' . \$baseUrl . '/api-keys');",
    $content
);

// Write the file back
file_put_contents('index.php', $content);

echo "Fixed redirect syntax errors in index.php\n";
