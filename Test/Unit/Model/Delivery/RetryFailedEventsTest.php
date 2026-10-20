<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideAdminUi\Test\Unit\Model\Delivery;

use Hryvinskyi\DataLayerServerSide\Api\FailedEventRetryInterface;
use Hryvinskyi\DataLayerServerSide\Test\Unit\Fake\RecordingLogger;
use Hryvinskyi\DataLayerServerSideAdminUi\Model\Delivery\DeliveryActionNotices;
use Hryvinskyi\DataLayerServerSideAdminUi\Model\Delivery\RetryFailedEvents;
use PHPUnit\Framework\TestCase;

/**
 * Queueing failed events again tells how many were queued, or that it failed; a failure's reason goes to the log only
 */
class RetryFailedEventsTest extends TestCase
{
    /**
     * The count of a successful retry, for the scope asked
     *
     * @return void
     */
    public function testTellsHowManyWereQueued(): void
    {
        $asked = [];
        $retry = $this->createStub(FailedEventRetryInterface::class);
        $retry->method('retry')->willReturnCallback(static function (?int $websiteId) use (&$asked): int {
            $asked[] = $websiteId;

            return 3;
        });

        $outcome = (new RetryFailedEvents($retry, new DeliveryActionNotices(), new RecordingLogger()))->retry(4);

        self::assertSame(['done' => true, 'message' => '3 failed events are queued again.'], $outcome);
        self::assertSame([4], $asked);
    }

    /**
     * A failure is told without its reason, which is logged with the scope
     *
     * @return void
     */
    public function testFailureIsLoggedNotShown(): void
    {
        $retry = $this->createStub(FailedEventRetryInterface::class);
        $retry->method('retry')->willThrowException(new \RuntimeException('table locked'));
        $logger = new RecordingLogger();

        $outcome = (new RetryFailedEvents($retry, new DeliveryActionNotices(), $logger))->retry(null);

        self::assertSame(
            ['done' => false, 'message' => 'The failed events could not be queued again. The log has the details.'],
            $outcome
        );
        self::assertCount(1, $logger->records);
        self::assertStringContainsString('every website', $logger->records[0]['message']);
        self::assertStringContainsString('table locked', $logger->records[0]['message']);
    }
}
