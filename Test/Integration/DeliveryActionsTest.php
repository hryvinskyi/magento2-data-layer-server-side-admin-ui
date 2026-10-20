<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideAdminUi\Test\Integration;

use Hryvinskyi\DataLayerAdminUi\Test\Integration\RendersSettingsForm;
use Hryvinskyi\DataLayerServerSide\Model\Data\DeliveryReport;
use Hryvinskyi\DataLayerServerSide\Model\Delivery\DeliveryStatus as OutboxDeliveryStatus;
use Hryvinskyi\DataLayerServerSideAdminUi\Test\Integration\Fake\FixedDeliveryStatus;
use Magento\Backend\Model\Auth;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use PHPUnit\Framework\TestCase;

/**
 * The settings page offers "Retry failed" beside the failed events and the "Send test event" row, each only to an
 * administrator the action's endpoint lets in, wired by the container and rendered through their templates
 *
 * @magentoAppArea adminhtml
 * @magentoAppIsolation enabled
 * @magentoDbIsolation enabled
 */
class DeliveryActionsTest extends TestCase
{
    use RendersSettingsForm;
    use DeniesDeliveryActions;

    private const SECTION = 'hryvinskyi_datalayer';
    private const DELIVERY_STATUS = 'hryvinskyi_datalayer_server_delivery_status';
    private const TEST_EVENT = 'hryvinskyi_datalayer_server_test_event';
    private const SCRIPT = '#<script type="importmap"[^>]*>\{"imports":\{[^<]*delivery-actions\\\\/test-event\.js[^<]*'
        . '</script><script[^>]*type="module"[^>]*src="[^"]*/Hryvinskyi_DataLayerServerSideAdminUi/js/'
        . 'delivery-actions/main\.js"#';

    /**
     * @inheritDoc
     */
    protected function tearDown(): void
    {
        $this->signOut();
    }

    /**
     * With failed events at a website, the status carries the retry button posting that website to the retry
     * endpoint, disabled until its script binds it
     *
     * @return void
     */
    public function testRetryButtonBesideFailedEvents(): void
    {
        $this->reportFailures(12);
        $websiteId = $this->mainWebsiteId();

        $html = $this->rowHtml($this->settingsForm(self::SECTION, $websiteId), self::DELIVERY_STATUS);

        self::assertStringContainsString('12 failed — last error: HTTP 403', $html);
        self::assertMatchesRegularExpression(
            '#data-hryvinskyi-datalayer-retry-failed="1">\s*<button type="button" class="action-default"'
            . ' data-role="retry" disabled="disabled">\s*<span>Retry failed</span>#',
            $html
        );
        $config = $this->config($html, 'data-hryvinskyi-datalayer-retry-failed');
        self::assertIsString($config['url']);
        self::assertStringContainsString('/hryvinskyi_datalayer_server/delivery/retryFailed/', $config['url']);
        self::assertSame($websiteId, $config['website']);
        self::assertIsString($config['formKey']);
        self::assertNotSame('', $config['formKey']);
        self::assertMatchesRegularExpression(self::SCRIPT, $html);
    }

    /**
     * A page showing both buttons loads their script once: one import map, one entry, and both buttons to bind
     *
     * @return void
     */
    public function testScriptIsPrintedOncePerPage(): void
    {
        $this->reportFailures(3);
        $form = $this->settingsForm(self::SECTION, $this->mainWebsiteId());

        $html = $this->rowHtml($form, self::DELIVERY_STATUS) . $this->rowHtml($form, self::TEST_EVENT);

        self::assertStringContainsString('data-hryvinskyi-datalayer-retry-failed="1"', $html);
        self::assertStringContainsString('data-hryvinskyi-datalayer-test-event="1"', $html);
        self::assertSame(1, preg_match_all(self::SCRIPT, $html));
        self::assertSame(1, substr_count($html, 'type="importmap"'));
    }

    /**
     * Without failed events the status shows no retry button
     *
     * @return void
     */
    public function testNoRetryButtonWithoutFailures(): void
    {
        $this->reportFailures(0);

        $html = $this->rowHtml($this->settingsForm(self::SECTION), self::DELIVERY_STATUS);

        self::assertStringContainsString('data-role="progress"', $html);
        self::assertStringNotContainsString('data-hryvinskyi-datalayer-retry-failed', $html);
    }

    /**
     * Without the delivery actions' rule the status still shows the failed events, but no retry button
     *
     * @return void
     */
    public function testNoRetryButtonWithoutAccess(): void
    {
        $this->reportFailures(4);
        $form = $this->settingsForm(self::SECTION);
        $this->denyDeliveryActions($this->service(Auth::class));

        $html = $this->rowHtml($form, self::DELIVERY_STATUS);

        self::assertStringContainsString('4 failed', $html);
        self::assertStringNotContainsString('data-hryvinskyi-datalayer-retry-failed', $html);
    }

    /**
     * The test row offers the button for the website's saved Google Analytics transport, with its script and the
     * translated wording the script shows
     *
     * @return void
     */
    public function testTestEventRow(): void
    {
        $websiteId = $this->mainWebsiteId();

        $html = $this->rowHtml($this->settingsForm(self::SECTION, $websiteId), self::TEST_EVENT);

        self::assertStringContainsString('<span>Send test event</span>', $html);
        self::assertStringContainsString('Sends one event to Google’s validation endpoint', $html);
        self::assertStringNotContainsString('data-role="unavailable"', $html);
        $config = $this->config($html, 'data-hryvinskyi-datalayer-test-event');
        self::assertTrue($config['testable']);
        self::assertSame($websiteId, $config['website']);
        self::assertIsString($config['url']);
        self::assertStringContainsString('/hryvinskyi_datalayer_server/delivery/testEvent/', $config['url']);
        self::assertSame(20, $config['timeoutSeconds']);
        self::assertIsArray($config['wording']);
        self::assertSame('Google returned {count} validation messages:', $config['wording']['messagesHeading'] ?? null);
        self::assertMatchesRegularExpression(self::SCRIPT, $html);
    }

    /**
     * With a server container as the saved transport the button stays disabled with the note saying why
     *
     * @return void
     */
    public function testTestEventRowForServerContainer(): void
    {
        $websiteId = $this->mainWebsiteId();
        $this->saveSettings(
            self::SECTION,
            ['server' => ['groups' => ['delivery' => ['fields' => ['transport' => ['value' => 'sgtm']]]]]],
            $websiteId
        );

        $html = $this->rowHtml($this->settingsForm(self::SECTION, $websiteId), self::TEST_EVENT);

        self::assertStringContainsString(
            'Testing is available for Google Analytics (Measurement Protocol) only.',
            $html
        );
        self::assertFalse($this->config($html, 'data-hryvinskyi-datalayer-test-event')['testable']);
    }

    /**
     * Without the delivery actions' rule the test row is left out
     *
     * @return void
     */
    public function testTestEventRowLeftOutWithoutAccess(): void
    {
        $form = $this->settingsForm(self::SECTION, $this->mainWebsiteId());
        $this->denyDeliveryActions($this->service(Auth::class));

        self::assertSame('', $this->rowHtml($form, self::TEST_EVENT));
    }

    /**
     * Make the delivery status report a number of failed events
     *
     * @param int $failed
     * @return void
     */
    private function reportFailures(int $failed): void
    {
        $lastRun = new \DateTimeImmutable('-1 minute', new \DateTimeZone('UTC'));
        $this->testObjectManager()->addSharedInstance(
            new FixedDeliveryStatus(new DeliveryReport(1, 3, 0, 40, $failed, null, null, $lastRun, 'HTTP 403')),
            OutboxDeliveryStatus::class
        );
    }

    /**
     * Read the JSON configuration printed inside an action's root
     *
     * @param string $html
     * @param string $rootAttribute
     * @return array<mixed>
     */
    private function config(string $html, string $rootAttribute): array
    {
        $found = preg_match(
            '#' . preg_quote($rootAttribute, '#') . '="1".*?<script type="application/json" data-role="config">'
            . '(.*?)</script>#s',
            $html,
            $match
        );
        self::assertSame(1, $found, 'The action prints its configuration.');
        $config = json_decode($match[1], true);
        self::assertIsArray($config);

        return $config;
    }

    /**
     * Get the test framework's object manager, which can hold a prepared shared instance
     *
     * @return ObjectManager
     */
    private function testObjectManager(): ObjectManager
    {
        $objectManager = Bootstrap::getObjectManager();
        self::assertInstanceOf(ObjectManager::class, $objectManager);

        return $objectManager;
    }
}
