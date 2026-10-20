<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideAdminUi\Api\Setup;

use Hryvinskyi\DataLayerAdminUi\Api\Setup\InvalidSetupRequestException;

/**
 * Finds the website whose server-side settings apply at a setup wizard scope
 *
 * Server-side settings are kept per website: a website uses its own, a store view its website's, and the default
 * scope the default ones.
 *
 * @api
 */
interface ScopeWebsiteInterface
{
    /**
     * Get the website of a scope
     *
     * @param mixed $scope A scope id as the wizard writes it: `default`, `website:<id>` or `store:<id>`
     * @return int The website id; 0 for the default scope
     * @throws InvalidSetupRequestException When the value names no scope of the store; the reason says so
     */
    public function websiteOf(mixed $scope): int;
}
