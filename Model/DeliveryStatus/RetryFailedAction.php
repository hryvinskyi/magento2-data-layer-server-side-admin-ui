<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideAdminUi\Model\DeliveryStatus;

use Hryvinskyi\DataLayerServerSideAdminUi\Api\DeliveryAccessInterface;
use Hryvinskyi\DataLayerServerSideAdminUi\Api\DeliveryActionsScriptInterface;
use Hryvinskyi\DataLayerServerSide\Api\Data\DeliveryReportInterface;
use Hryvinskyi\DataLayerServerSideAdminUi\Api\DeliveryStatusActionInterface;
use Magento\Backend\Block\TemplateFactory;
use Magento\Backend\Model\UrlInterface;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Serialize\Serializer\JsonHexTag;

/**
 * The "Retry failed" button beside the failed events: it queues the scope's failed events again
 *
 * Shown only while the scope has failed events and only to an administrator the retry endpoint lets in. The settings
 * page is itself a form, so the button cannot carry a form of its own: its script posts the form key and the website
 * to the endpoint, and the button stays disabled until the script runs.
 */
class RetryFailedAction implements DeliveryStatusActionInterface
{
    private const TEMPLATE = 'Hryvinskyi_DataLayerServerSideAdminUi::system/config/retry-failed.phtml';
    private const ROUTE = 'hryvinskyi_datalayer_server/delivery/retryFailed';

    /**
     * @param DeliveryAccessInterface $access
     * @param TemplateFactory $templates
     * @param UrlInterface $urlBuilder
     * @param FormKey $formKey
     * @param DeliveryActionsScriptInterface $script
     * @param JsonHexTag $json
     */
    public function __construct(
        private readonly DeliveryAccessInterface $access,
        private readonly TemplateFactory $templates,
        private readonly UrlInterface $urlBuilder,
        private readonly FormKey $formKey,
        private readonly DeliveryActionsScriptInterface $script,
        private readonly JsonHexTag $json
    ) {
    }

    /**
     * @inheritDoc
     */
    public function render(DeliveryReportInterface $report, ?int $websiteId): string
    {
        if ($report->getFailedCount() <= 0 || !$this->access->isAllowed()) {
            return '';
        }

        $block = $this->templates->create(['data' => ['template' => self::TEMPLATE]]);
        $block->assign('view', [
            'config' => $this->json->serialize([
                'url' => $this->urlBuilder->getUrl(self::ROUTE),
                'formKey' => $this->formKey->getFormKey(),
                'website' => $websiteId ?? 0,
            ]),
            'script' => $this->script->render(),
        ]);

        return $block->toHtml();
    }
}
