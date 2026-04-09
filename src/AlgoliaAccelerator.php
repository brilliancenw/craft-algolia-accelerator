<?php

namespace brilliance\algoliaaccelerator;

use Craft;
use brilliance\algoliaaccelerator\models\Settings;
use brilliance\algoliaaccelerator\services\CachePurgeService;
use brilliance\algoliaaccelerator\utilities\CacheUtility;
use craft\base\Event;
use craft\base\Model;
use craft\base\Plugin;
use craft\elements\Entry;
use craft\events\ModelEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\services\Utilities;

/**
 * Craft Algolia Accelerator Plugin
 *
 * Provides automatic cache purging for a Cloudflare-based Algolia caching layer.
 *
 * @method static AlgoliaAccelerator getInstance()
 * @method Settings getSettings()
 * @property-read CachePurgeService $cachePurge
 */
class AlgoliaAccelerator extends Plugin
{
    public string $schemaVersion = '1.0.0';
    public bool $hasCpSettings = true;

    public static function config(): array
    {
        return [
            'components' => [
                'cachePurge' => CachePurgeService::class,
            ],
        ];
    }

    public function init(): void
    {
        parent::init();

        // Defer setup until Craft is fully initialized
        Craft::$app->onInit(function() {
            $this->attachEventHandlers();
        });

        Craft::info('Craft Algolia Accelerator plugin loaded', __METHOD__);
    }

    protected function createSettingsModel(): ?Model
    {
        return Craft::createObject(Settings::class);
    }

    protected function settingsHtml(): ?string
    {
        // Get all sections for the multi-select (Craft 5 uses getEntries())
        $sections = Craft::$app->getEntries()->getAllSections();
        $sectionOptions = [];
        foreach ($sections as $section) {
            $sectionOptions[] = [
                'label' => $section->name,
                'value' => $section->handle,
            ];
        }

        return Craft::$app->view->renderTemplate('craft-algolia-accelerator/settings', [
            'plugin' => $this,
            'settings' => $this->getSettings(),
            'sectionOptions' => $sectionOptions,
        ]);
    }

    private function attachEventHandlers(): void
    {
        // Register utility
        Event::on(
            Utilities::class,
            Utilities::EVENT_REGISTER_UTILITIES,
            function(RegisterComponentTypesEvent $event) {
                $event->types[] = CacheUtility::class;
            }
        );

        // Only attach entry save handler if plugin is enabled
        if (!$this->getSettings()->enabled) {
            return;
        }

        // Cache purge on entry save
        Event::on(
            Entry::class,
            Entry::EVENT_AFTER_SAVE,
            function(ModelEvent $event) {
                /** @var Entry $entry */
                $entry = $event->sender;
                $settings = $this->getSettings();

                $sectionHandle = $entry->section->handle ?? null;

                if (!$sectionHandle) {
                    return;
                }

                // Check if this section should trigger a purge
                if (!empty($settings->triggerSections) && in_array($sectionHandle, $settings->triggerSections, true)) {
                    Craft::info("Craft Algolia Accelerator: Entry saved in section '{$sectionHandle}', triggering cache purge", __METHOD__);
                    $this->cachePurge->purgeCache($sectionHandle);
                }
            }
        );
    }
}
