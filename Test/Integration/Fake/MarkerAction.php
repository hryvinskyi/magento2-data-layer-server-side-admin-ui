<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideAdminUi\Test\Integration\Fake;

use Hryvinskyi\DataLayerServerSide\Api\Data\DeliveryReportInterface;
use Hryvinskyi\DataLayerServerSideAdminUi\Api\DeliveryStatusActionInterface;

/**
 * An action that shows a marker naming the failed count and the website it was offered for
 */
class MarkerAction implements DeliveryStatusActionInterface
{
    /**
     * @inheritDoc
     */
    public function render(DeliveryReportInterface $report, ?int $websiteId): string
    {
        return sprintf(
            '<span data-role="marker-action">%d failed at website %s</span>',
            $report->getFailedCount(),
            $websiteId === null ? 'all' : (string) $websiteId
        );
    }
}
