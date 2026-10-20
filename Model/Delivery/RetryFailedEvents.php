<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideAdminUi\Model\Delivery;

use Hryvinskyi\DataLayerServerSide\Api\FailedEventRetryInterface;
use Hryvinskyi\DataLayerServerSideAdminUi\Api\DeliveryActionNoticesInterface;
use Hryvinskyi\DataLayerServerSideAdminUi\Api\RetryFailedEventsInterface;
use Psr\Log\LoggerInterface;

/**
 * Queues the failed events again through the server-side module and turns the outcome into the sentence to tell
 *
 * Any failure is logged with its class and message and reported as "could not be queued again".
 */
class RetryFailedEvents implements RetryFailedEventsInterface
{
    /**
     * @param FailedEventRetryInterface $retry
     * @param DeliveryActionNoticesInterface $notices
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly FailedEventRetryInterface $retry,
        private readonly DeliveryActionNoticesInterface $notices,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritDoc
     */
    public function retry(?int $websiteId): array
    {
        try {
            $count = $this->retry->retry($websiteId);
        } catch (\Throwable $exception) {
            $this->logger->error(sprintf(
                'The failed server events of %s could not be queued again: %s: %s',
                $websiteId === null ? 'every website' : 'website ' . $websiteId,
                $exception::class,
                $exception->getMessage()
            ));

            return ['done' => false, 'message' => (string) $this->notices->notQueued()];
        }

        return ['done' => true, 'message' => (string) $this->notices->queuedAgain($count)];
    }
}
