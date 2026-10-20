<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideAdminUi\Api;

/**
 * Queues failed server events again for an administrator, and says how it went
 *
 * At a website only that website's events; at the default scope every website's. The outcome is always a sentence
 * for the person who asked: how many events are queued again, or that it failed. The reason of a failure goes to the
 * log, never to the person; the method does not throw.
 *
 * @phpstan-type RetryOutcome array{done: bool, message: string}
 * @api
 */
interface RetryFailedEventsInterface
{
    /**
     * Queue the failed events of a scope again
     *
     * @param int|null $websiteId Null for every website
     * @return RetryOutcome `done` is false when the events could not be queued again; `message` is translated
     */
    public function retry(?int $websiteId): array;
}
