<?php
namespace Hyperwallet\Util;

/**
 * The throttling service for Hyperwallet client's API request limits
 *
 * @package Hyperwallet\Util
 */
class HyperwalletThrottling
{
    /**
     * Rate limit
     *
     * @var int
     */
    private $rateLimit = 0;

    /**
     * Requests remaining before rate limit hit
     *
     * @var int
     */
    private $rateLimitRemaining = 0;

    /**
     * Unix timestamp corresponding to next rate limit reset
     *
     * @var int
     */
    private $rateLimitReset = 0;

    public function __construct(array $responseHeaders = [])
    {
        $this->parseResponseHeaders($responseHeaders);
    }

    private function parseResponseHeaders(array $responseHeaders = [])
    {
        $this->rateLimit = $responseHeaders['X-Rate-Limit'][0] ?? 0;
        $this->rateLimitRemaining = $responseHeaders['X-Rate-Limit-Remaining'][0] ?? 0;
        $this->rateLimitReset = $responseHeaders['X-Rate-Limit-Reset'][0] ?? 0;
    }

    /**
     * @return int
     */
    public function getRateLimit()
    {
        return $this->rateLimit;
    }

    /**
     * Determine if the current connection is rate limited
     *
     * @return bool
     */
    public function isRateLimited()
    {
        return $this->rateLimitReset > 0 && $this->rateLimitRemaining == 0;
    }

    /**
     * Get the unix timestamp representing when the rate limit window will reset
     *
     * @return int
     */
    public function getRateLimitReset()
    {
        return $this->rateLimitReset;
    }

    /**
     * Determine the amount of time remaining before next rate limit window reset
     *
     * @return int
     */
    public function getSecondsToNextRateLimitReset()
    {
        return $this->rateLimitReset > 0 ? max(time() - $this->rateLimitReset, 0) : 0;
    }

}
