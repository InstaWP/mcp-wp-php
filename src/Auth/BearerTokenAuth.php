<?php

namespace InstaWP\MCP\PHP\Auth;

/**
 * Bearer Token Authentication
 *
 * Provides optional bearer token authentication for MCP HTTP transport.
 * If no token is configured, authentication is disabled (open access).
 * If a token is configured, all requests must include valid Authorization header.
 */
class BearerTokenAuth
{
    private ?string $expectedToken;

    /**
     * @param string|null $expectedToken The bearer token to validate against (null = auth disabled)
     */
    public function __construct(?string $expectedToken = null)
    {
        $this->expectedToken = $expectedToken;
    }

    /**
     * Check if authentication is enabled
     */
    public function isEnabled(): bool
    {
        return !empty($this->expectedToken);
    }

    /**
     * Validate the incoming request for bearer token authentication
     *
     * @return array{authenticated: bool, error: ?string, headers: array<string, string>}
     */
    public function validate(): array
    {
        // If no token configured, authentication is disabled - allow all requests
        if (!$this->isEnabled()) {
            return [
                'authenticated' => true,
                'error' => null,
                'headers' => []
            ];
        }

        // Extract token from request
        $providedToken = $this->extractToken();

        // No token provided
        if ($providedToken === null) {
            return [
                'authenticated' => false,
                'error' => 'Authentication required',
                'headers' => [
                    'WWW-Authenticate' => 'Bearer realm="MCP Server", error="invalid_token", error_description="Bearer token required"'
                ]
            ];
        }

        // Token provided but invalid
        if (!$this->verifyToken($providedToken)) {
            return [
                'authenticated' => false,
                'error' => 'Invalid or expired token',
                'headers' => [
                    'WWW-Authenticate' => 'Bearer realm="MCP Server", error="invalid_token", error_description="Invalid bearer token"'
                ]
            ];
        }

        // Token is valid
        return [
            'authenticated' => true,
            'error' => null,
            'headers' => []
        ];
    }

    /**
     * Extract bearer token from request headers
     *
     * Checks both:
     * 1. Authorization: Bearer <token>
     * 2. X-MCP-API-Key: <token>
     */
    private function extractToken(): ?string
    {
        // Check Authorization header (standard)
        $authHeader = $this->getHeader('Authorization');
        if ($authHeader !== null && preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
            return trim($matches[1]);
        }

        // Check X-MCP-API-Key header (fallback)
        $apiKeyHeader = $this->getHeader('X-MCP-API-Key');
        if ($apiKeyHeader !== null) {
            return trim($apiKeyHeader);
        }

        return null;
    }

    /**
     * Get header value from request (case-insensitive)
     */
    private function getHeader(string $name): ?string
    {
        // Check $_SERVER with HTTP_ prefix
        $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        if (isset($_SERVER[$serverKey])) {
            return $_SERVER[$serverKey];
        }

        // Check without HTTP_ prefix (for Authorization header)
        $serverKeyAlt = strtoupper(str_replace('-', '_', $name));
        if (isset($_SERVER[$serverKeyAlt])) {
            return $_SERVER[$serverKeyAlt];
        }

        // Check getallheaders() if available
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            foreach ($headers as $key => $value) {
                if (strcasecmp($key, $name) === 0) {
                    return $value;
                }
            }
        }

        return null;
    }

    /**
     * Verify the provided token matches the expected token
     *
     * Uses timing-safe comparison to prevent timing attacks
     */
    private function verifyToken(string $providedToken): bool
    {
        if ($this->expectedToken === null) {
            return false;
        }

        return hash_equals($this->expectedToken, $providedToken);
    }

    /**
     * Send 401 Unauthorized response with proper headers
     *
     * @param string $error Error message
     * @param array<string, string> $headers Additional headers to send
     */
    public function sendUnauthorizedResponse(string $error, array $headers = []): void
    {
        http_response_code(401);
        header('Content-Type: application/json');

        // Send WWW-Authenticate and other headers
        foreach ($headers as $name => $value) {
            header("$name: $value");
        }

        echo json_encode([
            'error' => 'Unauthorized',
            'message' => $error
        ]);
    }
}
