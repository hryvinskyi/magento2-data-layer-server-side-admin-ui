<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideAdminUi\Controller\Adminhtml\Delivery;

use Hryvinskyi\DataLayerServerSideAdminUi\Api\DeliveryAccessInterface;
use Hryvinskyi\DataLayerServerSideAdminUi\Api\DeliveryActionNoticesInterface;
use Hryvinskyi\DataLayerServerSideAdminUi\Api\TestEventSenderInterface;
use Hryvinskyi\DataLayerServerSideAdminUi\Api\WebsiteParameterInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;

/**
 * Sends the test event with the saved settings of the scope the settings page shows, and answers with the outcome
 *
 * The answer is `{valid: bool, messages: string[], error: string}`; the default scope tests the default settings.
 */
class TestEvent extends Action implements HttpPostActionInterface
{
    /**
     * Access rule of the delivery actions
     */
    public const ADMIN_RESOURCE = DeliveryAccessInterface::RESOURCE;

    /**
     * @param Context $context
     * @param TestEventSenderInterface $sender
     * @param WebsiteParameterInterface $websiteParameter
     * @param DeliveryActionNoticesInterface $notices
     * @param JsonFactory $jsonFactory
     */
    public function __construct(
        Context $context,
        private readonly TestEventSenderInterface $sender,
        private readonly WebsiteParameterInterface $websiteParameter,
        private readonly DeliveryActionNoticesInterface $notices,
        private readonly JsonFactory $jsonFactory
    ) {
        parent::__construct($context);
    }

    /**
     * Send the test event and answer with what the validation endpoint made of it
     *
     * @return Json
     */
    public function execute(): Json
    {
        $json = $this->jsonFactory->create();
        try {
            $websiteId = $this->websiteParameter->read($this->getRequest()) ?? 0;
        } catch (\InvalidArgumentException) {
            return $json->setData([
                'valid' => false,
                'messages' => [],
                'error' => (string) $this->notices->unknownScope(),
            ]);
        }
        $result = $this->sender->send($websiteId);

        return $json->setData([
            'valid' => $result->isValid(),
            'messages' => $result->getMessages(),
            'error' => $result->getError(),
        ]);
    }
}
