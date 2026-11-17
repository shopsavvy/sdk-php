<?php

// Router for `php -S` used by ApiErrorsTest: the `ids` query value picks the scenario.
// A real HTTP server answers; nothing inside the SDK is mocked.

function body(string $code, string $message): string
{
    return json_encode([
        'success' => false,
        'error' => $message,
        'error_detail' => [
            'code' => $code,
            'message' => $message,
            'action' => 'Do the thing',
            'docs_url' => 'https://shopsavvy.com/data/documentation/errors#' . strtolower($code),
        ],
        'meta' => ['request_id' => 'req_123'],
    ]);
}

$scenario = $_GET['ids'] ?? '';
header('Content-Type: application/json');

switch ($scenario) {
    case 'rate_limit':
        http_response_code(429);
        header('Retry-After: 7');
        echo body('ERR_RATE_LIMIT', 'Rate limit exceeded');
        break;
    case 'credits':
        http_response_code(429);
        echo body('ERR_CREDITS_EXHAUSTED', 'Credits exhausted');
        break;
    case 'plain_429':
        http_response_code(429);
        header('Retry-After: 3');
        echo json_encode(['success' => false, 'error' => 'Too many requests']);
        break;
    case 'auth':
        http_response_code(401);
        echo body('ERR_AUTH_INVALID', 'Invalid API key');
        break;
    case 'not_found':
        http_response_code(404);
        echo body('ERR_PRODUCT_NOT_FOUND', 'Product not found');
        break;
    case 'invalid':
        http_response_code(400);
        echo body('ERR_INVALID_PARAMS', 'Bad ids');
        break;
    case 'subscription':
        http_response_code(403);
        echo body('ERR_SUBSCRIPTION_REQUIRED', 'Subscription required');
        break;
    case 'html':
        http_response_code(502);
        header('Content-Type: text/html');
        echo '<html>bad gateway</html>';
        break;
    default:
        http_response_code(500);
        echo body('ERR_INTERNAL', 'unknown scenario');
}
