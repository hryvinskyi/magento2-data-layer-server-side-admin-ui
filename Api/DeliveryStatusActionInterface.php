<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideAdminUi\Api;

use Hryvinskyi\DataLayerServerSide\Api\Data\DeliveryReportInterface;

/**
 * An action offered next to the failed events in the delivery status, such as sending them again
 *
 * Actions are registered in the `failedActions` argument of the delivery status renderer and shown, in that order,
 * beside the failed-events line while the scope has failed events. Each decides for itself whether the admin may use
 * it and returns no markup when not.
 *
 * @api
 */
interface DeliveryStatusActionInterface
{
    /**
     * Render the action for the scope's failed events
     *
     * @param DeliveryReportInterface $report Where the scope's delivery stands
     * @param int|null $websiteId The website the settings page edits; null at the default scope
     * @return string Markup, escaped by the action; empty when the action does not apply
     */
    public function render(DeliveryReportInterface $report, ?int $websiteId): string;
}
