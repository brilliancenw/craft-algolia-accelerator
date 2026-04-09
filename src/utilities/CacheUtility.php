<?php

namespace brilliance\algoliaaccelerator\utilities;

use Craft;
use craft\base\Utility;
use brilliance\algoliaaccelerator\AlgoliaAccelerator;

/**
 * Cache Utility
 *
 * Provides a Control Panel utility page for managing the Algolia cache.
 */
class CacheUtility extends Utility
{
    public static function displayName(): string
    {
        return Craft::t('algolia-accelerator', 'Algolia Accelerator');
    }

    public static function id(): string
    {
        return 'algolia-accelerator';
    }

    public static function icon(): ?string
    {
        return 'magnifying-glass';
    }

    public static function contentHtml(): string
    {
        $plugin = AlgoliaAccelerator::getInstance();
        $settings = $plugin->getSettings();
        $cachePurgeService = $plugin->cachePurge;

        // Get worker status
        $workerStatus = $cachePurgeService->getWorkerStatus();

        // Get configured sections
        $triggerSections = $settings->triggerSections;
        $sectionNames = [];
        if (!empty($triggerSections)) {
            foreach ($triggerSections as $handle) {
                $section = Craft::$app->getEntries()->getSectionByHandle($handle);
                if ($section) {
                    $sectionNames[] = $section->name;
                }
            }
        }

        return Craft::$app->getView()->renderTemplate('algolia-accelerator/utilities/cache', [
            'settings' => $settings,
            'workerStatus' => $workerStatus,
            'isConfigured' => $cachePurgeService->isConfigured(),
            'lastPurgeTime' => $cachePurgeService->getLastPurgeTime(),
            'triggerSectionNames' => $sectionNames,
        ]);
    }
}
