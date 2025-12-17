<?php

declare(strict_types=1);

namespace ShopSavvy\SDK\Tests;

use PHPUnit\Framework\TestCase;
use ShopSavvy\SDK\ShopSavvyClient;
use ShopSavvy\SDK\Exceptions\ShopSavvyException;

class ShopSavvyClientTest extends TestCase
{
    public function testClientCreation(): void
    {
        $client = new ShopSavvyClient('ss_test_valid_key_12345');
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
            'ss_test_valid_key_12345',
            'https://custom.api.com/v1',
            60.0
        );
        
        $this->assertInstanceOf(ShopSavvyClient::class, $client);
    }
}