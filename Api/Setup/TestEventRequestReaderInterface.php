<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideAdminUi\Api\Setup;

use Hryvinskyi\DataLayerAdminUi\Api\Setup\InvalidSetupRequestException;
use Magento\Framework\App\RequestInterface;

/**
 * Reads a test event request of the setup wizard: a JSON body `{"scope": scope id, "settings": object}`
 *
 * @phpstan-type TestEventRequest array{websiteId: int, draft: array<string, string>}
 * @api
 */
interface TestEventRequestReaderInterface
{
    /**
     * Read which website to test and with which unsaved settings
     *
     * @param RequestInterface $request
     * @return TestEventRequest The website id (0 for the default scope) and the drafted config values
     * @throws InvalidSetupRequestException When the body, the scope or a setting is unusable; the reason says why
     */
    public function read(RequestInterface $request): array;
}
