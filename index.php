<?php

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/php-errors.log');

// Custom error handler to capture all errors
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    $errorLog = __DIR__ . '/php-errors.log';
    $message = date('[Y-m-d H:i:s] ') . "PHP Error [$errno]: $errstr in $errfile on line $errline\n";
    file_put_contents($errorLog, $message, FILE_APPEND);
    return false;
});

// Custom exception handler
set_exception_handler(function($exception) {
    $errorLog = __DIR__ . '/php-errors.log';
    $message = date('[Y-m-d H:i:s] ') . "Uncaught Exception: " . $exception->getMessage() . "\n";
    $message .= "File: " . $exception->getFile() . " Line: " . $exception->getLine() . "\n";
    $message .= "Trace: " . $exception->getTraceAsString() . "\n\n";
    file_put_contents($errorLog, $message, FILE_APPEND);
});

require_once __DIR__ . '/vendor/autoload.php';

// Load WordPress using centralized loader
// This reads config.php and loads WordPress from the configured path
require_once __DIR__ . '/load-wordpress.php';

use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;
use Mcp\Server;
use Mcp\Server\Session\FileSessionStore;
use Mcp\Server\Transport\StreamableHttpTransport;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;

// Import our services and tools
use InstaWP\MCP\PHP\Services\ValidationService;
use InstaWP\MCP\PHP\Services\WordPressService;

// Content tools
use InstaWP\MCP\PHP\Tools\Content\ListContent;
use InstaWP\MCP\PHP\Tools\Content\GetContent;
use InstaWP\MCP\PHP\Tools\Content\CreateContent;
use InstaWP\MCP\PHP\Tools\Content\UpdateContent;
use InstaWP\MCP\PHP\Tools\Content\DeleteContent;
use InstaWP\MCP\PHP\Tools\Content\DiscoverContentTypes;
use InstaWP\MCP\PHP\Tools\Content\GetContentBySlug;
use InstaWP\MCP\PHP\Tools\Content\FindContentByUrl;

// Taxonomy tools
use InstaWP\MCP\PHP\Tools\Taxonomy\DiscoverTaxonomies;
use InstaWP\MCP\PHP\Tools\Taxonomy\GetTaxonomy;
use InstaWP\MCP\PHP\Tools\Taxonomy\ListTerms;
use InstaWP\MCP\PHP\Tools\Taxonomy\GetTerm;
use InstaWP\MCP\PHP\Tools\Taxonomy\CreateTerm;
use InstaWP\MCP\PHP\Tools\Taxonomy\UpdateTerm;
use InstaWP\MCP\PHP\Tools\Taxonomy\DeleteTerm;
use InstaWP\MCP\PHP\Tools\Taxonomy\AssignTermsToContent;
use InstaWP\MCP\PHP\Tools\Taxonomy\GetContentTerms;

// Initialize services
$wpService = new WordPressService();
$validationService = new ValidationService();

// Initialize all tools
$tools = [
    // Content tools
    new ListContent($wpService, $validationService),
    new GetContent($wpService, $validationService),
    new CreateContent($wpService, $validationService),
    new UpdateContent($wpService, $validationService),
    new DeleteContent($wpService, $validationService),
    new DiscoverContentTypes($wpService, $validationService),
    new GetContentBySlug($wpService, $validationService),
    new FindContentByUrl($wpService, $validationService),

    // Taxonomy tools
    new DiscoverTaxonomies($wpService, $validationService),
    new GetTaxonomy($wpService, $validationService),
    new ListTerms($wpService, $validationService),
    new GetTerm($wpService, $validationService),
    new CreateTerm($wpService, $validationService),
    new UpdateTerm($wpService, $validationService),
    new DeleteTerm($wpService, $validationService),
    new AssignTermsToContent($wpService, $validationService),
    new GetContentTerms($wpService, $validationService),
];

// Create PSR-7 request factory
$psr17Factory = new Psr17Factory();
$creator = new ServerRequestCreator($psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory);

// Get the incoming request
$request = $creator->fromGlobals();

// Create the MCP server
$serverBuilder = Server::builder()
    ->setServerInfo('WordPress MCP Server', '1.0.0')
    ->setSession(new FileSessionStore(__DIR__ . '/sessions'));

// Register all tools with dynamic parameter handling
foreach ($tools as $tool) {
    // Create a dynamic callable that accepts any parameters and passes them as an array to execute()
    // The SDK will use reflection to map parameters from inputSchema to this function's parameters
    // We dynamically create a function with the right signature based on the tool's schema

    $schema = $tool->getSchema();
    $params = [];
    $paramNames = [];

    foreach ($schema as $paramName => $rules) {
        // All parameters are optional with null default, we'll handle validation in the tool
        $params[] = "\${$paramName} = null";
        $paramNames[] = "'{$paramName}'";
    }

    $paramSignature = implode(', ', $params);
    $compactParams = implode(', ', $paramNames);

    // Create a wrapper function with proper named parameters
    if (empty($paramNames)) {
        // Tool has no parameters
        $wrapper = function() use ($tool) {
            $result = $tool->execute([]);
            return $result['data'] ?? $result;
        };
    } else {
        $code = <<<PHP
return function({$paramSignature}) use (\$tool) {
    \$params = array_filter(compact({$compactParams}), fn(\$v) => \$v !== null);
    \$result = \$tool->execute(\$params);
    return \$result['data'] ?? \$result;
};
PHP;
        $wrapper = eval($code);
    }

    $serverBuilder->addTool(
        $wrapper,
        name: $tool->getName(),
        description: $tool->getDescription(),
        inputSchema: $tool->getInputSchema()
    );
}

// Add site info resource
$serverBuilder->addResource(
    function (): array {
        return [
            'site_name' => get_bloginfo('name'),
            'site_url' => get_bloginfo('url'),
            'admin_email' => get_bloginfo('admin_email'),
            'wordpress_version' => get_bloginfo('version'),
            'posts_count' => wp_count_posts('post')->publish,
            'pages_count' => wp_count_posts('page')->publish,
        ];
    },
    uri: 'wordpress://site/info',
    name: 'site_info',
    description: 'WordPress site information and statistics',
    mimeType: 'application/json'
);

$server = $serverBuilder->build();

// Create HTTP transport
$transport = new StreamableHttpTransport($request, $psr17Factory, $psr17Factory);

// Run the server and get response
$response = $server->run($transport);

// Emit the response using SAPI emitter
(new SapiEmitter())->emit($response);
