<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideAdminUi\Test\Unit\Model\Delivery;

use Hryvinskyi\DataLayerServerSideAdminUi\Model\Delivery\TestableTransports;
use PHPUnit\Framework\TestCase;

/**
 * The testable transports are the listed codes, each once; anything else is refused when the list is built
 */
class TestableTransportsTest extends TestCase
{
    /**
     * Listed codes are testable, others are not
     *
     * @return void
     */
    public function testListedCodesAreTestable(): void
    {
        $transports = new TestableTransports(['ga4' => 'ga4', 'again' => 'ga4']);

        self::assertSame(['ga4'], $transports->getCodes());
        self::assertTrue($transports->isTestable('ga4'));
        self::assertFalse($transports->isTestable('sgtm'));
    }

    /**
     * An empty or non-text code fails loudly
     *
     * @return void
     */
    public function testRefusesUnusableCodes(): void
    {
        foreach ([[''], [3]] as $codes) {
            try {
                new TestableTransports($codes);
                self::fail('The list is refused: ' . (string) json_encode($codes));
            } catch (\InvalidArgumentException $refusal) {
                self::assertSame('Every testable transport is a non-empty code.', $refusal->getMessage());
            }
        }
    }
}
