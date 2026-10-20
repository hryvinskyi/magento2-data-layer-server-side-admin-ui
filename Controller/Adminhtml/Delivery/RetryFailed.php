<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideAdminUi\Controller\Adminhtml\Delivery;

use Hryvinskyi\DataLayerAdminUi\Api\Form\SettingsGroupLinkInterface;
use Hryvinskyi\DataLayerServerSideAdminUi\Api\DeliveryAccessInterface;
use Hryvinskyi\DataLayerServerSideAdminUi\Api\DeliveryActionNoticesInterface;
use Hryvinskyi\DataLayerServerSideAdminUi\Api\RetryFailedEventsInterface;
use Hryvinskyi\DataLayerServerSideAdminUi\Api\WebsiteParameterInterface;
use Hryvinskyi\DataLayerServerSideAdminUi\Model\ServerDeliveryState;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Framework\App\Action\HttpPostActionInterface;

/**
 * Queues the failed server events of the scope the settings page shows again, then returns to that page, at the
 * server-side group
 *
 * At a website only that website's events; at the default scope every website's. The administrator is told how many
 * events are queued again, or that it failed; the reason of a failure goes to the log, never to the page.
 */
class RetryFailed extends Action implements HttpPostActionInterface
{
    /**
     * Access rule of the delivery actions
     */
    public const ADMIN_RESOURCE = DeliveryAccessInterface::RESOURCE;

    /**
     * @param Context $context
     * @param RetryFailedEventsInterface $retry
     * @param WebsiteParameterInterface $websiteParameter
     * @param DeliveryActionNoticesInterface $notices
     * @param SettingsGroupLinkInterface $settingsLink
     */
    public function __construct(
        Context $context,
        private readonly RetryFailedEventsInterface $retry,
        private readonly WebsiteParameterInterface $websiteParameter,
        private readonly DeliveryActionNoticesInterface $notices,
        private readonly SettingsGroupLinkInterface $settingsLink
    ) {
        parent::__construct($context);
    }

    /**
     * Queue the failed events again and return to the settings
     *
     * @return Redirect
     */
    public function execute(): Redirect
    {
        /** @var Redirect $redirect */
        $redirect = $this->resultRedirectFactory->create();
        try {
            $websiteId = $this->websiteParameter->read($this->getRequest());
        } catch (\InvalidArgumentException) {
            $this->messageManager->addErrorMessage((string) $this->notices->unknownScope());

            return $this->toSettings($redirect, null);
        }

        $outcome = $this->retry->retry($websiteId);
        if ($outcome['done']) {
            $this->messageManager->addSuccessMessage($outcome['message']);
        } else {
            $this->messageManager->addErrorMessage($outcome['message']);
        }

        return $this->toSettings($redirect, $websiteId);
    }

    /**
     * Send the administrator back to the server-side settings of a scope
     *
     * @param Redirect $redirect
     * @param int|null $websiteId Null for the default scope
     * @return Redirect
     */
    private function toSettings(Redirect $redirect, ?int $websiteId): Redirect
    {
        $redirect->setPath(
            SettingsGroupLinkInterface::ROUTE,
            $this->settingsLink->routeParams(ServerDeliveryState::SETTINGS_GROUP, $websiteId)
        );

        return $redirect;
    }
}
