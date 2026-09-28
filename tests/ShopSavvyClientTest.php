<?php

declare(strict_types=1);

namespace ShopSavvy\SDK\Tests;

use PHPUnit\Framework\TestCase;
use ShopSavvy\SDK\ShopSavvyClient;
use ShopSavvy\SDK\Exceptions\ShopSavvyException;

class ShopSavvyClientTest extends TestCase
{
    // Real keys are ss_live_/ss_test_ + 32 hex characters (the API rejects
    // anything else); the old fixture 'ss_test_valid_key_12345' contained
    // underscores in the body and never matched the client's own format check.
    private const VALID_KEY = 'ss_test_0123456789abcdef0123456789abcdef';

    public function testClientCreation(): void
    {
        $client = new ShopSavvyClient(self::VALID_KEY);
        $this->assertInstanceOf(ShopSavvyClient::class, $client);
    }
    
    public function testInvalidApiKeyThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid API key format');
        
        new ShopSavvyClient('invalid_key');
    }
    
    public function testEmptyApiKeyThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('API key is required');
        
        new ShopSavvyClient('');
    }
    
    public function testCustomConfiguration(): void
    {
        $client = new ShopSavvyClient(
            self::VALID_KEY,
            'https://custom.api.com/v1',
            60.0
        );
        
        $this->assertInstanceOf(ShopSavvyClient::class, $client);
    }
}