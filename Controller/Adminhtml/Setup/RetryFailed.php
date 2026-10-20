<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideAdminUi\Controller\Adminhtml\Setup;

use Hryvinskyi\DataLayerAdminUi\Api\Setup\Http\JsonAnswerInterface;
use Hryvinskyi\DataLayerAdminUi\Api\Setup\Http\JsonBodyInterface;
use Hryvinskyi\DataLayerServerSide\Api\DeliveryStatusInterface;
use Hryvinskyi\DataLayerServerSideAdminUi\Api\DeliveryAccessInterface;
use Hryvinskyi\DataLayerServerSideAdminUi\Api\DeliveryStatusViewInterface;
use Hryvinskyi\DataLayerServerSideAdminUi\Api\RetryFailedEventsInterface;
use Hryvinskyi\DataLayerServerSideAdminUi\Api\Setup\ScopeWebsiteInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;

/**
 * Queues the failed server events of a setup wizard scope again and answers with the outcome and where delivery now
 * stands
 *
 * The body is `{"scope": scope id}`; the admin form key travels in the query string. A website queues its own
 * events, a store view its website's, the default scope every website's. The answer is
 * `{done: bool, message: text, status: {pending, sent, failed, progress, failedLine}}`; a request that cannot be read
 * answers 400 `{"error": reason}`, and a failure of the server 500 with a generic sentence, logged.
 */
class RetryFailed extends Action implements HttpPostActionInterface
{
    /**
     * Access rule of the delivery actions
     */
    public const ADMIN_RESOURCE = DeliveryAccessInterface::RESOURCE;

    /**
     * @param Context $context
     * @param JsonBodyInterface $jsonBody
     * @param ScopeWebsiteInterface $scopeWebsite
     * @param RetryFailedEventsInterface $retry
     * @param DeliveryStatusInterface $deliveryStatus
     * @param DeliveryStatusViewInterface $statusView
     * @param JsonAnswerInterface $jsonAnswer
     */
    public function __construct(
        Context $context,
        private readonly JsonBodyInterface $jsonBody,
        private readonly ScopeWebsiteInterface $scopeWebsite,
        private readonly RetryFailedEventsInterface $retry,
        private readonly DeliveryStatusInterface $deliveryStatus,
        private readonly DeliveryStatusViewInterface $statusView,
        private readonly JsonAnswerInterface $jsonAnswer
    ) {
        parent::__construct($context);
    }

    /**
     * Queue the failed events again and answer with the outcome and the scope's delivery status
     *
     * @return Json
     */
    public function execute(): Json
    {
        return $this->jsonAnswer->answer('retryFailed', function (): array {
            $websiteId = $this->scopeWebsite->websiteOf($this->jsonBody->read($this->getRequest())['scope'] ?? null);
            $scope = $websiteId === 0 ? null : $websiteId;
            $outcome = $this->retry->retry($scope);

            return [
                'done' => $outcome['done'],
                'message' => $outcome['message'],
                'status' => $this->statusView->describe($this->deliveryStatus->forWebsite($scope)),
            ];
        });
    }
}
