<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideAdminUi\Model\Setup;

use Hryvinskyi\DataLayerAdminUi\Api\Setup\Data\ScopeIdInterfaceFactory;
use Hryvinskyi\DataLayerAdminUi\Api\Setup\ScopeTreeInterface;
use Hryvinskyi\DataLayerAdminUi\Api\Setup\SettingDescriptorsInterface;
use Hryvinskyi\DataLayerServerSide\Api\DeliveryStatusInterface;
use Hryvinskyi\DataLayerServerSideAdminUi\Api\DeliveryAccessInterface;
use Hryvinskyi\DataLayerServerSideAdminUi\Api\DeliveryStatusViewInterface;
use Hryvinskyi\DataLayerServerSideAdminUi\Api\Setup\ServerStepDataInterface;
use Hryvinskyi\DataLayerServerSideAdminUi\Api\TestableTransportsInterface;
use Magento\Backend\Model\UrlInterface;

/**
 * Builds the server-side step's data from the admin's URLs and authorization, the testable transports, the
 * settings each transport shows (`transportFields` in `di.xml`), the glyph of each transport (`transportIcons`) and
 * the delivery status of the default scope and of every website
 *
 * Each transport's settings must be settings of the server-side step, checked when the list is built, so a mistyped
 * key fails on the first page load rather than leaving a field out.
 *
 * @phpstan-import-type StatusView from DeliveryStatusViewInterface
 */
class ServerStepData implements ServerStepDataInterface
{
    private const TEST_ROUTE = 'hryvinskyi_datalayer_server/setup/testEvent';
    private const RETRY_ROUTE = 'hryvinskyi_datalayer_server/setup/retryFailed';
    private const WEBSITE = 'website';

    /**
     * @var array<string, list<string>>
     */
    private readonly array $transportFields;

    /**
     * @var array<string, string>
     */
    private readonly array $transportIcons;

    /**
     * @param UrlInterface $urlBuilder
     * @param DeliveryAccessInterface $access
     * @param TestableTransportsInterface $testableTransports
     * @param DeliveryStatusInterface $deliveryStatus
     * @param DeliveryStatusViewInterface $statusView
     * @param ScopeTreeInterface $scopeTree
     * @param ScopeIdInterfaceFactory $scopeIdFactory
     * @param SettingDescriptorsInterface $descriptors
     * @param array<mixed> $transportFields The setting keys each transport shows, in order, keyed by transport code
     * @param array<mixed> $transportIcons The glyph name of each transport, keyed by transport code
     * @throws \InvalidArgumentException When a list is not of the expected shape, or names a setting that is not one
     *     of the server-side step
     */
    public function __construct(
        private readonly UrlInterface $urlBuilder,
        private readonly DeliveryAccessInterface $access,
        private readonly TestableTransportsInterface $testableTransports,
        private readonly DeliveryStatusInterface $deliveryStatus,
        private readonly DeliveryStatusViewInterface $statusView,
        private readonly ScopeTreeInterface $scopeTree,
        private readonly ScopeIdInterfaceFactory $scopeIdFactory,
        SettingDescriptorsInterface $descriptors,
        array $transportFields = [],
        array $transportIcons = []
    ) {
        $fields = [];
        foreach ($transportFields as $code => $keys) {
            if (!is_string($code) || !is_array($keys)) {
                throw new \InvalidArgumentException(
                    sprintf('The settings of transport "%s" must be a list keyed by its code.', (string) $code)
                );
            }
            $fields[$code] = [];
            foreach ($keys as $key) {
                if (!is_string($key) || $descriptors->get($key)->getStep() !== self::STEP) {
                    throw new \InvalidArgumentException(sprintf(
                        'Transport "%s" may show only settings of the server-side step.',
                        $code
                    ));
                }
                $fields[$code][] = $key;
            }
        }
        $this->transportFields = $fields;
        $icons = [];
        foreach ($transportIcons as $code => $icon) {
            if (!is_string($code) || !is_string($icon) || $icon === '') {
                throw new \InvalidArgumentException(
                    sprintf('The glyph of transport "%s" must be a name keyed by its code.', (string) $code)
                );
            }
            $icons[$code] = $icon;
        }
        $this->transportIcons = $icons;
    }

    /**
     * @inheritDoc
     */
    public function get(): array
    {
        return [
            'testUrl' => $this->urlBuilder->getUrl(self::TEST_ROUTE),
            'retryUrl' => $this->urlBuilder->getUrl(self::RETRY_ROUTE),
            'canAct' => $this->access->isAllowed(),
            'testableTransports' => $this->testableTransports->getCodes(),
            'transportFields' => $this->transportFields === [] ? (object) [] : $this->transportFields,
            'transportIcons' => $this->transportIcons === [] ? (object) [] : $this->transportIcons,
            'status' => $this->status(),
        ];
    }

    /**
     * Describe where delivery stands at the default scope and at each website, read for all of them at once
     *
     * @return array<string, StatusView> Keyed by scope id
     */
    private function status(): array
    {
        $websites = [];
        foreach ($this->scopeTree->getScopes() as $scope) {
            if ($scope['type'] === self::WEBSITE) {
                $websites[$scope['id']] = $this->scopeIdFactory->create(['value' => $scope['id']])->getId();
            }
        }
        $overview = $this->deliveryStatus->overview(array_values($websites));

        $status = [ScopeTreeInterface::DEFAULT_SCOPE => $this->statusView->describe($overview['all'])];
        foreach ($websites as $scopeId => $websiteId) {
            $report = $overview['websites'][$websiteId] ?? $this->deliveryStatus->forWebsite($websiteId);
            $status[$scopeId] = $this->statusView->describe($report);
        }

        return $status;
    }
}
