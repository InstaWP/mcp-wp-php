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

    /**
     * Safe Mode
     *
     * When enabled, safe mode prevents destructive operations from being executed.
     *
     * Blocked operations when safe_mode is true:
     * - delete_content: Deleting posts, pages, and other content
     * - delete_term: Deleting categories, tags, and other taxonomy terms
     *
     * Allowed operations (read and write):
     * - All list/get/discover operations (read-only)
     * - create_content, update_content (write operations)
     * - create_term, update_term (write operations)
     * - assign_terms_to_content (write operations)
     *
     * Use cases:
     * - Production environments where accidental deletions must be prevented
     * - Read-only or limited-write access for untrusted clients
     * - Shared environments where delete permissions should be restricted
     * - Testing and development where data preservation is important
     *
     * When an operation is blocked, the tool will return an error message
     * indicating that safe mode is enabled.
     *
     * DEFAULT: false (all operations allowed)
     */
    'safe_mode' => false,  // Set to true to enable safe mode and block delete operations
];
