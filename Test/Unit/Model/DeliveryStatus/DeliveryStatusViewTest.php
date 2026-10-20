<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideAdminUi\Test\Unit\Model\DeliveryStatus;

use Hryvinskyi\DataLayerServerSide\Model\Data\DeliveryReport;
use Hryvinskyi\DataLayerServerSideAdminUi\Model\DeliveryStatus\DeliveryStatusView;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A delivery report becomes three counts, a progress line and, when events failed, a failed line
 */
class DeliveryStatusViewTest extends TestCase
{
    /**
     * A report with waiting, sending, sent and failed events, a last run and a latest failure
     *
     * @return void
     */
    public function testReportWithFailures(): void
    {
        $lastRun = new \DateTimeImmutable('2026-10-15 12:31:00', new \DateTimeZone('UTC'));
        $timezone = $this->createMock(TimezoneInterface::class);
        $timezone->expects(self::once())->method('formatDateTime')
            ->with($lastRun, \IntlDateFormatter::MEDIUM, \IntlDateFormatter::SHORT)
            ->willReturn('Oct 15, 2026, 2:31 PM');
        $report = new DeliveryReport(
            7,
            30,
            7,
            214,
            12,
            $lastRun->modify('-26 minutes'),
            1560,
            $lastRun,
            'HTTP 403 (check the API secret)'
        );

        self::assertSame(
            [
                'pending' => 37,
                'sent' => 214,
                'failed' => 12,
                'progress' => 'Oldest pending event: 26 min · Last delivery ran Oct 15, 2026, 2:31 PM',
                'failedLine' => '12 failed — last error: HTTP 403 (check the API secret)',
            ],
            (new DeliveryStatusView($timezone))->describe($report)
        );
    }

    /**
     * Nothing waiting, never run and nothing failed say so
     *
     * @return void
     */
    public function testEmptyReport(): void
    {
        $timezone = $this->createMock(TimezoneInterface::class);
        $timezone->expects(self::never())->method('formatDateTime');

        self::assertSame(
            [
                'pending' => 0,
                'sent' => 0,
                'failed' => 0,
                'progress' => 'Nothing is waiting to be sent · Delivery has not run yet',
                'failedLine' => '',
            ],
            (new DeliveryStatusView($timezone))->describe(new DeliveryReport(null))
        );
    }

    /**
     * Failures without a recorded error show the count alone
     *
     * @return void
     */
    public function testFailureWithoutError(): void
    {
        $view = (new DeliveryStatusView($this->createMock(TimezoneInterface::class)))
            ->describe(new DeliveryReport(1, 0, 0, 0, 3));

        self::assertSame('3 failed', $view['failedLine']);
    }

    /**
     * The oldest waiting event's age is rounded down to its largest whole unit
     *
     * @dataProvider ages
     * @param int $seconds
     * @param string $shown
     * @return void
     */
    #[DataProvider('ages')]
    public function testAgeIsRoundedDown(int $seconds, string $shown): void
    {
        $report = new DeliveryReport(
            1,
            1,
            0,
            0,
            0,
            new \DateTimeImmutable('2026-10-15 12:00:00', new \DateTimeZone('UTC')),
            $seconds
        );
        $view = (new DeliveryStatusView($this->createMock(TimezoneInterface::class)))->describe($report);

        self::assertSame('Oldest pending event: ' . $shown . ' · Delivery has not run yet', $view['progress']);
    }

    /**
     * Ages and how they are shown
     *
     * @return array<string, array{int, string}>
     */
    public static function ages(): array
    {
        return [
            'seconds' => [40, '40 s'],
            'a minute' => [60, '1 min'],
            'hours' => [7259, '2 h'],
            'days' => [190000, '2 d'],
        ];
    }
}
