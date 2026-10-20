<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideAdminUi\Model\Setup;

use Hryvinskyi\DataLayerAdminUi\Api\Setup\SetupContributionInterface;
use Hryvinskyi\DataLayerServerSideAdminUi\Api\Setup\ServerStepDataInterface;

/**
 * Adds the server-side step to the setup wizard: its wording, its browser plugin and the module that plugin is
 * imported from, and the data only that plugin reads
 *
 * The wording comes from `di.xml`, kept as written there and translated each time it is read, into the locale of the
 * request reading it: the object manager hands `di.xml` text over untranslated, and this object is shared, so a text
 * translated once would keep the first request's language. The step's own phrase keys start with `server.`, so they
 * cannot collide with the wizard's; a setting's validation messages use the wizard's `error.<setting key>.<code>`
 * keys.
 */
class ServerSideContribution implements SetupContributionInterface
{
    /**
     * The id of the browser plugin, which also keys its data
     */
    private const PLUGIN_ID = 'server';

    /**
     * The name the plugin's module is imported by
     */
    private const MODULE = '@hryvinskyi/datalayer-setup-server';

    /**
     * The plugin's built module
     */
    private const MODULE_FILE = 'Hryvinskyi_DataLayerServerSideAdminUi::js/setup/server-step.js';

    /**
     * @var array<string, string>
     */
    private readonly array $phrases;

    /**
     * @param ServerStepDataInterface $stepData
     * @param array<mixed> $phrases Text in the source language, keyed by phrase key
     * @throws \InvalidArgumentException When a phrase is not text keyed by text
     */
    public function __construct(
        private readonly ServerStepDataInterface $stepData,
        array $phrases = []
    ) {
        $texts = [];
        foreach ($phrases as $key => $text) {
            if (!is_string($key) || !is_string($text)) {
                throw new \InvalidArgumentException(
                    sprintf('The setup phrase "%s" must be text keyed by text.', (string) $key)
                );
            }
            $texts[$key] = $text;
        }
        $this->phrases = $texts;
    }

    /**
     * @inheritDoc
     *
     * @phpstan-impure The answer follows the locale active when it is read
     */
    public function getPhrases(): array
    {
        return array_map(static fn (string $text): string => (string) __($text), $this->phrases);
    }

    /**
     * @inheritDoc
     */
    public function getPlugins(): array
    {
        return [self::PLUGIN_ID => self::MODULE];
    }

    /**
     * @inheritDoc
     */
    public function getImports(): array
    {
        return [self::MODULE => self::MODULE_FILE];
    }

    /**
     * @inheritDoc
     */
    public function getExtensionData(): array
    {
        return [self::PLUGIN_ID => $this->stepData->get()];
    }
}
