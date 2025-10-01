<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use TheDiamondBox\ShopSync\Models\Client;
use TheDiamondBox\ShopSync\Models\WtmModel;
use RuntimeException;

/**
 * Test suite for WtmModel functionality
 */
class WtmModelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Mock the config helper
        if (!function_exists('config')) {
            function config($key, $default = null) {
                // Default to 'wl' mode for testing exception behavior
                return 'wl';
            }
        }
    }

    /**
     * Test that WTM models throw exception in WL mode
     */
    public function testWtmModelThrowsExceptionInWlMode()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Model 'TheDiamondBox\ShopSync\Models\Client' can only be used in WTM");

        // This should throw an exception since we're in WL mode
        $client = new Client();
    }

    /**
     * Test static query method throws exception in WL mode
     */
    public function testQueryMethodThrowsExceptionInWlMode()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Cannot query 'TheDiamondBox\ShopSync\Models\Client' in 'wl' mode");

        // This should throw an exception since we're in WL mode
        Client::query();
    }

    /**
     * Test canUse method returns false in WL mode
     */
    public function testCanUseReturnsFalseInWlMode()
    {
        $this->assertFalse(Client::canUse());
    }

    /**
     * Test getCurrentMode returns the correct mode
     */
    public function testGetCurrentModeReturnsCorrectMode()
    {
        $this->assertEquals('wl', Client::getCurrentMode());
    }

    /**
     * Test isWlMode returns true when in WL mode
     */
    public function testIsWlModeReturnsTrueInWlMode()
    {
        $this->assertTrue(Client::isWlMode());
    }

    /**
     * Test isWtmMode returns false when in WL mode
     */
    public function testIsWtmModeReturnsFalseInWlMode()
    {
        $this->assertFalse(Client::isWtmMode());
    }
}