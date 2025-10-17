# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a professional WordPress MCP (Model Context Protocol) server implementation using the official PHP MCP SDK. The project enables AI assistants to interact with WordPress sites through a standardized protocol, providing tools for content management, user operations, and site administration.

## Development Commands

### Testing
```bash
# Run all tests
composer test

# Run tests with coverage report
composer test:coverage

# Run specific test suite
vendor/bin/phpunit tests/Unit
vendor/bin/phpunit tests/Integration

# Run a single test file
vendor/bin/phpunit tests/Unit/Tools/ListContentTest.php

# Run a specific test method
vendor/bin/phpunit --filter testExecuteReturnsFormattedResults tests/Unit/Tools/ListContentTest.php
```

### Static Analysis
```bash
# Run PHPStan analysis
composer analyse
```

### Testing MCP Tools

Test tools directly using the MCP inspector:

```bash
# List all available tools
npx @modelcontextprotocol/inspector --cli http://mcp-server-http-2/index.php --transport http --method tools/list

# Call a tool with no parameters
npx @modelcontextprotocol/inspector --cli http://mcp-server-http-2/index.php --transport http --method tools/call --tool-name discover_content_types

# Call a tool with parameters
npx @modelcontextprotocol/inspector --cli http://mcp-server-http-2/index.php --transport http --method tools/call --tool-name list_content --tool-arg content_type=post --tool-arg per_page=5

# Create content
npx @modelcontextprotocol/inspector --cli http://mcp-server-http-2/index.php --transport http --method tools/call --tool-name create_content --tool-arg content_type=post --tool-arg title="Test Post" --tool-arg content="Post content" --tool-arg status=draft
```

### Running the MCP Server

The project supports two transport methods:

**1. HTTP Transport (index.php)**
- For web access and MCP inspector testing
- Uses StreamableHttpTransport with FileSessionStore
- Requires MAMP or similar web server
- Clear session cache when tools are updated: `rm -rf sessions/*`

**2. Stdio Transport (server.php)**
- For Claude Desktop and CLI MCP clients
- Uses StdioTransport (no sessions required)
- Can be run directly: `php server.php`

**Requirements:**
- WordPress installation configured in `config.php`
- PHP 8.2+
- MAMP (for HTTP transport)

**Configuration:**
WordPress path, safe mode, and authentication are configured in `config.php` (copy from `config.example.php`):
```php
return [
    'wordpress_path' => 'wp/wp-load.php',  // Adjust to your WordPress location
    'safe_mode' => false,  // Set to true to block delete operations
    'bearer_token' => null,  // Set to enable authentication (null = disabled)
];
```

### Safe Mode

**What is Safe Mode:**
Safe mode is a configuration option that prevents destructive delete operations from being executed. When enabled, the tools `delete_content` and `delete_term` will throw exceptions with clear error messages.

**Implementation:**
1. **Configuration Loading** (`load-wordpress.php`):
   - Reads `safe_mode` from `config.php` (defaults to false)
   - Defines global constant `WP_MCP_SAFE_MODE`

2. **Safe Mode Check** (`AbstractTool.php`):
   - Provides `checkSafeMode()` method that tools can call
   - Throws `ToolException::safeModeViolation()` if safe mode is enabled

3. **Tool Integration**:
   - Delete tools (`DeleteContent`, `DeleteTerm`) call `$this->checkSafeMode('Operation description')` at the start of `doExecute()`
   - Exception is caught by AbstractTool's execute() method and returned as error response

4. **Server Metadata**:
   - Safe mode status is included in server info for transparency
   - Both `index.php` and `server.php` expose safe_mode in capabilities

**Adding Safe Mode to New Tools:**
If you create new tools that perform destructive operations:
```php
protected function doExecute(array $parameters): array
{
    // Check if safe mode blocks this operation
    $this->checkSafeMode('Description of operation being blocked');

    // Rest of your tool implementation...
}
```

**Testing with Safe Mode:**
```bash
# Edit config.php to enable safe mode
'safe_mode' => true,

# Try a delete operation
npx @modelcontextprotocol/inspector --cli http://mcp-server-http-2/index.php --transport http --method tools/call --tool-name delete_content --tool-arg content_id=123

# Should return error: "Operation blocked: Safe mode is enabled..."
```

### Authentication

**What is Authentication:**
Optional bearer token authentication for the HTTP transport (index.php). When enabled, all HTTP requests must include a valid bearer token to access the MCP server.

**How It Works:**
- **Authentication disabled** (default): Set `bearer_token` to `null` or empty string - no authentication required
- **Authentication enabled**: Set `bearer_token` to a secure random string - all requests must include valid token

**Implementation:**
1. **Token Generation** (`bin/generate-token.php`):
   - Generates cryptographically secure random tokens
   - Provides configuration examples for Claude Desktop and MCP inspector
   - Displays setup instructions

2. **Configuration** (`config.php`):
   - Set `bearer_token` to generated token value
   - Leave as `null` to disable authentication

3. **Token Validation** (`src/Auth/BearerTokenAuth.php`):
   - Validates `Authorization: Bearer <token>` header
   - Also accepts `X-MCP-API-Key: <token>` as fallback
   - Returns MCP-compliant 401 responses with `WWW-Authenticate` header

4. **Server Integration** (`index.php`):
   - Authentication check runs before WordPress loads
   - Server metadata includes `authentication_enabled` status
   - Exits early with 401 if authentication fails

**Generating a Token:**
```bash
# Generate a secure token
php bin/generate-token.php

# Generate with custom length
php bin/generate-token.php --length 48

# Show help
php bin/generate-token.php --help
```

**Configuration Examples:**

**Local Development (No Authentication):**
```php
// config.php
return [
    'wordpress_path' => 'wp/wp-load.php',
    'bearer_token' => null,  // Authentication disabled
];
```

**Production (With Authentication):**
```php
// config.php
return [
    'wordpress_path' => 'wp/wp-load.php',
    'bearer_token' => 'your-generated-token-here',  // Authentication enabled
];
```

**Claude Desktop Integration (with authentication):**
```json
{
  "mcpServers": {
    "php-wordpress-http": {
      "command": "npx",
      "args": [
        "mcp-remote",
        "https://your-site.com/mcp/index.php",
        "--header",
        "Authorization:${MCP_TOKEN}"
      ],
      "env": {
        "MCP_TOKEN": "Bearer your-generated-token-here"
      }
    }
  }
}
```

**Note:** Remove spaces around colons in header values due to a bug in Cursor/Claude Desktop Windows that mangles header values with spaces.

**Testing with MCP Inspector:**

**Without Authentication:**
```bash
npx @modelcontextprotocol/inspector \
  --cli http://mcp-server-http-2/index.php \
  --transport http \
  --method tools/list
```

**With Authentication:**
```bash
# Set token in environment
export MCP_BEARER_TOKEN="your-generated-token-here"

# Run tests with authentication
./tests/inspector-tests.sh

# Or use inspector directly
npx @modelcontextprotocol/inspector \
  --cli http://mcp-server-http-2/index.php \
  --transport http \
  --header "Authorization: Bearer your-generated-token-here" \
  --method tools/list
```

**Security Best Practices:**
- **Always use HTTPS** in production (never plain HTTP)
- Store tokens in environment variables or secure vaults
- Never commit `config.php` with real tokens to version control
- Rotate tokens periodically (generate new token, update config)
- Use different tokens for different environments (dev/staging/production)
- Token length: minimum 32 bytes (43 characters base64), recommended 48+ bytes
- Monitor for unauthorized access attempts in logs

**Claude Desktop Integration (Stdio):**
Add to `~/Library/Application Support/Claude/claude_desktop_config.json`:
```json
{
  "mcpServers": {
    "php-wordpress": {
      "command": "php",
      "args": [
        "/Users/vikas/Playground/random/mcp-php-example-2/server.php"
      ]
    }
  }
}
```

**Claude Desktop Integration (HTTP via mcp-remote):**
```json
{
  "mcpServers": {
    "php-wordpress-http": {
      "command": "npx",
      "args": [
        "mcp-remote",
        "http://mcp-server-http-2/index.php",
        "--allow-http"
      ]
    }
  }
}
```

## Architecture

### Core Components

**Tool System (`src/Tools/`)**
- `ToolInterface`: Contract defining getName(), getDescription(), getSchema(), and execute()
- `AbstractTool`: Base class providing validation, logging, error handling, and standardized response formatting
- All tools extend AbstractTool and implement doExecute() for tool-specific logic

**Service Layer (`src/Services/`)**
- `WordPressService`: Wrapper around WordPress functions for testability and abstraction
- `ValidationService`: Parameter validation using Respect/Validation library

**Exception Handling (`src/Exceptions/`)**
- `ToolException`: Custom exceptions with support for validation errors and WordPress-specific errors

### Tool Development Pattern

When creating new tools:

1. **Extend AbstractTool** - Inherit validation, logging, and error handling
2. **Implement getName()** - Return unique tool identifier
3. **Implement getDescription()** - Provide clear, concise tool description
4. **Implement getSchema()** - Define validation rules for parameters using Respect/Validation syntax
5. **Implement doExecute()** - Write tool-specific logic, parameters are pre-validated

Example validation schema format:
```php
public function getSchema(): array
{
    return [
        'post_id' => ['intType', 'positive'],
        'status' => ['optional', 'in' => ['publish', 'draft', 'pending']],
        'title' => ['stringType', 'length' => [1, 200]]
    ];
}
```

### Dependency Injection

Tools receive dependencies through constructor:
- `WordPressService $wp` - For WordPress operations
- `ValidationService $validator` - For parameter validation
- `?LoggerInterface $logger` - Optional PSR-3 logger (defaults to NullLogger)

### Response Format

Tools return structured arrays:
```php
// Success response
return $this->success($data, $message);
// Returns: ['success' => true, 'data' => $data, 'message' => $message]

// Error response
return $this->error($message, $errors);
// Returns: ['success' => false, 'error' => $message, 'errors' => $errors]
```

### MCP Server Tool Registration

The `index.php` dynamically registers all 17 tools. The MCP SDK uses reflection to map parameters by name, so we generate wrapper functions with proper named parameters for each tool:

```php
foreach ($tools as $tool) {
    $schema = $tool->getSchema();
    // Build parameter signature: $content_type = null, $per_page = null, etc.
    $params = [];
    foreach ($schema as $paramName => $rules) {
        $params[] = "\${$paramName} = null";
    }
    $paramSignature = implode(', ', $params);

    // Create wrapper function with named parameters
    $wrapper = eval("return function({$paramSignature}) use (\$tool) {
        \$params = array_filter(compact(...), fn(\$v) => \$v !== null);
        \$result = \$tool->execute(\$params);
        return \$result['data'] ?? \$result;
    };");

    $serverBuilder->addTool($wrapper, name: $tool->getName(), ...);
}
```

**Why this approach:** The MCP SDK's ReferenceHandler uses reflection to match incoming parameters to function arguments by name. Dynamic wrapper functions allow the SDK to properly introspect and map parameters.

**Tool Naming Convention:**
All tool names follow InstaWP/mcp-wp conventions:
- Content: `list_content`, `get_content`, `create_content`, `update_content`, `delete_content`, `discover_content_types`, `get_content_by_slug`, `find_content_by_url`
- Taxonomy: `discover_taxonomies`, `get_taxonomy`, `list_terms`, `get_term`, `create_term`, `update_term`, `delete_term`, `assign_terms_to_content`, `get_content_terms`

### WordPress Integration

WordPress loading is handled by `load-wordpress.php`, which:
1. Checks for `config.php` (fails with helpful error if missing)
2. Reads WordPress path from configuration
3. Validates the path exists
4. Sets minimal $_SERVER environment variables
5. Starts output buffering to suppress WordPress output
6. Defines WP_INSTALLING constant to prevent redirects
7. Loads wp-load.php from configured path
8. Clears output buffer before MCP request handling

Both `index.php` (HTTP) and `server.php` (Stdio) use the same centralized loader, ensuring consistent WordPress initialization.

## Key Design Decisions

- **Configuration-Based WordPress Loading**: WordPress path is configured in `config.php` (git-ignored), allowing users to drop the MCP server anywhere without modifying core files. Centralized loader (`load-wordpress.php`) handles all WordPress initialization for both transports.
- **Streamable HTTP Transport**: Uses MCP PHP SDK's StreamableHttpTransport instead of deprecated SSE
- **PSR Standards**: Follows PSR-3 (logging), PSR-7 (HTTP messages), PSR-17 (HTTP factories)
- **Session Management**: File-based sessions for HTTP transport state management
- **Output Buffering**: Critical for preventing WordPress from interfering with MCP JSON responses
- **Validation First**: All parameters validated before execution using declarative schemas
- **Template Method Pattern**: AbstractTool.execute() orchestrates validation/logging, doExecute() implements logic

## Testing Strategy

- **Unit Tests** (`tests/Unit/`): Test tool logic in isolation with mocked WordPress functions
- **Inspector Tests** (`tests/inspector-tests.sh`): End-to-end tests via HTTP using MCP inspector
- Use Mockery for mocking WordPressService and ValidationService dependencies
- Each tool should have corresponding test file with coverage for success cases, validation errors, and WordPress errors

**Run Inspector Tests:**
```bash
./tests/inspector-tests.sh
```

These tests verify all tools work correctly via the HTTP transport by making actual MCP calls.

## Troubleshooting

**Tools not updating after code changes:**
```bash
# Clear session cache
rm -rf sessions/*
# Restart Claude Desktop to reconnect
```

**Tool execution errors:**
- Use MCP inspector to test tools directly and see actual error messages
- Check tool parameter format matches schema (array keys and types)
- Verify WordPress path is correctly configured in `config.php`
- Check `php-errors.log` for WordPress loading errors
