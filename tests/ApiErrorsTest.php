<?php

declare(strict_types=1);

namespace ShopSavvy\SDK\Tests;

use PHPUnit\Framework\TestCase;
use ShopSavvy\SDK\Exceptions\ShopSavvyAuthenticationException;
use ShopSavvy\SDK\Exceptions\ShopSavvyCreditsExhaustedException;
use ShopSavvy\SDK\Exceptions\ShopSavvyException;
use ShopSavvy\SDK\Exceptions\ShopSavvyNotFoundException;
use ShopSavvy\SDK\Exceptions\ShopSavvyRateLimitException;
use ShopSavvy\SDK\Exceptions\ShopSavvyValidationException;
use ShopSavvy\SDK\ShopSavvyClient;

/**
 * Typed API errors, driven through the real client and Guzzle against a real local HTTP
 * server (`php -S` with tests/fixture-error-server.php). No mocking of SDK internals.
 */
class ApiErrorsTest extends TestCase
{
    private const KEY = 'ss_test_0123456789abcdef0123456789abcdef';

    /** @var resource|null */
    private static $process = null;
    private static string $baseUrl = '';

    public static function setUpBeforeClass(): void
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        $port = (int) explode(':', (string) stream_socket_get_name($socket, false))[1];
        fclose($socket);

        $cmd = [PHP_BINARY, '-S', "127.0.0.1:$port", __DIR__ . '/fixture-error-server.php'];
        self::$process = proc_open($cmd, [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);

        for ($i = 0; $i < 50; $i++) {
            $conn = @fsockopen('127.0.0.1', $port);
            if ($conn !== false) {
                fclose($conn);
                self::$baseUrl = "http://127.0.0.1:$port/v1";
                return;
            }
            usleep(100000);
        }
        self::fail('local test server did not start');
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$process !== null) {
            proc_terminate(self::$process);
            proc_close(self::$process);
        }
    }

    private function caught(string $scenario, ?ShopSavvyClient $client = null): ShopSavvyException
    {
        $client ??= new ShopSavvyClient(self::KEY, self::$baseUrl);
        try {
            $client->getProductDetails($scenario);
        } catch (ShopSavvyException $e) {
            return $e;
        }
        $this->fail('expected the call to throw');
    }

    public function testRateLimitCarriesDetailAndRetryAfter(): void
    {
        $e = $this->caught('rate_limit');
        $this->assertInstanceOf(ShopSavvyRateLimitException::class, $e);
        $this->assertNotInstanceOf(ShopSavvyCreditsExhaustedException::class, $e);
        $this->assertSame('Rate limit exceeded: Rate limit exceeded', $e->getMessage());
        $this->assertSame(429, $e->getStatusCode());
        $this->assertSame(429, $e->getCode());
        $this->assertSame('ERR_RATE_LIMIT', $e->errorCode);
        $this->assertSame('Do the thing', $e->action);
        $this->assertSame('https://shopsavvy.com/data/documentation/errors#err_rate_limit', $e->docsUrl);
        $this->assertSame('req_123', $e->requestId);
        $this->assertSame(7, $e->retryAfterSeconds);
    }

    public function testCreditsExhaustedIsItsOwnTypeNotARateLimit(): void
    {
        $e = $this->caught('credits');
        $this->assertInstanceOf(ShopSavvyCreditsExhaustedException::class, $e);
        $this->assertNotInstanceOf(ShopSavvyRateLimitException::class, $e);
        $this->assertSame('ERR_CREDITS_EXHAUSTED', $e->errorCode);
        $this->assertNull($e->retryAfterSeconds);
    }

    public function testPlain429WithoutDetailStaysRateLimit(): void
    {
        $e = $this->caught('plain_429');
        $this->assertInstanceOf(ShopSavvyRateLimitException::class, $e);
        $this->assertNull($e->errorCode);
        $this->assertNull($e->action);
        $this->assertNull($e->docsUrl);
        $this->assertNull($e->requestId);
        $this->assertSame(3, $e->retryAfterSeconds);
        $this->assertStringContainsString('Too many requests', $e->getMessage());
    }

    public function testStatusClasses(): void
    {
        $this->assertInstanceOf(ShopSavvyAuthenticationException::class, $this->caught('auth'));
        $this->assertInstanceOf(ShopSavvyNotFoundException::class, $this->caught('not_found'));
        $this->assertInstanceOf(ShopSavvyValidationException::class, $this->caught('invalid'));
    }

    public function testOtherCodesArePlainExceptionWithDetail(): void
    {
        $e = $this->caught('subscription');
        $this->assertSame(ShopSavvyException::class, $e::class);
        $this->assertSame('ERR_SUBSCRIPTION_REQUIRED', $e->errorCode);
        $this->assertSame(403, $e->getStatusCode());
    }

    public function testNonJsonErrorBodyStillTyped(): void
    {
        $e = $this->caught('html');
        $this->assertSame(ShopSavvyException::class, $e::class);
        $this->assertSame(502, $e->getStatusCode());
        $this->assertNull($e->errorCode);
    }

    /** Costs nothing: a well-formed nonexistent key is rejected before any credit is touched. */
    public function testLiveBogusKeyGetsAuthRevokedWithCodeAndDocsUrl(): void
    {
        $client = new ShopSavvyClient('ss_live_' . str_repeat('a', 32));
        $e = $this->caught('611247373064', $client);
        $this->assertInstanceOf(ShopSavvyAuthenticationException::class, $e);
        $this->assertSame('ERR_AUTH_REVOKED', $e->errorCode);
        $this->assertStringStartsWith('https://shopsavvy.com/data/documentation/errors', (string) $e->docsUrl);
        $this->assertSame(401, $e->getStatusCode());
    }
}
