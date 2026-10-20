<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideAdminUi\Model\DeliveryStatus;

use Hryvinskyi\DataLayerServerSide\Api\Data\DeliveryReportInterface;
use Hryvinskyi\DataLayerServerSideAdminUi\Api\DeliveryStatusViewInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;

/**
 * Turns a delivery report into what the delivery status shows: three counts and two lines of text
 *
 * Pending counts the events waiting and the events a run is sending now. The age of the oldest waiting event is
 * rounded down to whole seconds, minutes, hours or days; the last run is shown in the admin's time zone.
 */
class DeliveryStatusView implements DeliveryStatusViewInterface
{
    private const MINUTE = 60;
    private const HOUR = 3600;
    private const DAY = 86400;

    /**
     * @param TimezoneInterface $timezone
     */
    public function __construct(private readonly TimezoneInterface $timezone)
    {
    }

    /**
     * @inheritDoc
     */
    public function describe(DeliveryReportInterface $report): array
    {
        $age = $report->getOldestPendingAgeSeconds();
        $lastRun = $report->getLastRunAt();
        $progress = [
            $age === null
                ? (string) __('Nothing is waiting to be sent')
                : (string) __('Oldest pending event: %1', $this->age($age)),
            $lastRun === null
                ? (string) __('Delivery has not run yet')
                : (string) __(
                    'Last delivery ran %1',
                    $this->timezone->formatDateTime($lastRun, \IntlDateFormatter::MEDIUM, \IntlDateFormatter::SHORT)
                ),
        ];
        $failed = $report->getFailedCount();
        $failure = trim((string) $report->getLatestFailure());

        return [
            'pending' => $report->getPendingCount() + $report->getSendingCount(),
            'sent' => $report->getSentCount(),
            'failed' => $failed,
            'progress' => implode(' · ', $progress),
            'failedLine' => match (true) {
                $failed === 0 => '',
                $failure === '' => (string) __('%1 failed', $failed),
                default => (string) __('%1 failed — last error: %2', $failed, $failure),
            },
        ];
    }

    /**
     * Round an age down to its largest whole unit
     *
     * @param int $seconds
     * @return string
     */
    private function age(int $seconds): string
    {
        $seconds = max(0, $seconds);
        if ($seconds >= self::DAY) {
            return (string) __('%1 d', intdiv($seconds, self::DAY));
        }
        if ($seconds >= self::HOUR) {
            return (string) __('%1 h', intdiv($seconds, self::HOUR));
        }
        if ($seconds >= self::MINUTE) {
            return (string) __('%1 min', intdiv($seconds, self::MINUTE));
        }

        return (string) __('%1 s', $seconds);
    }
}
