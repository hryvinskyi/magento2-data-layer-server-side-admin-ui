<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideAdminUi\Test\Unit\Model;

use Hryvinskyi\DataLayerServerSide\Api\ConfigInterface;
use Hryvinskyi\DataLayerServerSideAdminUi\Model\ServerDeliveryState;
use PHPUnit\Framework\TestCase;

/**
 * The event settings learn whether server events are delivered for a website from this module's own setting
 */
class ServerDeliveryStateTest extends TestCase
{
    /**
     * A website reads its own setting; the default scope reads the admin website's, which is the default value; the
     * settings live in the server-side group of the data layer's section
     *
     * @return void
     */
    public function testReadsTheWebsitesSetting(): void
    {
        $config = $this->createMock(ConfigInterface::class);
        $config->method('isEnabled')->willReturnMap([[3, true], [0, false]]);
        $state = new ServerDeliveryState($config);

        self::assertTrue($state->isEnabled(3));
        self::assertFalse($state->isEnabled(null));
        self::assertSame('hryvinskyi_datalayer/server', $state->getSettingsGroupPath());
    }
}
