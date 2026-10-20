<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideAdminUi\Model;

use Hryvinskyi\DataLayerAdminUi\Api\ServerDeliveryStateInterface;
use Hryvinskyi\DataLayerServerSide\Api\ConfigInterface;

/**
 * Tells the event settings whether this module delivers server events for a website, and where that is switched on
 */
class ServerDeliveryState implements ServerDeliveryStateInterface
{
    /**
     * The group of the data layer's settings that holds server-side delivery, as `section/group`
     */
    public const SETTINGS_GROUP = 'hryvinskyi_datalayer/server';

    /**
     * @param ConfigInterface $config
     */
    public function __construct(private readonly ConfigInterface $config)
    {
    }

    /**
     * @inheritDoc
     */
    public function isEnabled(?int $websiteId): bool
    {
        return $this->config->isEnabled($websiteId ?? 0);
    }

    /**
     * @inheritDoc
     */
    public function getSettingsGroupPath(): ?string
    {
        return self::SETTINGS_GROUP;
    }
}
