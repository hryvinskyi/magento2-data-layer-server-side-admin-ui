<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideAdminUi\Controller\Adminhtml\Setup;

use Hryvinskyi\DataLayer\Api\Config\DraftSettingsInterface;
use Hryvinskyi\DataLayerAdminUi\Api\Setup\Http\JsonAnswerInterface;
use Hryvinskyi\DataLayerServerSideAdminUi\Api\Data\TestResultInterface;
use Hryvinskyi\DataLayerServerSideAdminUi\Api\DeliveryAccessInterface;
use Hryvinskyi\DataLayerServerSideAdminUi\Api\Setup\TestEventRequestReaderInterface;
use Hryvinskyi\DataLayerServerSideAdminUi\Api\TestEventSenderInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;

/**
 * Sends the test event with the setup wizard's unsaved server-side settings of a scope, and answers with the outcome
 *
 * The body is `{"scope": scope id, "settings": object}`; the admin form key travels in the query string, where the
 * admin checks it before this runs. A store view tests its website's settings, the default scope the default ones.
 * Nothing is saved: the settings are read as drafted only while the event is sent. The answer is
 * `{valid: bool, messages: string[], error: string}`; a request that cannot be read answers 400 `{"error": reason}`,
 * and a failure of the server 500 with a generic sentence, logged.
 */
class TestEvent extends Action implements HttpPostActionInterface
{
    /**
     * Access rule of the delivery actions
     */
    public const ADMIN_RESOURCE = DeliveryAccessInterface::RESOURCE;

    /**
     * @param Context $context
     * @param TestEventRequestReaderInterface $requestReader
     * @param DraftSettingsInterface $draftSettings
     * @param TestEventSenderInterface $sender
     * @param JsonAnswerInterface $jsonAnswer
     */
    public function __construct(
        Context $context,
        private readonly TestEventRequestReaderInterface $requestReader,
        private readonly DraftSettingsInterface $draftSettings,
        private readonly TestEventSenderInterface $sender,
        private readonly JsonAnswerInterface $jsonAnswer
    ) {
        parent::__construct($context);
    }

    /**
     * Send the test event against the drafted settings and answer with what the validation endpoint made of it
     *
     * @return Json
     */
    public function execute(): Json
    {
        return $this->jsonAnswer->answer('testEvent', function (): array {
            $request = $this->requestReader->read($this->getRequest());
            $websiteId = $request['websiteId'];
            $result = $this->draftSettings->withDraft(
                $request['draft'],
                fn (): TestResultInterface => $this->sender->send($websiteId)
            );

            return [
                'valid' => $result->isValid(),
                'messages' => $result->getMessages(),
                'error' => $result->getError(),
            ];
        });
    }
}
