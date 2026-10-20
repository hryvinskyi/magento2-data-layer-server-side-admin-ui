<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideAdminUi\Block\Adminhtml\System\Config;

use Hryvinskyi\DataLayerServerSideAdminUi\Api\DeliveryAccessInterface;
use Hryvinskyi\DataLayerServerSideAdminUi\Api\DeliveryActionsScriptInterface;
use Hryvinskyi\DataLayerAdminUi\Block\Adminhtml\System\Config\ReadOnlyField;
use Hryvinskyi\DataLayerServerSideAdminUi\Api\TestEventSenderInterface;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Serialize\Serializer\JsonHexTag;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use Magento\Store\Model\ScopeInterface;

/**
 * The "Send test event" row: a button that sends one event to Google's validation endpoint and shows the reply
 *
 * The test uses the saved settings of the website being edited, or the default ones at the default scope. The row is
 * left out for an administrator the test endpoint does not let in. While the saved transport has no validation
 * endpoint the button is disabled with a note saying why. The script gets its wording from here, translated, and
 * waits a little longer than the transport's own timeouts before it gives up.
 */
class TestEvent extends ReadOnlyField
{
    private const ROUTE = 'hryvinskyi_datalayer_server/delivery/testEvent';

    /**
     * @var string
     */
    protected $_template = 'Hryvinskyi_DataLayerServerSideAdminUi::system/config/test-event.phtml';

    /**
     * @param Context $context
     * @param TestEventSenderInterface $sender
     * @param DeliveryAccessInterface $access
     * @param DeliveryActionsScriptInterface $script
     * @param JsonHexTag $json
     * @param SecureHtmlRenderer $secureRenderer
     * @param int $timeoutSeconds How long the script waits for the reply
     * @param array<string,mixed> $data
     */
    public function __construct(
        Context $context,
        private readonly TestEventSenderInterface $sender,
        private readonly DeliveryAccessInterface $access,
        private readonly DeliveryActionsScriptInterface $script,
        private readonly JsonHexTag $json,
        SecureHtmlRenderer $secureRenderer,
        private readonly int $timeoutSeconds = 20,
        array $data = []
    ) {
        parent::__construct($context, $data, $secureRenderer);
    }

    /**
     * Render the row, or nothing for an administrator who may not send the test
     *
     * @param AbstractElement $element
     * @return string
     */
    public function render(AbstractElement $element)
    {
        return $this->access->isAllowed() ? parent::render($element) : '';
    }

    /**
     * Render the button, the result area and the script through the template
     *
     * @param AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        $websiteId = $element->getData('scope') === ScopeInterface::SCOPE_WEBSITES
            && is_numeric($element->getData('scope_id'))
            ? (int) $element->getData('scope_id')
            : 0;
        $testable = $this->sender->canTest($websiteId);
        $this->assign('view', [
            'id' => $element->getHtmlId(),
            'testable' => $testable,
            'config' => $this->json->serialize([
                'url' => $this->getUrl(self::ROUTE),
                'formKey' => $this->getFormKey(),
                'website' => $websiteId,
                'testable' => $testable,
                'timeoutSeconds' => $this->timeoutSeconds,
                'wording' => [
                    'sending' => (string) __('Sending…'),
                    'validHeading' => (string) __('Valid'),
                    'validText' => (string) __('Google returned no validation messages.'),
                    'messageHeading' => (string) __('Google returned 1 validation message:'),
                    'messagesHeading' => (string) __('Google returned %1 validation messages:', '{count}'),
                    'failureHeading' => (string) __('Not checked'),
                    'timeout' => (string) __('No answer within %1 seconds. Try again later.', $this->timeoutSeconds),
                    'unreadable' => (string) __('The answer could not be read. Reload the page and try again.'),
                ],
            ]),
            'script' => $this->script->render(),
        ]);

        return $this->toHtml();
    }
}
