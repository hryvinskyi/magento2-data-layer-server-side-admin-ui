<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideAdminUi\Api;

use Hryvinskyi\DataLayerServerSideAdminUi\Api\Data\TestResultInterface;

/**
 * Checks a website's saved delivery settings by sending one test event to Google's validation endpoint
 *
 * The validation endpoint records nothing, so the test never shows up in reports. The event is sent at once and never
 * written to the outbox, and it never goes to the endpoint that records events. Every outcome is a result: the method
 * does not throw, and no part of a result or of a log line it writes contains the API secret.
 *
 * @api
 */
interface TestEventSenderInterface
{
    /**
     * Whether a website's saved transport has a validation endpoint the test event can go to
     *
     * A transport without one, such as a server container, would record the test event, so it is not tested.
     *
     * @param int $websiteId 0 for the default scope's settings
     * @return bool
     */
    public function canTest(int $websiteId): bool;

    /**
     * Send the test event with a website's saved settings
     *
     * @param int $websiteId 0 for the default scope's settings
     * @return TestResultInterface
     */
    public function send(int $websiteId): TestResultInterface;
}
