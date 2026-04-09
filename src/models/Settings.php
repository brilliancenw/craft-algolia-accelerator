<?php

namespace brilliance\algoliaaccelerator\models;

use Craft;
use craft\base\Model;
use craft\helpers\App;

/**
 * Algolia Accelerator Settings
 */
class Settings extends Model
{
    /**
     * Whether cache purging is enabled
     */
    public bool $enabled = true;

    /**
     * Cloudflare Worker hostname (e.g., search-api.example.com)
     */
    public string $workerHost = '';

    /**
     * Authentication token for cache purge requests
     */
    public string $purgeToken = '';

    /**
     * Section handles that should trigger cache purge on save
     */
    public array $triggerSections = [];

    /**
     * Cache TTL in seconds (informational, actual TTL is set in worker)
     */
    public int $cacheTtl = 86400;

    public function init(): void
    {
        parent::init();

        // Set defaults from environment variables if not already set
        if (empty($this->workerHost)) {
            $this->workerHost = App::env('ALGOLIA_CACHE_WORKER_HOST') ?? '';
        }

        if (empty($this->purgeToken)) {
            $this->purgeToken = App::env('ALGOLIA_CACHE_PURGE_TOKEN') ?? '';
        }
    }

    /**
     * Get the worker host, parsing environment variables
     */
    public function getWorkerHost(): string
    {
        return App::parseEnv($this->workerHost);
    }

    /**
     * Get the purge token, parsing environment variables
     */
    public function getPurgeToken(): string
    {
        return App::parseEnv($this->purgeToken);
    }

    public function defineRules(): array
    {
        return [
            [['enabled'], 'boolean'],
            [['workerHost', 'purgeToken'], 'string'],
            [['triggerSections'], 'each', 'rule' => ['string']],
            [['cacheTtl'], 'integer', 'min' => 60],
        ];
    }
}
