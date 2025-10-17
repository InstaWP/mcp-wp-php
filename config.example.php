<?php
/**
 * WordPress MCP Server Configuration Example
 *
 * Copy this file to 'config.php' and update the wordpress_path below.
 *
 * IMPORTANT: Do not commit config.php to version control. It should be
 * specific to your installation.
 */

return [
    /**
     * WordPress Installation Path
     *
     * Specify the path to your WordPress installation's wp-load.php file.
     *
     * You can use either:
     * - Relative paths (relative to this MCP server directory)
     * - Absolute paths
     *
     * Examples:
     *
     * Relative Paths (recommended if MCP server is inside/near WordPress):
     * - '../wp-load.php'                           // MCP server in subdirectory of WP root
     * - '../../wp-load.php'                        // MCP server nested two levels deep
     * - 'wp/wp-load.php'                           // WordPress in subdirectory
     *
     * Absolute Paths:
     * - '/var/www/html/wp-load.php'                // Linux
     * - '/Users/username/Sites/mysite/wp-load.php' // macOS
     * - 'C:/xampp/htdocs/mysite/wp-load.php'       // Windows (use forward slashes)
     *
     * REQUIRED: This path must be set for the server to start.
     */
    'wordpress_path' => '../wp-load.php',  // Change this to your WordPress path
];
