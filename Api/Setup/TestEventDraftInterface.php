<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideAdminUi\Api\Setup;

use Hryvinskyi\DataLayerAdminUi\Api\Setup\InvalidSetupRequestException;

/**
 * Turns the unsaved server-side settings a test event is asked for into the drafted config values it runs against
 *
 * Each setting becomes its config path with the value its kind would store, so a drafted value reads exactly as the
 * same value would once saved (a measurement ID in capitals). A secret is drafted only when the request carries one:
 * the browser sends a secret only when the person typed a new one, and a secret left out keeps the stored one.
 *
 * @api
 */
interface TestEventDraftInterface
{
    /**
     * Turn wizard values into drafted config values
     *
     * @param array<mixed> $settings Wire values keyed by wizard setting key
     * @return array<string, string> Raw values in their stored form, keyed by config path
     * @throws InvalidSetupRequestException When a key names no setting of the server-side step, or a value is not one
     *     its setting takes; the reason says which
     */
    public function toDraft(array $settings): array;
}
