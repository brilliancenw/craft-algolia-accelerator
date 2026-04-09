<?php

namespace brilliance\algoliaaccelerator\services;

use Craft;
use craft\base\Component;
use brilliance\algoliaaccelerator\AlgoliaAccelerator;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Cache Purge Service
 *
 * Handles communication with the Cloudflare Worker to purge cached search results.
 */
class CachePurgeService extends Component
{
    /**
     * Track if purge has already been requested this request
     */
    private bool $purgeRequested = false;

    /**
     * Timestamp of the last successful purge
     */
    private static ?string $lastPurgeTime = null;

    /**
     * Purge the Algolia search cache
     *
     * @param string $triggeredBy Identifier for what triggered the purge (for logging)
     * @return bool Whether the purge was successful
     */
    public function purgeCache(string $triggeredBy = 'manual'): bool
    {
        // Only purge once per request
        if ($this->purgeRequested) {
            Craft::info("Algolia cache purge SKIPPED - already purged this request (triggered by: {$triggeredBy})", __METHOD__);
            return true;
        }

        $settings = AlgoliaAccelerator::getInstance()->getSettings();
        $workerHost = $settings->getWorkerHost();
        $purgeToken = $settings->getPurgeToken();

        // Skip if not configured
        if (empty($workerHost) || empty($purgeToken)) {
            Craft::warning('Algolia cache purge skipped - worker not configured', __METHOD__);
            return false;
        }

        $this->purgeRequested = true;

        try {
            $client = Craft::createGuzzleClient();
            $response = $client->request('POST', "https://{$workerHost}/purge", [
                'headers' => [
                    'Authorization' => "Bearer {$purgeToken}",
                ],
                'timeout' => 5, // Don't block CMS if worker is slow
            ]);

            $body = $response->getBody()->getContents();
            $data = json_decode($body, true);

            self::$lastPurgeTime = date('Y-m-d H:i:s');

            Craft::info("Algolia cache purged (triggered by: {$triggeredBy}). Worker response: {$body}", __METHOD__);

            return $data['success'] ?? false;
        } catch (GuzzleException $e) {
            // Log but don't fail - cache will expire naturally
            Craft::warning('Failed to purge Algolia cache: ' . $e->getMessage(), __METHOD__);
            return false;
        }
    }

    /**
     * Get the worker health status
     *
     * @return array{online: bool, message: string, data?: array}
     */
    public function getWorkerStatus(): array
    {
        $settings = AlgoliaAccelerator::getInstance()->getSettings();
        $workerHost = $settings->getWorkerHost();

        if (empty($workerHost)) {
            return [
                'online' => false,
                'message' => 'Worker host not configured',
            ];
        }

        try {
            $client = Craft::createGuzzleClient();
            $response = $client->request('GET', "https://{$workerHost}/health", [
                'timeout' => 5,
            ]);

            $body = $response->getBody()->getContents();
            $data = json_decode($body, true);

            return [
                'online' => true,
                'message' => 'Worker is online',
                'data' => $data,
            ];
        } catch (GuzzleException $e) {
            return [
                'online' => false,
                'message' => 'Worker is offline or unreachable: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get the last purge timestamp
     */
    public function getLastPurgeTime(): ?string
    {
        return self::$lastPurgeTime;
    }

    /**
     * Check if the plugin is properly configured
     */
    public function isConfigured(): bool
    {
        $settings = AlgoliaAccelerator::getInstance()->getSettings();
        return !empty($settings->getWorkerHost()) && !empty($settings->getPurgeToken());
    }
}
