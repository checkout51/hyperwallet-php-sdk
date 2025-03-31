<?php

namespace Hyperwallet\Tests\Util;

use Hyperwallet\Util\HyperwalletThrottling;

class HyperwalletThrottlingTest extends \PHPUnit\Framework\TestCase {

    public function testShouldReturnRateLimitValues() {

        $reset = time() + 250;

        $responseHeaders = array(
            'X-Rate-Limit' => [125],
            'X-Rate-Limit-Remaining' => [250],
            'X-Rate-Limit-Reset' => [$reset]
        );
        $throttling = new HyperwalletThrottling($responseHeaders);
        $this->assertEquals(125, $throttling->getRateLimit());
        $this->assertFalse($throttling->isRateLimited());
        $this->assertEquals($reset, $throttling->getRateLimitReset());
        $this->assertEqualsWithDelta(250, $throttling->getSecondsToNextRateLimitReset(), 5);
    }

    public function testShouldReturnRateLimitValueDefaultsIfNotSet() {
        $responseHeaders = array();
        $throttling = new HyperwalletThrottling($responseHeaders);
        $this->assertEquals(0, $throttling->getRateLimit());
        $this->assertFalse($throttling->isRateLimited());
        $this->assertEquals(0, $throttling->getSecondsToNextRateLimitReset());
    }

    public function testShouldReturnRateLimitValueWhenRateLimited() {

        $reset = time() + 1500;

        $responseHeaders = array(
            'X-Rate-Limit' => [50],
            'X-Rate-Limit-Remaining' => [0],
            'X-Rate-Limit-Reset' => [$reset]
        );
        $throttling = new HyperwalletThrottling($responseHeaders);
        $this->assertEquals(50, $throttling->getRateLimit());
        $this->assertTrue($throttling->isRateLimited());
        $this->assertEquals($reset, $throttling->getRateLimitReset());
        $this->assertEqualsWithDelta(1500, $throttling->getSecondsToNextRateLimitReset(), 5);
    }
}