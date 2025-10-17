# WordPress MCP Server (using PHP SDK)

A professional WordPress MCP (Model Context Protocol) server that enables AI assistants like Claude to interact with your WordPress site through a standardized protocol.

## What is this?

This server exposes your WordPress content, taxonomies, and site information through MCP tools, allowing AI assistants to:
- List, read, create, update, and delete WordPress content (posts, pages, custom post types)
- Manage taxonomies (categories, tags, custom taxonomies) and terms
- Discover content types and taxonomies
- Search content by URL or slug

## Features

✨ **17 WordPress Tools**
- Content Management: `list_content`, `get_content`, `create_content`, `update_content`, `delete_content`
- Content Discovery: `discover_content_types`, `get_content_by_slug`, `find_content_by_url`
- Taxonomy Management: `list_terms`, `get_term`, `create_term`, `update_term`, `delete_term`
- Taxonomy Discovery: `discover_taxonomies`, `get_taxonomy`
- Term Assignment: `assign_terms_to_content`, `get_content_terms`

🔒 **Secure & Production Ready**
- Built with official PHP MCP SDK
- Comprehensive validation using Respect/Validation
- Proper error handling and logging
- 110+ passing unit tests

🚀 **Easy Integration**
- HTTP transport via StreamableHTTP (no complicated setup)
- Works with Claude Desktop via mcp-remote
- Simple installation in WordPress root

## Installation

### Step 1: Install in WordPress

1. Download this repository
2. Place the entire directory in your WordPress root folder (where `wp-config.php` is located)
3. Rename the directory to something simple like `mcp-server`

Your directory structure should look like:
```
/your-wordpress-site/
├── wp-admin/
├── wp-content/
├── wp-includes/
├── wp-config.php
├── mcp-server/          ← This directory
│   ├── index.php
│   ├── vendor/
│   └── ...
```

### Step 2: Install Dependencies

Open terminal in the `mcp-server` directory and run:

```bash
composer install --no-dev
```

### Step 3: Verify Access

Make sure your web server (Apache/Nginx) can access the `mcp-server` directory.

**Access URL:**
The server will be accessible at: `https://your-site.com/mcp-server/index.php`

### Step 4: Test the Server

Visit `https://your-site.com/mcp-server/index.php` in your browser. You should see a JSON response indicating the MCP server is running.

## Claude Desktop Integration

This server supports two connection methods:

### Option 1: HTTP Transport (Recommended for Remote Sites)

Use this method when connecting to a remote WordPress site via HTTP/HTTPS.

**Install mcp-remote (optional, Claude Desktop can do this itself):**
```bash
npm install -g mcp-remote
```

**Configure Claude Desktop:**

Add to your Claude Desktop config file:

**macOS:** `~/Library/Application Support/Claude/claude_desktop_config.json`
**Windows:** `%APPDATA%\Claude\claude_desktop_config.json`

```json
{
  "mcpServers": {
    "wordpress": {
      "command": "npx",
      "args": [
        "mcp-remote",
        "https://your-site.com/mcp-server/index.php"
      ]
    }
  }
}
```

**Note:** Replace `https://your-site.com/mcp-server/index.php` with your actual URL.

For local development (HTTP without SSL):
```json
{
  "mcpServers": {
    "wordpress": {
      "command": "npx",
      "args": [
        "mcp-remote",
        "http://your-local-site.test/mcp-server/index.php",
        "--allow-http"
      ]
    }
  }
}
```

### Option 2: Stdio Transport (Recommended for Local Sites)

Use this method when running WordPress locally on the same machine as Claude Desktop. This is faster and doesn't require HTTP.

**Configure Claude Desktop:**

```json
{
  "mcpServers": {
    "wordpress": {
      "command": "php",
      "args": [
        "/absolute/path/to/your-wordpress-site/mcp-server/server.php"
      ]
    }
  }
}
```

**Example for macOS/Linux:**
```json
{
  "mcpServers": {
    "wordpress": {
      "command": "php",
      "args": [
        "/Users/yourname/Sites/mywordpress/mcp-server/server.php"
      ]
    }
  }
}
```

**Example for Windows:**
```json
{
  "mcpServers": {
    "wordpress": {
      "command": "php",
      "args": [
        "C:\\xampp\\htdocs\\mywordpress\\mcp-server\\server.php"
      ]
    }
  }
}
```

**Note:**
- Make sure `php` is in your system PATH
- Use the absolute path to `server.php`
- Stdio transport doesn't require a web server to be running

### Restart Claude Desktop

After adding the configuration, restart Claude Desktop completely (quit and reopen).

## Using with Claude

Once configured, you can ask Claude things like:

- "List all my blog posts"
- "Create a new draft post titled 'Hello World'"
- "What categories do I have?"
- "Update post ID 5 to published status"
- "Find the post with URL https://mysite.com/my-post"
- "Show me all pages on my site"

Claude will use the MCP tools to interact with your WordPress site automatically!

## Example Prompts

**Content Management:**
- "List my latest 5 published posts"
- "Create a new post titled 'My New Article' with some sample content"
- "Update the post titled 'About Us' to change its status to draft"
- "Show me all draft posts"

**Taxonomy Management:**
- "List all categories"
- "Create a new category called 'Technology'"
- "What tags exist on my site?"
- "Assign the 'Technology' category to post ID 10"

**Discovery:**
- "What content types does my WordPress site have?"
- "What taxonomies are registered?"
- "Find the post at URL https://mysite.com/2024/hello-world"

## Troubleshooting

### Server not responding

1. **Check URL:** Make sure the URL in your Claude config is correct and accessible
2. **Clear sessions:** Delete the `sessions/` directory contents: `rm -rf mcp-server/sessions/*`
3. **Check permissions:** Ensure the web server can read/write to the `sessions/` directory

```bash
chmod -R 755 mcp-server/
chmod -R 777 mcp-server/sessions/
```

### "Connection refused" or "Cannot connect"

1. **Verify the server is running:** Visit the URL in your browser
2. **Check firewall:** Ensure your firewall allows access to the web server
3. **Check SSL:** For HTTPS sites, ensure your SSL certificate is valid

### Tools not working

1. **Update WordPress path:** Edit `index.php` line ~53 and update the WordPress path if needed:
   ```php
   require_once __DIR__ . '/../wp-load.php';
   ```

2. **Clear sessions:**
   ```bash
   rm -rf sessions/*
   ```

3. **Check error logs:** Look at `php-errors.log` in the mcp-server directory

### Claude can't see the tools

1. **Restart Claude Desktop** completely (quit and reopen, don't just close the window)
2. **Check config syntax:** Ensure your `claude_desktop_config.json` is valid JSON
3. **Check logs:** Claude Desktop logs are at:
   - macOS: `~/Library/Logs/Claude/`
   - Windows: `%APPDATA%\Claude\logs\`

## Testing the Server

You can test the MCP server directly using the MCP inspector:

```bash
# Install inspector
npm install -g @modelcontextprotocol/inspector

# List all tools
npx @modelcontextprotocol/inspector --cli https://your-site.com/mcp-server/index.php --transport http --method tools/list

# Test a tool
npx @modelcontextprotocol/inspector --cli https://your-site.com/mcp-server/index.php --transport http --method tools/call --tool-name discover_content_types
```

## Security Considerations

⚠️ **Important Security Notes:**

1. **Authentication:** This server does NOT include authentication. Make sure to:
   - Use HTTPS (SSL/TLS) for all connections
   - Restrict access using web server configuration (IP whitelist, Basic Auth, etc.)
   - Consider implementing WordPress authentication in production

2. **Access Control:** The server has full access to your WordPress database through WordPress functions. Only expose it to trusted clients.

3. **Firewall:** Consider using a firewall or VPN to restrict access to trusted IPs only. You can configure IP restrictions in your web server configuration (Apache VirtualHost or Nginx server block).

## Development

For developers who want to extend this server:

- See `CLAUDE.md` for development documentation
- Run tests: `composer test`
- Run static analysis: `composer analyse`
- Inspector tests: `./tests/inspector-tests.sh`

## Requirements

- PHP 8.2 or higher
- WordPress 5.0 or higher
- Composer
- Node.js and npm (for mcp-remote)

## Support

For issues, questions, or contributions, please visit the project repository.

## License

This project is licensed under the MIT License.
