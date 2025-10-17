<?php
/**
 * WordPress Bootstrap Loader
 *
 * This file handles loading WordPress for the MCP server.
 * It reads the configuration from config.php and loads wp-load.php.
 *
 * This file is used by both index.php (HTTP transport) and server.php (Stdio transport).
 */

// Determine if we're running in CLI or web context
$isCli = php_sapi_name() === 'cli';

// Check if config.php exists
if (!file_exists(__DIR__ . '/config.php')) {
    $errorMessage = "Configuration file not found!\n\n"
        . "Please copy 'config.example.php' to 'config.php' and set your WordPress path:\n"
        . "  cp config.example.php config.php\n\n"
        . "Then edit config.php and update the 'wordpress_path' setting.";

    if ($isCli) {
        fwrite(STDERR, "ERROR: $errorMessage\n");
        exit(1);
    } else {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'error' => 'Configuration required',
            'message' => $errorMessage
        ]);
        exit;
    }
}

// Load configuration
$config = require __DIR__ . '/config.php';

// Validate configuration
if (!isset($config['wordpress_path']) || empty($config['wordpress_path'])) {
    $errorMessage = "WordPress path not configured!\n\n"
        . "Please edit 'config.php' and set the 'wordpress_path' to your WordPress installation.";

    if ($isCli) {
        fwrite(STDERR, "ERROR: $errorMessage\n");
        exit(1);
    } else {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'error' => 'WordPress path not configured',
            'message' => $errorMessage
        ]);
        exit;
    }
}

// Load safe mode setting (default to false if not set)
$safeMode = isset($config['safe_mode']) ? (bool)$config['safe_mode'] : false;

// Define global constant for safe mode access
if (!defined('WP_MCP_SAFE_MODE')) {
    define('WP_MCP_SAFE_MODE', $safeMode);
}

// Resolve the WordPress path (handle relative paths)
$wpLoadPath = $config['wordpress_path'];

// If path is not absolute, make it relative to this directory
if (!file_exists($wpLoadPath)) {
    // Try resolving relative to this directory
    $relativePath = __DIR__ . '/' . ltrim($config['wordpress_path'], '/');
    if (file_exists($relativePath)) {
        $wpLoadPath = $relativePath;
    }
}

// Check if WordPress exists at the resolved path
if (!file_exists($wpLoadPath)) {
    $errorMessage = "WordPress not found at configured path!\n\n"
        . "Configured path: {$config['wordpress_path']}\n"
        . "Resolved path: $wpLoadPath\n\n"
        . "Please check your 'config.php' and ensure the path to wp-load.php is correct.";

    if ($isCli) {
        fwrite(STDERR, "ERROR: $errorMessage\n");
        exit(1);
    } else {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'error' => 'WordPress not found',
            'message' => $errorMessage,
            'configured_path' => $config['wordpress_path'],
            'resolved_path' => $wpLoadPath
        ]);
        exit;
    }
}

// Set minimal environment for WordPress (if not already set)
if (!isset($_SERVER['HTTP_HOST'])) {
    $_SERVER['HTTP_HOST'] = 'mcp-wp-php';
}
if (!isset($_SERVER['SERVER_NAME'])) {
    $_SERVER['SERVER_NAME'] = 'mcp-wp-php';
}
if (!isset($_SERVER['SERVER_PORT'])) {
    $_SERVER['SERVER_PORT'] = '80';
}
if (!isset($_SERVER['REMOTE_ADDR'])) {
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
}

// Start output buffering BEFORE loading WordPress (if not already started)
if (ob_get_level() === 0) {
    ob_start();
}

// Prevent WordPress from redirecting
if (!defined('WP_INSTALLING')) {
    define('WP_INSTALLING', true);
}

// Load WordPress
require_once $wpLoadPath;

// Clear any output from WordPress loading
if (ob_get_level() > 0) {
    ob_end_clean();
}
