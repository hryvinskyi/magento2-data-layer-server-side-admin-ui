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
 * Turns a delivery report into what a delivery status shows: three counts and two lines of translated text
 *
 * The settings page and the setup wizard both show it, so both say the same thing in the same words.
 *
 * @phpstan-type StatusView array{pending: int, sent: int, failed: int, progress: string, failedLine: string}
 * @api
 */
interface DeliveryStatusViewInterface
{
    /**
     * Describe a report
     *
     * Pending counts the events waiting and the events a run is sending now; the progress line names the age of the
     * oldest waiting event and the last run; the failed line is empty while nothing failed.
     *
     * @param DeliveryReportInterface $report
     * @return StatusView
     */
    public function describe(DeliveryReportInterface $report): array;
}
