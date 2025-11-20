<?php

namespace TheDiamondBox\ShopSync\Tests\Unit;

use TheDiamondBox\ShopSync\Exceptions\ClientNotFoundException;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;

class ProductFetcherFactorySimpleTest extends TestCase
{
    /**
     * Test ClientNotFoundException creation and properties
     */
    public function testClientNotFoundExceptionCreation()
    {
        $clientId = 'test-client-123';
        $exception = ClientNotFoundException::forClientId($clientId);

        $this->assertInstanceOf(ClientNotFoundException::class, $exception);
        $this->assertEquals("Client not found: {$clientId}.", $exception->getMessage());
        $this->assertEquals($clientId, $exception->getClientId());
    }

    /**
     * Test ClientNotFoundException context
     */
    public function testClientNotFoundExceptionContext()
    {
        $clientId = 'context-test-client';
        $exception = ClientNotFoundException::forClientId($clientId);
        $context = $exception->getContext();

        $this->assertIsArray($context);
        $this->assertEquals(ClientNotFoundException::class, $context['exception']);
        $this->assertEquals($clientId, $context['client_id']);
        $this->assertEquals("Client not found: {$clientId}.", $context['message']);
        $this->assertArrayHasKey('file', $context);
        $this->assertArrayHasKey('line', $context);
    }

    /**
     * Test ClientNotFoundException inheritance
     */
    public function testClientNotFoundExceptionInheritance()
    {
        $exception = new ClientNotFoundException();

        $this->assertInstanceOf(\Exception::class, $exception);
        $this->assertInstanceOf(\Throwable::class, $exception);
    }

    /**
     * Test ProductFetcherFactory getAvailableModes structure
     */
    public function testGetAvailableModes()
    {
        $reflection = new \ReflectionClass(\TheDiamondBox\ShopSync\Services\Fetchers\Product\ProductFetcherFactory::class);

        // Verify the class exists and has the getAvailableModes method
        $this->assertTrue($reflection->hasMethod('getAvailableModes'));

        $method = $reflection->getMethod('getAvailableModes');
        $this->assertTrue($method->isStatic());
        $this->assertTrue($method->isPublic());
    }

    /**
     * Test ProductFetcherFactory isValidMode method exists and is structured correctly
     */
    public function testIsValidModeMethodExists()
    {
        $reflection = new \ReflectionClass(\TheDiamondBox\ShopSync\Services\Fetchers\Product\ProductFetcherFactory::class);

        // Verify the isValidMode method exists
        $this->assertTrue($reflection->hasMethod('isValidMode'));

        $method = $reflection->getMethod('isValidMode');
        $this->assertTrue($method->isStatic());
        $this->assertTrue($method->isPublic());

        // Check parameter count
        $this->assertEquals(1, $method->getNumberOfParameters());

        // Check parameter name
        $parameters = $method->getParameters();
        $this->assertEquals('mode', $parameters[0]->getName());
    }

    /**
     * Test that the make method has proper exception documentation
     */
    public function testMakeMethodDocumentation()
    {
        $reflection = new \ReflectionClass(\TheDiamondBox\ShopSync\Services\Fetchers\Product\ProductFetcherFactory::class);

        $this->assertTrue($reflection->hasMethod('make'));

        $method = $reflection->getMethod('make');
        $docComment = $method->getDocComment();

        $this->assertStringContainsString('@throws InvalidArgumentException', $docComment);
        $this->assertStringContainsString('@throws ClientNotFoundException', $docComment);
    }

    /**
     * Test PHP 7.2+ compatibility features
     */
    public function testPhp72Compatibility()
    {
        // Test that our ClientNotFoundException works with basic PHP features
        $exception = new ClientNotFoundException('Test message', 'test-client');

        // String interpolation (PHP 7.0+)
        $message = "Error: {$exception->getMessage()}";
        $this->assertEquals('Error: Test message', $message);

        // Null coalescing operator (PHP 7.0+)
        $clientId = $exception->getClientId() ?? 'default-client';
        $this->assertEquals('test-client', $clientId);

        // Array spread operator works with our context method (PHP 7.4+, but array_merge works in 7.2+)
        $context = $exception->getContext();
        $mergedContext = array_merge($context, ['additional' => 'data']);
        $this->assertArrayHasKey('additional', $mergedContext);
        $this->assertEquals('data', $mergedContext['additional']);
    }

    /**
     * Test exception chaining (PHP 7.0+ feature)
     */
    public function testExceptionChaining()
    {
        $previousException = new \Exception('Previous error');
        $clientException = new ClientNotFoundException('Client error', 'client-123', 0, $previousException);

        $this->assertSame($previousException, $clientException->getPrevious());
    }

    /**
     * Test setClientId method for fluent interface
     */
    public function testFluentInterface()
    {
        $exception = new ClientNotFoundException();
        $result = $exception->setClientId('fluent-test');

        $this->assertSame($exception, $result); // Should return self for method chaining
        $this->assertEquals('fluent-test', $exception->getClientId());
    }
}