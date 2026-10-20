<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideAdminUi\Test\Unit\Model\Config\Source;

use Hryvinskyi\DataLayer\Test\Unit\DictionaryRenderer;
use Hryvinskyi\DataLayerServerSideAdminUi\Model\Config\Source\Transports;
use Magento\Framework\Phrase;
use Magento\Framework\Phrase\RendererInterface;
use PHPUnit\Framework\TestCase;

/**
 * The transports are offered in registration order, their labels translated each time the options are read
 */
class TransportsTest extends TestCase
{
    /**
     * The renderer phrases had before the test
     *
     * @var RendererInterface|null
     */
    private ?RendererInterface $renderer = null;

    /**
     * Remember the renderer phrases have before the test
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->renderer = Phrase::getRenderer();
    }

    /**
     * Restore the renderer phrases had before the test
     *
     * @return void
     */
    protected function tearDown(): void
    {
        if ($this->renderer !== null) {
            Phrase::setRenderer($this->renderer);
        }
    }

    /**
     * One shared list answers in whichever locale reads it; the codes are never translated
     *
     * @return void
     */
    public function testLabelsAreTranslatedWhenRead(): void
    {
        $transports = new Transports(['ga4' => 'Google Analytics', 'sgtm' => 'Server-side Tag Manager']);

        Phrase::setRenderer(new DictionaryRenderer(['Server-side Tag Manager' => 'Serverseitiger Tag Manager']));
        self::assertSame([
            ['value' => 'ga4', 'label' => 'Google Analytics'],
            ['value' => 'sgtm', 'label' => 'Serverseitiger Tag Manager'],
        ], $transports->toOptionArray());

        Phrase::setRenderer(new DictionaryRenderer(['Google Analytics' => 'Google Analytique']));
        self::assertSame('Google Analytique', $transports->toOptionArray()[0]['label']);
    }
}
