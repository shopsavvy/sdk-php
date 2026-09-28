<?php

declare(strict_types=1);

namespace ShopSavvy\SDK\Tests;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use ShopSavvy\SDK\Exceptions\ShopSavvyAuthenticationException;
use ShopSavvy\SDK\Exceptions\ShopSavvyException;
use ShopSavvy\SDK\Exceptions\ShopSavvyNotFoundException;
use ShopSavvy\SDK\Exceptions\ShopSavvyRateLimitException;
use ShopSavvy\SDK\Exceptions\ShopSavvyValidationException;
use ShopSavvy\SDK\Models\ApiResponse;
use ShopSavvy\SDK\Models\ProductDetails;
use ShopSavvy\SDK\ShopSavvyClient;

/**
 * Drives the client through Guzzle's MockHandler so each test pins the exact
 * request the SDK sends (method, path, query) and parses a response body in
 * the shape the Data API really returns (shopsavvy.com/data/documentation).
 */
class ShopSavvyClientHttpTest extends TestCase
{
    private const KEY = 'ss_live_0123456789abcdef0123456789abcdef';

    /** @var array<int, array{request: RequestInterface}> */
    private array $history = [];

    /**
     * @param array<int, Response> $responses
     */
    private function client(array $responses): ShopSavvyClient
    {
        $this->history = [];
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));

        return new ShopSavvyClient(self::KEY, null, 30.0, ['handler' => $stack]);
    }

    /**
     * @param array<string, mixed> $body
     */
    private static function json(array $body, int $status = 200): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'], json_encode($body, JSON_THROW_ON_ERROR));
    }

    private function lastRequest(): RequestInterface
    {
        return $this->history[count($this->history) - 1]['request'];
    }

    /**
     * @return array<string, string>
     */
    private function lastQuery(): array
    {
        parse_str($this->lastRequest()->getUri()->getQuery(), $query);
        /** @var array<string, string> $query */
        return $query;
    }

    public function testSendsAuthAndUserAgentHeaders(): void
    {
        $client = $this->client([self::json(['success' => true, 'data' => []])]);
        $client->getProductDetails('611247373064');

        $request = $this->lastRequest();
        $this->assertSame('Bearer ' . self::KEY, $request->getHeaderLine('Authorization'));
        $this->assertSame('ShopSavvy-PHP-SDK/' . ShopSavvyClient::VERSION, $request->getHeaderLine('User-Agent'));
    }

    public function testSearchMapsProductsWithNumericBarcodes(): void
    {
        // The API sends barcode as a JSON number. Before the fix this threw a
        // TypeError (int passed to ?string under strict_types) and, before that,
        // ProductSearchResult could not even be autoloaded.
        $client = $this->client([self::json([
            'success' => true,
            'data' => [[
                'title' => 'Keurig K-Mini Single Serve Coffee Maker, Black',
                'shopsavvy' => 'products/3ONn300xybP3y66ibqc1',
                'brand' => 'Keurig',
                'barcode' => 611247373064,
                'amazon' => 'B07G14HTBZ',
                'model' => 'K-MINI',
                'mpn' => 5000200237,
                'images' => ['https://images-na.ssl-images-amazon.com/images/I/31jy5fSzyRL.jpg'],
            ]],
            'pagination' => ['total' => 47, 'limit' => 10, 'offset' => 0, 'returned' => 1],
            'meta' => ['credits_used' => 1, 'credits_remaining' => 999, 'rate_limit_remaining' => 58],
        ])]);

        $result = $client->searchProducts('keurig k-mini', 10);

        $this->assertSame('GET', $this->lastRequest()->getMethod());
        $this->assertSame('/v1/products/search', $this->lastRequest()->getUri()->getPath());
        $this->assertSame(['q' => 'keurig k-mini', 'limit' => '10'], $this->lastQuery());

        $this->assertCount(1, $result->data);
        $product = $result->data[0];
        $this->assertInstanceOf(ProductDetails::class, $product);
        $this->assertSame('611247373064', $product->barcode);
        $this->assertSame('5000200237', $product->mpn);
        $this->assertSame('products/3ONn300xybP3y66ibqc1', $product->shopsavvy);
        $this->assertSame(47, $result->pagination?->total);
        $this->assertSame(999, $result->creditsRemaining());
    }

    public function testCurrentOffersRequestAndResponse(): void
    {
        $client = $this->client([self::json([
            'success' => true,
            'data' => [[
                'title' => 'Keurig K-Mini',
                'shopsavvy' => 'products/3ONn300xybP3y66ibqc1',
                'offers' => [[
                    'id' => '0RmL0ZMUesP2PmSlP97G',
                    'availability' => 'in',
                    'condition' => 'new',
                    'retailer' => 'Amazon',
                    'price' => 58.86,
                    'URL' => 'https://www.amazon.com/dp/B07GV2S1GS',
                    'timestamp' => '2022-05-03T22:52:06.802Z',
                ]],
            ]],
            'meta' => ['credits_used' => 2, 'credits_remaining' => 998],
        ])]);

        $response = $client->getCurrentOffers('611247373064', 'amazon.com');

        $this->assertSame('/v1/products/offers', $this->lastRequest()->getUri()->getPath());
        $this->assertSame(['ids' => '611247373064', 'retailer' => 'amazon.com'], $this->lastQuery());
        $this->assertInstanceOf(ApiResponse::class, $response);
        $this->assertSame(58.86, $response->data[0]['offers'][0]['price']);
        $this->assertSame(2, $response->creditsUsed());
    }

    public function testPriceHistorySendsStartAndEnd(): void
    {
        $client = $this->client([self::json(['success' => true, 'data' => []])]);
        $client->getPriceHistory('611247373064', '2026-01-01', '2026-01-31');

        $this->assertSame('/v1/products/offers/history', $this->lastRequest()->getUri()->getPath());
        $this->assertSame(
            ['ids' => '611247373064', 'start' => '2026-01-01', 'end' => '2026-01-31'],
            $this->lastQuery()
        );
    }

    public function testScheduleSendsQueryParametersWithPut(): void
    {
        $client = $this->client([self::json(['success' => true, 'data' => [['title' => 'x', 'schedule' => 'daily']]])]);
        $client->scheduleProductMonitoring('611247373064', 'daily', 'amazon.com');

        $request = $this->lastRequest();
        $this->assertSame('PUT', $request->getMethod());
        $this->assertSame('/v1/products/scheduled', $request->getUri()->getPath());
        $this->assertSame(
            ['ids' => '611247373064', 'schedule' => 'daily', 'retailer' => 'amazon.com'],
            $this->lastQuery()
        );
        $this->assertSame('', (string) $request->getBody());
    }

    public function testRemoveFromScheduleSendsIdsWithDelete(): void
    {
        $client = $this->client([self::json(['success' => true, 'data' => []])]);
        $client->removeProductFromSchedule('611247373064');

        $this->assertSame('DELETE', $this->lastRequest()->getMethod());
        $this->assertSame('/v1/products/scheduled', $this->lastRequest()->getUri()->getPath());
        $this->assertSame(['ids' => '611247373064'], $this->lastQuery());
    }

    public function testDealsPassThroughParameters(): void
    {
        $client = $this->client([self::json(['success' => true, 'deals' => [['path' => 'deals/abc123']]])]);
        $deals = $client->getDeals(['sort' => 'top-day', 'limit' => 5]);

        $this->assertSame('/v1/deals', $this->lastRequest()->getUri()->getPath());
        $this->assertSame(['sort' => 'top-day', 'limit' => '5'], $this->lastQuery());
        $this->assertSame('deals/abc123', $deals['deals'][0]['path']);
    }

    /**
     * @return array<string, array{int, class-string<ShopSavvyException>}>
     */
    public static function errorStatusProvider(): array
    {
        return [
            '400 invalid params' => [400, ShopSavvyValidationException::class],
            '401 bad key' => [401, ShopSavvyAuthenticationException::class],
            '404 not found' => [404, ShopSavvyNotFoundException::class],
            '429 rate limited' => [429, ShopSavvyRateLimitException::class],
            '500 server error' => [500, ShopSavvyException::class],
        ];
    }

    /**
     * Every exception subclass must be autoloadable on its own: they used to
     * share one file, so PSR-4 could not find any of them and a 401 surfaced as
     * a fatal "Class not found" instead of a catchable ShopSavvyException.
     *
     * @dataProvider errorStatusProvider
     * @param class-string<ShopSavvyException> $expected
     */
    public function testHttpErrorsMapToTypedExceptions(int $status, string $expected): void
    {
        $client = $this->client([self::json(['success' => false, 'error' => 'API said no'], $status)]);

        try {
            $client->getProductDetails('611247373064');
            $this->fail('Expected ' . $expected);
        } catch (ShopSavvyException $e) {
            $this->assertInstanceOf($expected, $e);
            $this->assertStringContainsString('API said no', $e->getMessage());
            $this->assertSame($status, $e->getCode());
        }
    }
}
