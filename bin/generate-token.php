#!/usr/bin/env php
<?php
/**
 * Bearer Token Generator
 *
 * Generates a cryptographically secure random token for MCP authentication.
 *
 * Usage:
 *   php bin/generate-token.php
 *   php bin/generate-token.php --length 64
 */

// Parse command line arguments
$options = getopt('', ['length:', 'help']);

if (isset($options['help'])) {
    echo <<<HELP
Bearer Token Generator

Generates a cryptographically secure random token for MCP authentication.

Usage:
  php bin/generate-token.php [OPTIONS]

Options:
  --length <bytes>  Number of random bytes to generate (default: 32)
                    The output will be base64 encoded, so the actual token
                    length will be approximately 4/3 * bytes
  --help            Show this help message

Examples:
  php bin/generate-token.php
  php bin/generate-token.php --length 48
  php bin/generate-token.php --length 64

After generating a token:
  1. Copy the generated token
  2. Edit your config.php file
  3. Set 'bearer_token' => 'your-generated-token'
  4. Save the file
  5. Configure your MCP client with the same token

HELP;
    exit(0);
}

// Get length from options or use default
$length = isset($options['length']) ? (int)$options['length'] : 32;

// Validate length
if ($length < 16) {
    fwrite(STDERR, "Error: Length must be at least 16 bytes for security\n");
    exit(1);
}

if ($length > 256) {
    fwrite(STDERR, "Error: Length must not exceed 256 bytes\n");
    exit(1);
}

// Generate cryptographically secure random bytes
try {
    $randomBytes = random_bytes($length);
    $token = base64_encode($randomBytes);

    // Remove padding characters to make token cleaner
    $token = rtrim($token, '=');

    echo "\n";
    echo "==========================================================\n";
    echo "  Bearer Token Generated Successfully\n";
    echo "==========================================================\n";
    echo "\n";
    echo "Token: {$token}\n";
    echo "\n";
    echo "Token Length: " . strlen($token) . " characters\n";
    echo "Entropy: {$length} bytes\n";
    echo "\n";
    echo "==========================================================\n";
    echo "  Configuration Instructions\n";
    echo "==========================================================\n";
    echo "\n";
    echo "1. Add to your config.php:\n";
    echo "\n";
    echo "   'bearer_token' => '{$token}',\n";
    echo "\n";
    echo "2. Configure Claude Desktop (mcp-remote):\n";
    echo "\n";
    echo "   {\n";
    echo "     \"mcpServers\": {\n";
    echo "       \"wordpress\": {\n";
    echo "         \"command\": \"npx\",\n";
    echo "         \"args\": [\n";
    echo "           \"mcp-remote\",\n";
    echo "           \"https://your-site.com/mcp/index.php\",\n";
    echo "           \"--header\",\n";
    echo "           \"Authorization:\${MCP_TOKEN}\"\n";
    echo "         ],\n";
    echo "         \"env\": {\n";
    echo "           \"MCP_TOKEN\": \"Bearer {$token}\"\n";
    echo "         }\n";
    echo "       }\n";
    echo "     }\n";
    echo "   }\n";
    echo "\n";
    echo "3. Test with MCP Inspector:\n";
    echo "\n";
    echo "   npx @modelcontextprotocol/inspector \\\n";
    echo "     --cli http://your-site/index.php \\\n";
    echo "     --transport http \\\n";
    echo "     --header \"Authorization: Bearer {$token}\" \\\n";
    echo "     --method tools/list\n";
    echo "\n";
    echo "==========================================================\n";
    echo "  Security Reminders\n";
    echo "==========================================================\n";
    echo "\n";
    echo "- Store this token securely (password manager, vault)\n";
    echo "- Never commit config.php with real tokens to git\n";
    echo "- Use HTTPS in production (never plain HTTP)\n";
    echo "- Rotate tokens periodically\n";
    echo "- Use different tokens for different environments\n";
    echo "\n";

    exit(0);
} catch (Exception $e) {
    fwrite(STDERR, "Error generating random bytes: " . $e->getMessage() . "\n");
    exit(1);
}
