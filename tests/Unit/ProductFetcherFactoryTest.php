<?php

namespace TheDiamondBox\ShopSync\Tests\Unit;

use TheDiamondBox\ShopSync\Exceptions\ClientNotFoundException;
use TheDiamondBox\ShopSync\Services\Fetchers\Product\ProductFetcherFactory;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;
use Mockery;

class ProductFetcherFactoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Clear any previous log expectations
        Log::shouldReceive('info', 'error')->byDefault();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test creating WL (WhiteLabel) mode fetcher
     */
    public function testCanCreateWlModeFetcher()
    {
        $fetcher = ProductFetcherFactory::make('wl');

        $this->assertInstanceOf(ProductFetcherInterface::class, $fetcher);
        $this->assertInstanceOf(DatabaseProductFetcher::class, $fetcher);
    }

    /**
     * Test creating WL mode fetcher with uppercase
     */
    public function testCanCreateWlModeFetcherWithUppercase()
    {
        $fetcher = ProductFetcherFactory::make('WL');

        $this->assertInstanceOf(DatabaseProductFetcher::class, $fetcher);
    }

    /**
     * Test creating WL mode fetcher with mixed case
     */
    public function testCanCreateWlModeFetcherWithMixedCase()
    {
        $fetcher = ProductFetcherFactory::make('Wl');

        $this->assertInstanceOf(DatabaseProductFetcher::class, $fetcher);
    }

    /**
     * Test creating WTM mode fetcher with valid client
     */
    public function testCanCreateWtmModeFetcherWithValidClient()
    {
        // Create a mock request
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('header')
                ->with('client-id')
                ->andReturn('valid-client-id');

        // Create a mock client
        $client = Mockery::mock(Client::class);

        // Mock the Client query
        $queryMock = Mockery::mock();
        $queryMock->shouldReceive('find')
                  ->with('valid-client-id')
                  ->andReturn($client);

        Client::shouldReceive('query')
              ->andReturn($queryMock);

        $fetcher = ProductFetcherFactory::make('wtm', $request);

        $this->assertInstanceOf(ProductFetcherInterface::class, $fetcher);
        $this->assertInstanceOf(ApiProductFetcher::class, $fetcher);
    }

    /**
     * Test WTM mode throws exception when request is null
     */
    public function testWtmModeThrowsExceptionWhenRequestIsNull()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Request object is required when using 'wtm' mode.");

        ProductFetcherFactory::make('wtm', null);
    }

    /**
     * Test WTM mode throws exception when client-id header is missing
     */
    public function testWtmModeThrowsExceptionWhenClientIdHeaderIsMissing()
    {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('header')
                ->with('client-id')
                ->andReturn(null);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Client ID header is required when using 'wtm' mode.");

        ProductFetcherFactory::make('wtm', $request);
    }

    /**
     * Test WTM mode throws exception when client-id header is empty
     */
    public function testWtmModeThrowsExceptionWhenClientIdHeaderIsEmpty()
    {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('header')
                ->with('client-id')
                ->andReturn('');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Client ID header is required when using 'wtm' mode.");

        ProductFetcherFactory::make('wtm', $request);
    }

    /**
     * Test WTM mode throws ClientNotFoundException when client not found
     */
    public function testWtmModeThrowsClientNotFoundExceptionWhenClientNotFound()
    {
        $clientId = 'nonexistent-client';

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('header')
                ->with('client-id')
                ->andReturn($clientId);

        // Mock the Client query to return null (not found)
        $queryMock = Mockery::mock();
        $queryMock->shouldReceive('find')
                  ->with($clientId)
                  ->andReturn(null);

        Client::shouldReceive('query')
              ->andReturn($queryMock);

        $this->expectException(ClientNotFoundException::class);
        $this->expectExceptionMessage("Client not found: {$clientId}.");

        ProductFetcherFactory::make('wtm', $request);
    }

    /**
     * Test invalid mode throws InvalidArgumentException
     */
    public function testInvalidModeThrowsInvalidArgumentException()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid mode: invalid. Must be 'wl' (WhiteLabel) or 'wtm' (Watch the Market).");

        ProductFetcherFactory::make('invalid');
    }

    /**
     * Test makeFromConfig with default WL mode
     */
    public function testMakeFromConfigWithDefaultWlMode()
    {
        Config::shouldReceive('config')
              ->with('products-package.mode', 'wl')
              ->andReturn('wl');

        Log::shouldReceive('info')
           ->with('Creating ProductFetcher', ['mode' => 'wl'])
           ->once();

        $fetcher = ProductFetcherFactory::makeFromConfig();

        $this->assertInstanceOf(DatabaseProductFetcher::class, $fetcher);
    }

    /**
     * Test makeFromConfig with WTM mode and valid request
     */
    public function testMakeFromConfigWithWtmModeAndValidRequest()
    {
        Config::shouldReceive('config')
              ->with('products-package.mode', 'wl')
              ->andReturn('wtm');

        Log::shouldReceive('info')
           ->with('Creating ProductFetcher', ['mode' => 'wtm'])
           ->once();

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('header')
                ->with('client-id')
                ->andReturn('valid-client-id');

        $client = Mockery::mock(Client::class);

        $queryMock = Mockery::mock();
        $queryMock->shouldReceive('find')
                  ->with('valid-client-id')
                  ->andReturn($client);

        Client::shouldReceive('query')
              ->andReturn($queryMock);

        $fetcher = ProductFetcherFactory::makeFromConfig($request);

        $this->assertInstanceOf(ApiProductFetcher::class, $fetcher);
    }

    /**
     * Test makeFromConfig falls back to WL mode on invalid mode
     */
    public function testMakeFromConfigFallsBackToWlModeOnInvalidMode()
    {
        Config::shouldReceive('config')
              ->with('products-package.mode', 'wl')
              ->andReturn('invalid-mode');

        Log::shouldReceive('info')
           ->with('Creating ProductFetcher', ['mode' => 'invalid-mode'])
           ->once();

        Log::shouldReceive('error')
           ->with('Invalid ProductFetcher mode in config, falling back to WL mode', [
               'invalid_mode' => 'invalid-mode',
               'error' => "Invalid mode: invalid-mode. Must be 'wl' (WhiteLabel) or 'wtm' (Watch the Market)."
           ])
           ->once();

        $fetcher = ProductFetcherFactory::makeFromConfig();

        $this->assertInstanceOf(DatabaseProductFetcher::class, $fetcher);
    }

    /**
     * Test makeFromConfig does not fallback on request validation error
     */
    public function testMakeFromConfigDoesNotFallbackOnRequestValidationError()
    {
        Config::shouldReceive('config')
              ->with('products-package.mode', 'wl')
              ->andReturn('wtm');

        Log::shouldReceive('info')
           ->with('Creating ProductFetcher', ['mode' => 'wtm'])
           ->once();

        Log::shouldReceive('error')
           ->with('ProductFetcher creation failed due to request validation', [
               'mode' => 'wtm',
               'error' => "Request object is required when using 'wtm' mode."
           ])
           ->once();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Request object is required when using 'wtm' mode.");

        ProductFetcherFactory::makeFromConfig(null);
    }

    /**
     * Test makeFromConfig throws ClientNotFoundException
     */
    public function testMakeFromConfigThrowsClientNotFoundException()
    {
        Config::shouldReceive('config')
              ->with('products-package.mode', 'wl')
              ->andReturn('wtm');

        Log::shouldReceive('info')
           ->with('Creating ProductFetcher', ['mode' => 'wtm'])
           ->once();

        $clientId = 'nonexistent-client';

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('header')
                ->with('client-id')
                ->andReturn($clientId);

        $queryMock = Mockery::mock();
        $queryMock->shouldReceive('find')
                  ->with($clientId)
                  ->andReturn(null);

        Client::shouldReceive('query')
              ->andReturn($queryMock);

        Log::shouldReceive('error')
           ->with('Client not found', Mockery::on(function ($data) use ($clientId) {
               return $data['mode'] === 'wtm' &&
                      $data['client_id'] === $clientId &&
                      $data['error'] === "Client not found: {$clientId}." &&
                      is_array($data['context']);
           }))
           ->once();

        $this->expectException(ClientNotFoundException::class);

        ProductFetcherFactory::makeFromConfig($request);
    }

    /**
     * Test getAvailableModes returns correct structure
     */
    public function testGetAvailableModesReturnsCorrectStructure()
    {
        $modes = ProductFetcherFactory::getAvailableModes();

        $this->assertIsArray($modes);
        $this->assertArrayHasKey('wl', $modes);
        $this->assertArrayHasKey('wtm', $modes);

        $this->assertEquals('WhiteLabel', $modes['wl']['name']);
        $this->assertEquals(DatabaseProductFetcher::class, $modes['wl']['class']);

        $this->assertEquals('Watch the Market', $modes['wtm']['name']);
        $this->assertEquals(ApiProductFetcher::class, $modes['wtm']['class']);
    }

    /**
     * Test isValidMode returns true for valid modes
     */
    public function testIsValidModeReturnsTrueForValidModes()
    {
        $this->assertTrue(ProductFetcherFactory::isValidMode('wl'));
        $this->assertTrue(ProductFetcherFactory::isValidMode('wtm'));
        $this->assertTrue(ProductFetcherFactory::isValidMode('WL'));
        $this->assertTrue(ProductFetcherFactory::isValidMode('WTM'));
        $this->assertTrue(ProductFetcherFactory::isValidMode('Wl'));
        $this->assertTrue(ProductFetcherFactory::isValidMode('WtM'));
    }

    /**
     * Test isValidMode returns false for invalid modes
     */
    public function testIsValidModeReturnsFalseForInvalidModes()
    {
        $this->assertFalse(ProductFetcherFactory::isValidMode('invalid'));
        $this->assertFalse(ProductFetcherFactory::isValidMode(''));
        $this->assertFalse(ProductFetcherFactory::isValidMode('api'));
        $this->assertFalse(ProductFetcherFactory::isValidMode('database'));
    }

    /**
     * Test getConfigStatus with valid WL mode
     */
    public function testGetConfigStatusWithValidWlMode()
    {
        Config::shouldReceive('config')
              ->with('products-package.mode', 'wl')
              ->andReturn('wl');

        $status = ProductFetcherFactory::getConfigStatus();

        $this->assertIsArray($status);
        $this->assertEquals('wl', $status['current_mode']);
        $this->assertTrue($status['is_valid']);
        $this->assertTrue($status['fetcher_created']);
        $this->assertArrayHasKey('available_modes', $status);
        $this->assertArrayNotHasKey('error', $status);
    }

    /**
     * Test getConfigStatus with invalid mode
     */
    public function testGetConfigStatusWithInvalidMode()
    {
        Config::shouldReceive('config')
              ->with('products-package.mode', 'wl')
              ->andReturn('invalid');

        $status = ProductFetcherFactory::getConfigStatus();

        $this->assertIsArray($status);
        $this->assertEquals('invalid', $status['current_mode']);
        $this->assertFalse($status['is_valid']);
        $this->assertArrayHasKey('available_modes', $status);
    }

    /**
     * Test getConfigStatus with WTM mode but no request (should fail)
     */
    public function testGetConfigStatusWithWtmModeButNoRequest()
    {
        Config::shouldReceive('config')
              ->with('products-package.mode', 'wl')
              ->andReturn('wtm');

        $status = ProductFetcherFactory::getConfigStatus();

        $this->assertIsArray($status);
        $this->assertEquals('wtm', $status['current_mode']);
        $this->assertTrue($status['is_valid']);
        $this->assertFalse($status['fetcher_created']);
        $this->assertArrayHasKey('error', $status);
        $this->assertEquals("Request object is required when using 'wtm' mode.", $status['error']);
    }

    /**
     * Test PHP 7.2 compatibility - no return type hints used
     */
    public function testPhp72Compatibility()
    {
        // Test that methods work without type hints
        $reflection = new \ReflectionClass(ProductFetcherFactory::class);

        $makeMethod = $reflection->getMethod('make');
        $this->assertNull($makeMethod->getReturnType()); // No return type hint for PHP 7.2 compatibility

        $getAvailableModesMethod = $reflection->getMethod('getAvailableModes');
        // In PHP 7.2+ array return type hints are allowed, so this is OK
    }
}