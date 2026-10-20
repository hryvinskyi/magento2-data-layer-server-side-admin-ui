<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideAdminUi\Test\Unit\Model\Setup;

use Hryvinskyi\DataLayer\Test\Unit\DictionaryRenderer;
use Hryvinskyi\DataLayerServerSideAdminUi\Api\Setup\ServerStepDataInterface;
use Hryvinskyi\DataLayerServerSideAdminUi\Model\Setup\ServerSideContribution;
use Magento\Framework\Phrase;
use Magento\Framework\Phrase\RendererInterface;
use PHPUnit\Framework\TestCase;

/**
 * The step joins the wizard as the plugin `server`, imported from this module's built file, with its wording and its
 * data keyed by the plugin id
 */
class ServerSideContributionTest extends TestCase
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
     * The wording arrives from the wiring untranslated and is translated each time it is read, so the one shared
     * contribution answers every request in that request's locale
     *
     * @return void
     */
    public function testWordingIsTranslatedWhenRead(): void
    {
        $contribution = new ServerSideContribution($this->stepData([]), ['server.step.title' => 'Server-side']);

        Phrase::setRenderer(new DictionaryRenderer(['Server-side' => 'Serverseitig']));
        self::assertSame(['server.step.title' => 'Serverseitig'], $contribution->getPhrases());

        Phrase::setRenderer(new DictionaryRenderer(['Server-side' => 'Côté serveur']));
        self::assertSame(['server.step.title' => 'Côté serveur'], $contribution->getPhrases());
    }

    /**
     * Wording, plugin, import and data
     *
     * @return void
     */
    public function testContributesTheStep(): void
    {
        $contribution = new ServerSideContribution($this->stepData(['canAct' => false]), ['server.step.title' => 'X']);

        self::assertSame(['server.step.title' => 'X'], $contribution->getPhrases());
        self::assertSame(['server' => '@hryvinskyi/datalayer-setup-server'], $contribution->getPlugins());
        self::assertSame(
            ['@hryvinskyi/datalayer-setup-server' => 'Hryvinskyi_DataLayerServerSideAdminUi::js/setup/server-step.js'],
            $contribution->getImports()
        );
        self::assertSame(['server' => ['canAct' => false]], $contribution->getExtensionData());
    }

    /**
     * A phrase that is not text keyed by text fails loudly
     *
     * @return void
     */
    public function testRefusesAPhraseOfTheWrongShape(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ServerSideContribution($this->stepData([]), ['server.step.title' => ['X']]);
    }

    /**
     * Build step data answering as given
     *
     * @param array<string, mixed> $data
     * @return ServerStepDataInterface
     */
    private function stepData(array $data): ServerStepDataInterface
    {
        $stepData = $this->createStub(ServerStepDataInterface::class);
        $stepData->method('get')->willReturn($data);

        return $stepData;
    }
}
