<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideAdminUi\Test\Integration;

use Hryvinskyi\DataLayerAdminUi\Api\ServerDeliveryStateInterface;
use Hryvinskyi\DataLayerAdminUi\Test\Integration\RendersSettingsForm;
use Hryvinskyi\DataLayerServerSide\Model\Data\DeliveryReport;
use Hryvinskyi\DataLayerServerSide\Model\Delivery\DeliveryStatus as OutboxDeliveryStatus;
use Hryvinskyi\DataLayerServerSideAdminUi\Block\Adminhtml\System\Config\DeliveryStatus;
use Hryvinskyi\DataLayerServerSideAdminUi\Model\ServerDeliveryState;
use Hryvinskyi\DataLayerServerSideAdminUi\Test\Integration\Fake\FixedDeliveryStatus;
use Hryvinskyi\DataLayerServerSideAdminUi\Test\Integration\Fake\MarkerAction;
use Magento\Config\Block\System\Config\Form;
use Magento\Config\Model\Config\Structure;
use Magento\Config\Model\Config\Structure\Element\Section;
use Magento\Framework\Acl\Builder;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Element\AbstractBlock;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use PHPUnit\Framework\TestCase;

/**
 * The server-side settings render as a group of the data layer's section for a signed-in administrator, closed until
 * opened, each setting still stored at its own path, with the read-only rows and the delivery actions under the
 * section's access rule; and the event settings learn from here whether server routes are delivered
 *
 * @magentoAppArea adminhtml
 * @magentoAppIsolation enabled
 * @magentoDbIsolation enabled
 */
class AdminSectionTest extends TestCase
{
    use RendersSettingsForm;

    private const SECTION = 'hryvinskyi_datalayer';
    private const GROUP = 'hryvinskyi_datalayer_server';
    private const ACL_RESOURCE = 'Hryvinskyi_DataLayerAdminUi::config';
    private const DELIVERY_ACL_RESOURCE = 'Hryvinskyi_DataLayerServerSideAdminUi::delivery';
    private const DELIVERY_STATUS = 'hryvinskyi_datalayer_server_delivery_status';
    private const STORED = 'hryvinskyi_datalayer_server/';

    /**
     * @inheritDoc
     */
    protected function tearDown(): void
    {
        $this->signOut();
    }

    /**
     * The server-side settings are one group of the data layer's section, holding a group per concern, with the
     * measurement id beside the transport that both delivery groups depend on; the section of their own is gone
     *
     * @return void
     */
    public function testSettingsAreAGroupOfTheDataLayerSection(): void
    {
        $form = $this->settingsForm(self::SECTION)->getForm();
        self::assertNotNull($form->getElement(self::GROUP));
        foreach (['delivery', 'ga4', 'sgtm', 'identity', 'orders', 'consent'] as $group) {
            self::assertNotNull($form->getElement(self::GROUP . '_' . $group), $group);
        }
        self::assertNotNull($form->getElement(self::GROUP . '_delivery_measurement_id'));
        self::assertNull($form->getElement(self::GROUP . '_ga4_measurement_id'));

        $structure = $this->service(Structure::class);
        $former = $structure->getElement('hryvinskyi_datalayer_server');
        self::assertInstanceOf(Section::class, $former);
        self::assertNull($former->getAttribute('tab'), 'Nothing declares the former section any more.');
        self::assertNull($former->getAttribute('resource'));
    }

    /**
     * The settings are guarded by the data layer section's access rule; the delivery actions keep a rule of their
     * own under it; the section rule this module had is gone
     *
     * @return void
     */
    public function testGuardedByTheSectionsAclResource(): void
    {
        $acl = $this->service(Builder::class)->getAcl();
        self::assertTrue($acl->hasResource(self::ACL_RESOURCE));
        self::assertTrue($acl->hasResource(self::DELIVERY_ACL_RESOURCE));
        self::assertTrue($acl->inheritsResource(self::DELIVERY_ACL_RESOURCE, self::ACL_RESOURCE, true));
        self::assertFalse($acl->hasResource('Hryvinskyi_DataLayerServerSideAdminUi::config'));
        self::assertFalse($acl->hasResource('Hryvinskyi_DataLayerServerSide::config'));

        $this->settingsForm(self::SECTION);
        $section = $this->service(Structure::class)->getElement(self::SECTION);
        self::assertInstanceOf(Section::class, $section);
        self::assertSame(self::ACL_RESOURCE, $section->getAttribute('resource'));
        self::assertTrue($section->isAllowed());
    }

    /**
     * Every setting posted through the group is stored at the path it always had, only at the website it was saved
     * for; the form shows each stored value with "use the parent scope's value" unticked, and ticking it removes the
     * website's value again
     *
     * @return void
     */
    public function testEverySettingKeepsItsPath(): void
    {
        $websiteId = $this->mainWebsiteId();
        $this->saveSettings(self::SECTION, ['server' => ['groups' => [
            'delivery' => ['fields' => [
                'enabled' => ['value' => '1'],
                'transport' => ['value' => 'sgtm'],
                'measurement_id' => ['value' => 'g-ab12'],
                'debug' => ['value' => '1'],
                'max_attempts' => ['value' => '7'],
            ]],
            'ga4' => ['fields' => ['region' => ['value' => 'eu']]],
            'sgtm' => ['fields' => ['endpoint' => ['value' => 'https://sgtm.example.com']]],
            'identity' => ['fields' => [
                'send_user_id' => ['value' => '1'],
                'fallback_cookie' => ['value' => '0'],
                'retention_days' => ['value' => '90'],
            ]],
            'orders' => ['fields' => ['purchase_trigger' => ['value' => 'placed']]],
        ]]], $websiteId);

        $stored = [
            'general/enabled' => '1',
            'general/transport' => 'sgtm',
            'ga4/measurement_id' => 'G-AB12',
            'delivery/debug' => '1',
            'delivery/max_attempts' => '7',
            'ga4/region' => 'eu',
            'sgtm/endpoint' => 'https://sgtm.example.com',
            'identity/send_user_id' => '1',
            'identity/fallback_cookie' => '0',
            'identity/retention_days' => '90',
            'orders/purchase_trigger' => 'placed',
        ];
        foreach ($stored as $path => $value) {
            self::assertSame($value, $this->storedSetting(self::STORED . $path, $websiteId), $path);
            self::assertNull($this->storedSetting(self::STORED . $path), $path);
        }

        $form = $this->settingsForm(self::SECTION, $websiteId)->getForm();
        $shown = [
            'delivery_transport' => 'sgtm',
            'delivery_measurement_id' => 'G-AB12',
            'delivery_max_attempts' => '7',
            'sgtm_endpoint' => 'https://sgtm.example.com',
            'identity_retention_days' => '90',
            'orders_purchase_trigger' => 'placed',
        ];
        foreach ($shown as $id => $value) {
            $element = $form->getElement(self::GROUP . '_' . $id);
            self::assertInstanceOf(AbstractElement::class, $element, $id);
            self::assertSame($value, $element->getData('value'), $id);
            self::assertFalse((bool) $element->getData('inherit'), $id);
        }

        $this->saveSettings(self::SECTION, ['server' => ['groups' => [
            'delivery' => ['fields' => ['transport' => ['inherit' => '1']]],
        ]]], $websiteId);
        self::assertNull($this->storedSetting(self::STORED . 'general/transport', $websiteId));
        $transport = $this->settingsForm(self::SECTION, $websiteId)->getForm()
            ->getElement(self::GROUP . '_delivery_transport');
        self::assertInstanceOf(AbstractElement::class, $transport);
        self::assertTrue((bool) $transport->getData('inherit'));
    }

    /**
     * A value the setting's own check refuses is still refused through the group
     *
     * @return void
     */
    public function testRefusedValueIsRefusedThroughTheGroup(): void
    {
        $this->expectException(LocalizedException::class);

        $this->saveSettings(self::SECTION, ['server' => ['groups' => [
            'identity' => ['fields' => ['retention_days' => ['value' => '7']]],
        ]]]);
    }

    /**
     * The Google Analytics group shows only for its transport and the server container's group only for its own;
     * the dependency names the transport where the group now renders it
     *
     * @return void
     */
    public function testDeliveryGroupsDependOnTheTransport(): void
    {
        $form = $this->settingsForm(self::SECTION);
        $dependence = $form->getChildBlock('element_dependence');
        self::assertInstanceOf(AbstractBlock::class, $dependence);
        $script = (string) $dependence->toHtml();
        $found = preg_match('#new FormElementDependenceController\((\{(?:[^{}]|(?1))*\})#', $script, $match);
        self::assertSame(1, $found);
        $depends = json_decode($match[1], true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($depends);

        $transport = self::GROUP . '_delivery_transport';
        self::assertSame(
            [$transport => ['values' => ['ga4'], 'negative' => false]],
            $depends[self::GROUP . '_ga4'] ?? null
        );
        self::assertSame(
            [$transport => ['values' => ['sgtm'], 'negative' => false]],
            $depends[self::GROUP . '_sgtm'] ?? null
        );
    }

    /**
     * The group starts closed for an administrator who never opened it
     *
     * @return void
     */
    public function testGroupStartsClosed(): void
    {
        $html = $this->rowHtml($this->settingsForm(self::SECTION), self::GROUP);
        $state = $this->elements($this->parsed($html), '//input[@id="' . self::GROUP . '-state"]');

        self::assertCount(1, $state);
        self::assertSame('0', $state[0]->getAttribute('value'));
    }

    /**
     * At a website the delivery status shows that website's counts, its progress, the failed line and the actions
     * registered for failed events; it stores nothing
     *
     * @return void
     */
    public function testDeliveryStatusAtWebsite(): void
    {
        $lastRun = new \DateTimeImmutable('-1 minute', new \DateTimeZone('UTC'));
        $status = new FixedDeliveryStatus(new DeliveryReport(
            1,
            30,
            7,
            214,
            12,
            $lastRun->modify('-25 minutes'),
            1560,
            $lastRun,
            'HTTP 403 (check the API secret)'
        ));
        $this->testObjectManager()->addSharedInstance($status, OutboxDeliveryStatus::class);
        $websiteId = $this->mainWebsiteId();
        $element = $this->element($this->settingsForm(self::SECTION, $websiteId), self::DELIVERY_STATUS);
        $renderer = Bootstrap::getObjectManager()->create(
            DeliveryStatus::class,
            ['failedActions' => ['marker' => new MarkerAction()]]
        );
        self::assertInstanceOf(DeliveryStatus::class, $renderer);
        $html = (string) $renderer->render($element);

        self::assertSame([$websiteId], $status->askedWebsites);
        self::assertMatchesRegularExpression('#data-role="pending">\s*37\s*<#', $html);
        self::assertMatchesRegularExpression('#data-role="sent">\s*214\s*<#', $html);
        self::assertMatchesRegularExpression('#data-role="failed" class="is-failing">\s*12\s*<#', $html);
        self::assertStringContainsString('Oldest pending event: 26 min · Last delivery ran', $html);
        self::assertStringContainsString('12 failed — last error: HTTP 403 (check the API secret)', $html);
        self::assertStringContainsString(
            '<span data-role="marker-action">12 failed at website ' . $websiteId . '</span>',
            $html
        );
        self::assertStringNotContainsString('_inherit', $html);
    }

    /**
     * At the default scope the status covers every website, and without failures neither the failed line nor the
     * actions show
     *
     * @return void
     */
    public function testDeliveryStatusAtDefaultScopeWithoutFailures(): void
    {
        $status = new FixedDeliveryStatus(new DeliveryReport(null, 0, 0, 5));
        $this->testObjectManager()->addSharedInstance($status, OutboxDeliveryStatus::class);
        $element = $this->element($this->settingsForm(self::SECTION), self::DELIVERY_STATUS);
        $renderer = Bootstrap::getObjectManager()->create(
            DeliveryStatus::class,
            ['failedActions' => ['marker' => new MarkerAction()]]
        );
        self::assertInstanceOf(DeliveryStatus::class, $renderer);
        $html = (string) $renderer->render($element);

        self::assertSame([null], $status->askedWebsites);
        self::assertStringContainsString('Nothing is waiting to be sent · Delivery has not run yet', $html);
        self::assertStringNotContainsString('data-role="failed-line"', $html);
        self::assertStringNotContainsString('marker-action', $html);
    }

    /**
     * The consent notice is a full-width warning; the debug comment is shown as a warning
     *
     * @return void
     */
    public function testNoticeAndWarningComment(): void
    {
        $form = $this->settingsForm(self::SECTION);
        $notice = $this->rowHtml($form, self::GROUP . '_consent_consent_notice');
        $debug = $this->rowHtml($form, self::GROUP . '_delivery_debug');

        self::assertStringContainsString('<td colspan="4">', $notice);
        self::assertStringContainsString('class="hryvinskyi-datalayer-notice" role="note"', $notice);
        self::assertStringContainsString('Consent is not checked yet', $notice);
        self::assertStringContainsString('class="note hryvinskyi-datalayer-note--warning"', $debug);
        self::assertStringContainsString('Events are NOT recorded in GA4 while this is on', $debug);
    }

    /**
     * The event settings learn from this module whether server routes are delivered for a website
     *
     * @return void
     */
    public function testEventSettingsLearnTheDeliveryState(): void
    {
        $state = $this->service(ServerDeliveryStateInterface::class);
        self::assertInstanceOf(ServerDeliveryState::class, $state);
        $websiteId = $this->mainWebsiteId();
        self::assertFalse($state->isEnabled($websiteId));

        $this->saveSettings(
            self::SECTION,
            ['server' => ['groups' => ['delivery' => ['fields' => ['enabled' => ['value' => '1']]]]]],
            $websiteId
        );

        self::assertTrue($state->isEnabled($websiteId));
        self::assertFalse($state->isEnabled(null));
        self::assertSame('hryvinskyi_datalayer/server', $state->getSettingsGroupPath());
    }

    /**
     * The events table links to this group while a route goes to the server and delivery is off
     *
     * @return void
     */
    public function testEventsTableLinksHere(): void
    {
        $websiteId = $this->mainWebsiteId();
        $html = $this->rowHtml($this->settingsForm(self::SECTION, $websiteId), 'hryvinskyi_datalayer_events_disabled');
        $links = $this->elements($this->parsed($html), '//*[@data-role="warning-server"]//a');

        self::assertCount(1, $links);
        self::assertMatchesRegularExpression(
            '#/section/' . self::SECTION . '/website/' . $websiteId . '/.*\#' . self::GROUP . '-link$#',
            $links[0]->getAttribute('href')
        );
        self::assertStringContainsString('"serverDeliveryEnabled":false', $html);
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

    /**
     * Get one element of a settings form
     *
     * @param Form $form
     * @param string $elementId
     * @return AbstractElement
     */
    private function element(Form $form, string $elementId): AbstractElement
    {
        $element = $form->getForm()->getElement($elementId);
        self::assertInstanceOf(AbstractElement::class, $element);

        return $element;
    }
}
