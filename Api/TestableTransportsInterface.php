<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideAdminUi\Api;

/**
 * The transports a test event may go through: those whose debug mode sends to a validation endpoint that records
 * nothing
 *
 * A transport without such an endpoint, such as a server container, would record the test event, so it is never
 * tested. The sender and every screen that offers the test read the one list.
 *
 * @api
 */
interface TestableTransportsInterface
{
    /**
     * Whether a transport can be tested
     *
     * @param string $code The transport's code
     * @return bool
     */
    public function isTestable(string $code): bool;

    /**
     * Get the codes of the transports that can be tested
     *
     * @return list<string>
     */
    public function getCodes(): array;
}
