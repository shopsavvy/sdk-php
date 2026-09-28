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
use ShopSavvy\SDK\Models\OfferWithHistory;
use ShopSavvy\SDK\Models\PriceHistoryEntry;
use ShopSavvy\SDK\Models\PriceHistoryResponse;
use ShopSavvy\SDK\Models\ProductWithPriceHistory;
use ShopSavvy\SDK\Models\ProductDetails;
use ShopSavvy\SDK\Models\RemoveResponse;
use ShopSavvy\SDK\Models\ScheduledProduct;
use ShopSavvy\SDK\Models\ScheduledProductsResponse;
use ShopSavvy\SDK\Models\ScheduleResponse;
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

    public function testPriceHistorySendsRetailerFilter(): void
    {
        $client = $this->client([self::json(['success' => true, 'data' => []])]);
        $client->getPriceHistory('611247373064', '2026-01-01', '2026-01-31', 'amazon.com');

        $this->assertSame(
            ['ids' => '611247373064', 'start' => '2026-01-01', 'end' => '2026-01-31', 'retailer' => 'amazon.com'],
            $this->lastQuery()
        );
    }

    /**
     * Feeds a response in the exact shape GET /products/offers/history returns (one entry
     * per product, each with offers, each offer with history) through the real parser.
     */
    public function testPriceHistoryParsesProductsOffersAndHistory(): void
    {
        $body = (string) file_get_contents(__DIR__ . '/fixture-price-history-response.json');
        $client = $this->client([new Response(200, ['Content-Type' => 'application/json'], $body)]);

        $response = $client->getPriceHistory('611247373064,611247369449', '2022-11-20', '2022-11-27');

        $this->assertInstanceOf(PriceHistoryResponse::class, $response);
        $this->assertTrue($response->success);
        $this->assertSame(14, $response->creditsUsed());
        $this->assertSame(986, $response->creditsRemaining());
        $this->assertSame(999, $response->meta?->rateLimitRemaining);
        $this->assertCount(2, $response->data);

        // Product 1: full product fields + two offers
        $mini = $response->data[0];
        $this->assertInstanceOf(ProductWithPriceHistory::class, $mini);
        $this->assertSame('Keurig K-Mini Single Serve Coffee Maker, Black', $mini->title);
        $this->assertSame('3ONn300xybP3y66ibqc1', $mini->shopsavvy);
        $this->assertSame('611247373064', $mini->barcode);
        $this->assertSame('B07G14HTBZ', $mini->amazon);
        $this->assertSame('K-MINI', $mini->model);
        $this->assertSame('Keurig K-Mini', $mini->titleShort);
        $this->assertSame(['value' => 4.6, 'count' => 51234], $mini->rating);
        $this->assertSame('B07G14HTBZ', $mini->identifiers['amazon'] ?? null);
        $this->assertCount(2, $mini->offers);

        $amazon = $mini->offers[0];
        $this->assertInstanceOf(OfferWithHistory::class, $amazon);
        $this->assertSame('0IUouCFtZEhxeOablTPl', $amazon->id);
        $this->assertSame('Amazon', $amazon->retailer);
        $this->assertSame(74.96, $amazon->price);
        $this->assertSame('USD', $amazon->currency);
        $this->assertSame('in', $amazon->availability);
        $this->assertSame('new', $amazon->condition);
        $this->assertSame('ACME Deals', $amazon->seller);
        $this->assertSame('https://www.amazon.com/dp/B07G14HTBZ?m=A1GKQADQC2VI6E', $amazon->url);
        $this->assertSame('2022-11-27T22:36:33.236Z', $amazon->timestamp);
        $this->assertCount(3, $amazon->history);

        $newest = $amazon->history[0];
        $this->assertInstanceOf(PriceHistoryEntry::class, $newest);
        $this->assertSame('2022-11-27T22:36:33.236Z', $newest->timestamp);
        $this->assertSame(74.96, $newest->price);
        $this->assertSame('USD', $newest->currency);
        $this->assertSame('in', $newest->availability);
        $this->assertSame('out', $amazon->history[1]->availability);
        $this->assertSame(70.99, $amazon->history[1]->price);

        // A point with no recorded currency and unknown (omitted) availability
        $oldest = $amazon->history[2];
        $this->assertSame(79.99, $oldest->price);
        $this->assertSame('2022-11-21T08:15:00.000Z', $oldest->timestamp);
        $this->assertNull($oldest->currency);
        $this->assertNull($oldest->availability);

        // Offer with null seller and omitted availability
        $bestBuy = $mini->offers[1];
        $this->assertSame('Best Buy', $bestBuy->retailer);
        $this->assertNull($bestBuy->seller);
        $this->assertNull($bestBuy->availability);
        $this->assertSame(59.99, $bestBuy->price);
        $this->assertCount(2, $bestBuy->history);
        $this->assertSame(64.99, $bestBuy->history[1]->price);

        // Product 2: nullable product fields, an offer with an empty history
        $elite = $response->data[1];
        $this->assertSame('DrKWneG0MpFlZpwZXNYa', $elite->shopsavvy);
        $this->assertSame('611247369449', $elite->barcode);
        $this->assertNull($elite->amazon);
        $this->assertNull($elite->category);
        $this->assertNull($elite->color);
        $this->assertNull($elite->mpn);
        $this->assertSame([], $elite->images);
        $this->assertCount(1, $elite->offers);
        $this->assertSame('eBay', $elite->offers[0]->retailer);
        $this->assertSame(89.5, $elite->offers[0]->price);
        $this->assertSame([], $elite->offers[0]->history);
    }

    public function testScheduleSendsQueryParametersWithPut(): void
    {
        $client = $this->client([self::json(['success' => true, 'data' => [
            ['title' => 'Keurig K-Mini', 'shopsavvy' => 'products/3ONn300xybP3y66ibqc1', 'schedule' => 'daily', 'retailer' => 'amazon.com'],
        ]])]);
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

    public function testScheduleWithoutRetailerSendsOnlyIdsAndSchedule(): void
    {
        $client = $this->client([self::json(['success' => true, 'data' => []])]);
        $client->scheduleProductMonitoring('611247373064', 'hourly');

        $request = $this->lastRequest();
        $this->assertSame('PUT', $request->getMethod());
        $this->assertSame('/v1/products/scheduled', $request->getUri()->getPath());
        $this->assertSame(['ids' => '611247373064', 'schedule' => 'hourly'], $this->lastQuery());
        $this->assertSame('', (string) $request->getBody());
    }

    /**
     * The endpoint takes several products as a comma-separated `ids` list; the SDK has no
     * separate batch method, so a comma list passed as the identifier must reach the wire as-is.
     */
    public function testScheduleAndUnscheduleSeveralProductsViaCommaSeparatedIds(): void
    {
        $client = $this->client([
            self::json(['success' => true, 'data' => []]),
            self::json(['success' => true, 'data' => []]),
        ]);

        $client->scheduleProductMonitoring('611247373064,611247369449', 'weekly', 'bestbuy.com');
        $this->assertSame('PUT', $this->lastRequest()->getMethod());
        $this->assertSame('/v1/products/scheduled', $this->lastRequest()->getUri()->getPath());
        $this->assertSame(
            ['ids' => '611247373064,611247369449', 'schedule' => 'weekly', 'retailer' => 'bestbuy.com'],
            $this->lastQuery()
        );

        $client->removeProductFromSchedule('611247373064,611247369449');
        $this->assertSame('DELETE', $this->lastRequest()->getMethod());
        $this->assertSame('/v1/products/scheduled', $this->lastRequest()->getUri()->getPath());
        $this->assertSame(['ids' => '611247373064,611247369449'], $this->lastQuery());
        $this->assertSame('', (string) $this->lastRequest()->getBody());
    }

    public function testRemoveFromScheduleSendsIdsWithDelete(): void
    {
        $client = $this->client([self::json(['success' => true, 'data' => []])]);
        $client->removeProductFromSchedule('611247373064');

        $this->assertSame('DELETE', $this->lastRequest()->getMethod());
        $this->assertSame('/v1/products/scheduled', $this->lastRequest()->getUri()->getPath());
        $this->assertSame(['ids' => '611247373064'], $this->lastQuery());
    }

    /**
     * PUT /products/scheduled answers `{ success, data: [ { ...product, schedule, retailer? } ], meta }`
     * (refinery entrypoint-api.ts `schedule`).
     */
    public function testScheduleParsesScheduledProductsWithEveryProductField(): void
    {
        $client = $this->client([self::fixture('fixture-schedule-response.json')]);

        $response = $client->scheduleProductMonitoring('611247373064,B08N5WRWNW', 'hourly', 'amazon.com');

        $this->assertInstanceOf(ScheduleResponse::class, $response);
        $this->assertTrue($response->success);
        $this->assertCount(2, $response->data);
        $this->assertContainsOnlyInstancesOf(ScheduledProduct::class, $response->data);
        $this->assertContainsOnlyInstancesOf(ProductDetails::class, $response->data);

        $keurig = $response->data[0];
        $this->assertSame('Keurig K-Mini Single Serve Coffee Maker, Black', $keurig->title);
        $this->assertSame('products/3ONn300xybP3y66ibqc1', $keurig->shopsavvy);
        $this->assertSame('611247373064', $keurig->barcode);
        $this->assertSame('B07GV2S1GS', $keurig->amazon);
        $this->assertSame('K-Mini', $keurig->model);
        $this->assertSame('5000200237', $keurig->mpn);
        $this->assertSame('Keurig', $keurig->brand);
        $this->assertSame('Black', $keurig->color);
        $this->assertSame(['https://images.example/keurig-1.jpg', 'https://images.example/keurig-2.jpg'], $keurig->images);
        $this->assertSame('Keurig K-Mini', $keurig->titleShort);
        $this->assertSame(['value' => 4.5, 'count' => 81234], $keurig->rating);
        $this->assertSame('hourly', $keurig->schedule);
        $this->assertSame('hourly', $keurig->getFrequency());
        $this->assertTrue($keurig->isHourly());
        $this->assertFalse($keurig->isDaily());
        $this->assertSame('amazon.com', $keurig->retailer);

        $echo = $response->data[1];
        $this->assertSame('products/Q2x9AbCdEf', $echo->shopsavvy);
        $this->assertNull($echo->category);
        $this->assertNull($echo->barcode);
        $this->assertSame([], $echo->images);
        $this->assertSame('amazon.com', $echo->retailer);

        $this->assertSame(2, $response->creditsUsed());
        $this->assertSame(998, $response->creditsRemaining());
        $this->assertSame(59, $response->meta?->rateLimitRemaining);
    }

    /**
     * DELETE /products/scheduled answers `{ success, message, meta }` — no `data`
     * (refinery entrypoint-api.ts `unschedule`).
     */
    public function testUnscheduleParsesSuccessMessageAndMeta(): void
    {
        $client = $this->client([self::fixture('fixture-unschedule-response.json')]);

        $response = $client->removeProductFromSchedule('611247373064');

        $this->assertInstanceOf(RemoveResponse::class, $response);
        $this->assertTrue($response->success);
        $this->assertSame('Products successfully removed from schedule', $response->message);
        $this->assertNotNull($response->meta);
        $this->assertSame(0, $response->meta->creditsUsed);
        $this->assertSame(0, $response->meta->creditsRemaining);
        $this->assertSame(0, $response->meta->rateLimitRemaining);
    }

    /**
     * GET /products/scheduled answers `{ success, data: [ { ...product, schedule?, retailer? } ], meta }`;
     * `schedule` is omitted for an interval with no Data API label and `retailer` when there is none
     * (refinery entrypoint-api.ts `scheduled`).
     */
    public function testScheduledListParsesEntriesWithOptionalScheduleAndRetailer(): void
    {
        $client = $this->client([self::fixture('fixture-scheduled-list-response.json')]);

        $response = $client->getScheduledProducts();

        $this->assertSame('GET', $this->lastRequest()->getMethod());
        $this->assertSame('/v1/products/scheduled', $this->lastRequest()->getUri()->getPath());
        $this->assertInstanceOf(ScheduledProductsResponse::class, $response);
        $this->assertTrue($response->success);
        $this->assertContainsOnlyInstancesOf(ScheduledProduct::class, $response->data);
        $this->assertSame(
            ['products/3ONn300xybP3y66ibqc1', 'products/Q2x9AbCdEf', 'products/Zz81Sony'],
            array_map(fn(ScheduledProduct $p) => $p->shopsavvy, $response->data)
        );
        $this->assertSame(['daily', 'weekly', null], array_map(fn(ScheduledProduct $p) => $p->schedule, $response->data));
        $this->assertSame(['bestbuy.com', null, null], array_map(fn(ScheduledProduct $p) => $p->retailer, $response->data));

        [$keurig, $echo, $sony] = $response->data;
        $this->assertTrue($keurig->isDaily());
        $this->assertSame('Keurig K-Mini Single Serve Coffee Maker, Black', $keurig->title);
        $this->assertSame('611247373064', $keurig->barcode);
        $this->assertTrue($echo->isWeekly());
        $this->assertSame('B08N5WRWNW', $echo->amazon);
        $this->assertFalse($sony->isHourly());
        $this->assertFalse($sony->isDaily());
        $this->assertFalse($sony->isWeekly());
        $this->assertSame('027242923232', $sony->barcode);
        $this->assertSame(0, $response->creditsUsed());
    }

    /**
     * `format=csv` makes the endpoints answer text/csv. The client used to JSON-decode every
     * body, so each CSV call threw "Failed to decode JSON response".
     */
    public function testCsvFormatReturnsTheRawCsvText(): void
    {
        $productsCsv = "shopsavvy,barcode,amazon,title,category,brand,color,mpn,model,image\r\n"
            . "products/3ONn300xybP3y66ibqc1,611247373064,B07GV2S1GS,\"Keurig K-Mini, 6-12oz\",Home & Kitchen,Keurig,Black,5000200237,K-Mini,https://images.example/keurig-1.jpg\r\n";
        $offersCsv = "shopsavvy,barcode,title,currency-Amazon,price-Amazon,availability-Amazon\r\n"
            . "products/3ONn300xybP3y66ibqc1,611247373064,Keurig K-Mini,USD,58.86,in\r\n";
        $historyCsv = "date,retailer,price,currency,availability\r\n2026-01-02T00:00:00.000Z,Amazon,58.86,USD,in\r\n";
        $csv = fn(string $body) => new Response(200, ['Content-Type' => 'text/csv; charset=utf-8'], $body);

        $client = $this->client([
            $csv($productsCsv), $csv($productsCsv), $csv($offersCsv), $csv($offersCsv), $csv($historyCsv),
        ]);

        $this->assertSame($productsCsv, $client->getProductDetails('611247373064', 'csv'));
        $this->assertSame(['ids' => '611247373064', 'format' => 'csv'], $this->lastQuery());
        $this->assertSame($productsCsv, $client->getProductDetailsBatch(['611247373064', 'B0788F3R8X'], 'csv'));
        $this->assertSame($offersCsv, $client->getCurrentOffers('611247373064', 'amazon.com', 'csv'));
        $this->assertSame(['ids' => '611247373064', 'retailer' => 'amazon.com', 'format' => 'csv'], $this->lastQuery());
        $this->assertSame($offersCsv, $client->getCurrentOffersBatch(['611247373064', 'B0788F3R8X'], null, 'csv'));
        $this->assertSame(['ids' => '611247373064,B0788F3R8X', 'format' => 'csv'], $this->lastQuery());
        $this->assertSame($historyCsv, $client->getPriceHistory('611247373064', '2026-01-01', '2026-01-31', null, 'csv'));
        $this->assertSame(
            ['ids' => '611247373064', 'start' => '2026-01-01', 'end' => '2026-01-31', 'format' => 'csv'],
            $this->lastQuery()
        );
    }

    public function testJsonFormatStillReturnsTypedResponses(): void
    {
        $client = $this->client([
            self::json(['success' => true, 'data' => [['title' => 'Keurig K-Mini', 'shopsavvy' => 'products/3ONn300xybP3y66ibqc1']]]),
            self::json(['success' => true, 'data' => []]),
        ]);

        $details = $client->getProductDetails('611247373064', 'json');
        $this->assertInstanceOf(ApiResponse::class, $details);
        $this->assertSame('Keurig K-Mini', $details->data[0]['title']);

        $history = $client->getPriceHistory('611247373064', '2026-01-01', '2026-01-31', null, 'json');
        $this->assertInstanceOf(PriceHistoryResponse::class, $history);
    }

    private static function fixture(string $name): Response
    {
        $body = file_get_contents(__DIR__ . '/' . $name);
        if ($body === false) {
            throw new \RuntimeException("Missing fixture $name");
        }

        return new Response(200, ['Content-Type' => 'application/json'], $body);
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
