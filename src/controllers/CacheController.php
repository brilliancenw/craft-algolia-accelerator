<?php

namespace brilliance\algoliaaccelerator\controllers;

use Craft;
use craft\web\Controller;
use brilliance\algoliaaccelerator\AlgoliaAccelerator;
use yii\web\Response;

/**
 * Cache Controller
 *
 * Handles AJAX requests for cache management from the Control Panel.
 */
class CacheController extends Controller
{
    /**
     * Toggle the plugin enabled state
     */
    public function actionToggle(): Response
    {
        $this->requirePostRequest();
        $this->requireCpRequest();
        $this->requirePermission('utility:craft-algolia-accelerator');

        $enabled = $this->request->getBodyParam('enabled');

        $plugin = AlgoliaAccelerator::getInstance();
        $settings = $plugin->getSettings();
        $settings->enabled = (bool)$enabled;

        if (Craft::$app->getPlugins()->savePluginSettings($plugin, $settings->toArray())) {
            return $this->asJson([
                'success' => true,
                'enabled' => $settings->enabled,
            ]);
        }

        return $this->asJson([
            'success' => false,
            'message' => Craft::t('craft-algolia-accelerator', 'Failed to save settings'),
        ]);
    }

    /**
     * Purge the Algolia cache
     */
    public function actionPurge(): Response
    {
        $this->requirePostRequest();
        $this->requireCpRequest();
        $this->requirePermission('utility:craft-algolia-accelerator');

        $plugin = AlgoliaAccelerator::getInstance();
        $result = $plugin->cachePurge->purgeCache('manual (CP utility)');

        if ($result) {
            return $this->asJson([
                'success' => true,
                'message' => Craft::t('craft-algolia-accelerator', 'Cache purged successfully'),
            ]);
        }

        return $this->asJson([
            'success' => false,
            'message' => Craft::t('craft-algolia-accelerator', 'Failed to purge cache. Check the logs for details.'),
        ]);
    }

    /**
     * Get the current worker status
     */
    public function actionStatus(): Response
    {
        $this->requireCpRequest();
        $this->requirePermission('utility:craft-algolia-accelerator');

        $plugin = AlgoliaAccelerator::getInstance();
        $status = $plugin->cachePurge->getWorkerStatus();

        return $this->asJson($status);
    }
}
