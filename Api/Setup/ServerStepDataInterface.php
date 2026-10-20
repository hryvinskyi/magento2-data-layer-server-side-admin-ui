<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideAdminUi\Api\Setup;

use Hryvinskyi\DataLayerServerSideAdminUi\Api\DeliveryStatusViewInterface;

/**
 * What the setup wizard's server-side step reads besides its settings: where its actions post, whether the
 * administrator may use them, which transports can be tested and which settings each transport shows, and where
 * delivery stands
 *
 * Delivery status is given for the default scope (every website together) and for each website, keyed by scope id,
 * since the person switches scope without reloading the page; a store view shows its website's.
 *
 * @phpstan-import-type StatusView from DeliveryStatusViewInterface
 * @phpstan-type StepData array{
 *     testUrl: string,
 *     retryUrl: string,
 *     canAct: bool,
 *     testableTransports: list<string>,
 *     transportFields: array<string, list<string>>|object,
 *     transportIcons: array<string, string>|object,
 *     status: array<string, StatusView>
 * }
 * @api
 */
interface ServerStepDataInterface
{
    /**
     * The id of the wizard step that shows the server-side settings
     */
    public const STEP = 'server';

    /**
     * Get the step's data
     *
     * @return StepData Maps that may be empty (`transportFields`, `transportIcons`) are an empty object when empty,
     *     so they encode to JSON objects
     */
    public function get(): array;
}
