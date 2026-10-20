<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideAdminUi\Api;

/**
 * Who may act on server-side delivery: queue failed events again and send a test event
 *
 * The endpoints of those actions are guarded by the same access rule, so an action is offered only to an
 * administrator its endpoint lets in. Seeing the delivery status needs only the settings' own rule.
 *
 * @api
 */
interface DeliveryAccessInterface
{
    /**
     * The access rule of the delivery actions
     */
    public const RESOURCE = 'Hryvinskyi_DataLayerServerSideAdminUi::delivery';

    /**
     * Whether the signed-in administrator may use the delivery actions
     *
     * @return bool
     */
    public function isAllowed(): bool;
}
