<?php

namespace TheDiamondBox\ShopSync\Tests\Unit;

use TheDiamondBox\ShopSync\Exceptions\ClientNotFoundException;
use PHPUnit\Framework\TestCase;
use Exception;

class ClientNotFoundExceptionTest extends TestCase
{
    /**
     * Test basic exception creation with default message
     */
    public function testCanCreateExceptionWithDefaultMessage()
    {
        $exception = new ClientNotFoundException();

        $this->assertInstanceOf(Exception::class, $exception);
        $this->assertEquals('', $exception->getMessage());
        $this->assertNull($exception->getClientId());
    }

    /**
     * Test exception creation with custom message
     */
    public function testCanCreateExceptionWithCustomMessage()
    {
        $message = 'Custom error message';
        $exception = new ClientNotFoundException($message);

        $this->assertEquals($message, $exception->getMessage());
        $this->assertNull($exception->getClientId());
    }

    /**
     * Test exception creation with client ID
     */
    public function testCanCreateExceptionWithClientId()
    {
        $clientId = 'client-123';
        $exception = new ClientNotFoundException('', $clientId);

        $this->assertEquals($clientId, $exception->getClientId());
    }

    /**
     * Test exception creation with message and client ID
     */
    public function testCanCreateExceptionWithMessageAndClientId()
    {
        $message = 'Client not found';
        $clientId = 'client-456';
        $exception = new ClientNotFoundException($message, $clientId);

        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals($clientId, $exception->getClientId());
    }

    /**
     * Test exception creation with all parameters
     */
    public function testCanCreateExceptionWithAllParameters()
    {
        $message = 'Client not found';
        $clientId = 'client-789';
        $code = 404;
        $previous = new Exception('Previous exception');

        $exception = new ClientNotFoundException($message, $clientId, $code, $previous);

        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals($clientId, $exception->getClientId());
        $this->assertEquals($code, $exception->getCode());
        $this->assertEquals($previous, $exception->getPrevious());
    }

    /**
     * Test auto-generated message when client ID provided but no message
     */
    public function testAutoGeneratesMessageWhenClientIdProvidedButNoMessage()
    {
        $clientId = 'client-auto';
        $exception = new ClientNotFoundException('', $clientId);

        $this->assertEquals("Client not found: {$clientId}.", $exception->getMessage());
    }

    /**
     * Test static factory method forClientId
     */
    public function testStaticFactoryMethodForClientId()
    {
        $clientId = 'client-static';
        $exception = ClientNotFoundException::forClientId($clientId);

        $this->assertEquals("Client not found: {$clientId}.", $exception->getMessage());
        $this->assertEquals($clientId, $exception->getClientId());
    }

    /**
     * Test static factory method with custom message
     */
    public function testStaticFactoryMethodWithCustomMessage()
    {
        $clientId = 'client-custom';
        $customMessage = 'Custom static message';
        $exception = ClientNotFoundException::forClientId($clientId, $customMessage);

        $this->assertEquals($customMessage, $exception->getMessage());
        $this->assertEquals($clientId, $exception->getClientId());
    }

    /**
     * Test setClientId method
     */
    public function testCanSetClientId()
    {
        $exception = new ClientNotFoundException();
        $clientId = 'client-set';

        $result = $exception->setClientId($clientId);

        $this->assertSame($exception, $result); // Method should return self for chaining
        $this->assertEquals($clientId, $exception->getClientId());
    }

    /**
     * Test __toString method without client ID
     */
    public function testToStringWithoutClientId()
    {
        $exception = new ClientNotFoundException('Test message');
        $toString = $exception->__toString();

        $this->assertStringContainsString('Test message', $toString);
        $this->assertStringNotContainsString('[Client ID:', $toString);
    }

    /**
     * Test __toString method with client ID
     */
    public function testToStringWithClientId()
    {
        $clientId = 'client-tostring';
        $exception = new ClientNotFoundException('Test message', $clientId);
        $toString = $exception->__toString();

        $this->assertStringContainsString('Test message', $toString);
        $this->assertStringContainsString("[Client ID: {$clientId}]", $toString);
    }

    /**
     * Test getContext method
     */
    public function testGetContext()
    {
        $message = 'Test context message';
        $clientId = 'client-context';
        $code = 500;
        $exception = new ClientNotFoundException($message, $clientId, $code);

        $context = $exception->getContext();

        $this->assertIsArray($context);
        $this->assertEquals(ClientNotFoundException::class, $context['exception']);
        $this->assertEquals($message, $context['message']);
        $this->assertEquals($clientId, $context['client_id']);
        $this->assertEquals($code, $context['code']);
        $this->assertArrayHasKey('file', $context);
        $this->assertArrayHasKey('line', $context);
    }

    /**
     * Test getContext method with null client ID
     */
    public function testGetContextWithNullClientId()
    {
        $exception = new ClientNotFoundException('Test message');
        $context = $exception->getContext();

        $this->assertIsArray($context);
        $this->assertNull($context['client_id']);
    }

    /**
     * Test exception inheritance
     */
    public function testExceptionInheritance()
    {
        $exception = new ClientNotFoundException();

        $this->assertInstanceOf(\Exception::class, $exception);
        $this->assertInstanceOf(\Throwable::class, $exception);
    }

    /**
     * Test that exception can be thrown and caught
     */
    public function testCanBeThrownAndCaught()
    {
        $clientId = 'client-throw';
        $caught = false;
        $caughtException = null;

        try {
            throw ClientNotFoundException::forClientId($clientId);
        } catch (ClientNotFoundException $e) {
            $caught = true;
            $caughtException = $e;
        }

        $this->assertTrue($caught);
        $this->assertInstanceOf(ClientNotFoundException::class, $caughtException);
        $this->assertEquals($clientId, $caughtException->getClientId());
    }

    /**
     * Test exception is caught as generic Exception
     */
    public function testCanBeCaughtAsGenericException()
    {
        $clientId = 'client-generic';
        $caught = false;
        $caughtException = null;

        try {
            throw ClientNotFoundException::forClientId($clientId);
        } catch (Exception $e) {
            $caught = true;
            $caughtException = $e;
        }

        $this->assertTrue($caught);
        $this->assertInstanceOf(ClientNotFoundException::class, $caughtException);
    }
}