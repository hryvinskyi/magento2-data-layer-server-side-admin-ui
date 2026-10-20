<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideAdminUi\Test\Unit\Model\DeliveryStatus;

use Hryvinskyi\DataLayerAdminUi\Api\ModuleScriptInterface;
use Hryvinskyi\DataLayerServerSideAdminUi\Model\DeliveryStatus\DeliveryActionsScript;
use PHPUnit\Framework\TestCase;

/**
 * The delivery actions load their entry with every module it imports by name, each named after its own file, once per
 * page
 */
class DeliveryActionsScriptTest extends TestCase
{
    /**
     * @return void
     */
    public function testEntryComesWithEveryImportedModule(): void
    {
        $asked = [];
        $moduleScript = $this->createMock(ModuleScriptInterface::class);
        $moduleScript->method('render')->willReturnCallback(
            static function (string $entry, array $imports) use (&$asked): string {
                $asked[] = [$entry, $imports];

                return '<tags/>';
            }
        );

        $script = new DeliveryActionsScript($moduleScript);

        self::assertSame('<tags/>', $script->render());
        self::assertSame('', $script->render(), 'A page carries the tags once, however many buttons ask.');
        $prefix = '@hryvinskyi/data-layer-server-side-admin/delivery-actions/';
        $directory = 'Hryvinskyi_DataLayerServerSideAdminUi::js/delivery-actions/';
        self::assertSame(
            [[
                $directory . 'main.js',
                [
                    $prefix . 'retry-failed.js' => $directory . 'retry-failed.js',
                    $prefix . 'test-event.js' => $directory . 'test-event.js',
                    $prefix . 'action-config.js' => $directory . 'action-config.js',
                    $prefix . 'test-result.js' => $directory . 'test-result.js',
                ],
            ]],
            $asked
        );
    }
}
