<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideAdminUi\Block\Adminhtml\System\Config;

use Hryvinskyi\DataLayerAdminUi\Block\Adminhtml\System\Config\ReadOnlyField;
use Hryvinskyi\DataLayerServerSide\Api\DeliveryStatusInterface;
use Hryvinskyi\DataLayerServerSideAdminUi\Api\DeliveryStatusActionInterface;
use Hryvinskyi\DataLayerServerSideAdminUi\Api\DeliveryStatusViewInterface;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use Magento\Store\Model\ScopeInterface;

/**
 * Shows where the delivery of server events stands for the website being edited, or for every website together at
 * the default scope: pending, sent and failed counts, the oldest waiting event, the last run, and the latest failure
 *
 * Actions on the failed events (registered in the `failedActions` argument) are shown beside the failed line.
 */
class DeliveryStatus extends ReadOnlyField
{
    /**
     * @var string
     */
    protected $_template = 'Hryvinskyi_DataLayerServerSideAdminUi::system/config/delivery-status.phtml';

    /**
     * @var list<DeliveryStatusActionInterface>
     */
    private readonly array $failedActions;

    /**
     * @param Context $context
     * @param DeliveryStatusInterface $deliveryStatus
     * @param DeliveryStatusViewInterface $statusView
     * @param SecureHtmlRenderer $secureRenderer
     * @param array<mixed> $failedActions Actions offered beside the failed events, in order
     * @param array<string,mixed> $data
     * @throws \InvalidArgumentException When an action is not one
     */
    public function __construct(
        Context $context,
        private readonly DeliveryStatusInterface $deliveryStatus,
        private readonly DeliveryStatusViewInterface $statusView,
        SecureHtmlRenderer $secureRenderer,
        array $failedActions = [],
        array $data = []
    ) {
        $actions = [];
        foreach ($failedActions as $name => $action) {
            if (!$action instanceof DeliveryStatusActionInterface) {
                throw new \InvalidArgumentException(sprintf(
                    'The delivery status action "%s" must implement %s.',
                    (string) $name,
                    DeliveryStatusActionInterface::class
                ));
            }
            $actions[] = $action;
        }
        $this->failedActions = $actions;
        parent::__construct($context, $data, $secureRenderer);
    }

    /**
     * Render the status through the template
     *
     * @param AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        $websiteId = $element->getData('scope') === ScopeInterface::SCOPE_WEBSITES
            && is_numeric($element->getData('scope_id'))
            ? (int) $element->getData('scope_id')
            : null;
        $report = $this->deliveryStatus->forWebsite($websiteId);
        $view = $this->statusView->describe($report);
        $actions = '';
        if ($view['failed'] > 0) {
            foreach ($this->failedActions as $action) {
                $actions .= $action->render($report, $websiteId);
            }
        }
        $this->assign('view', ['id' => $element->getHtmlId(), 'actions' => $actions] + $view);

        return $this->toHtml();
    }
}
