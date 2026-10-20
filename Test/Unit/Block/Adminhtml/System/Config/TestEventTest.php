<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideAdminUi\Test\Unit\Block\Adminhtml\System\Config;

use Hryvinskyi\DataLayerServerSideAdminUi\Api\DeliveryActionsScriptInterface;
use Hryvinskyi\DataLayerServerSideAdminUi\Api\TestEventSenderInterface;
use Hryvinskyi\DataLayerServerSideAdminUi\Block\Adminhtml\System\Config\TestEvent;
use Hryvinskyi\DataLayerServerSideAdminUi\Model\Delivery\DeliveryAccess;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\App\ObjectManager as AppObjectManager;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Serialize\Serializer\JsonHexTag;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use PHPUnit\Framework\TestCase;

/**
 * The "Send test event" row is left out for an administrator the test endpoint does not let in
 */
class TestEventTest extends TestCase
{
    /**
     * Without the delivery actions' access rule the row renders nothing and asks nothing of the sender
     *
     * @return void
     */
    public function testRowIsLeftOutWithoutAccess(): void
    {
        $stubObjectManager = $this->createMock(ObjectManagerInterface::class);
        $stubObjectManager->method('get')->willReturnCallback(function (string $type): object {
            /** @var class-string $type */
            return $this->createMock($type);
        });
        AppObjectManager::setInstance($stubObjectManager);

        $asked = [];
        $authorization = $this->createMock(AuthorizationInterface::class);
        $authorization->method('isAllowed')->willReturnCallback(function (string $resource) use (&$asked): bool {
            $asked[] = $resource;

            return false;
        });
        $sender = $this->createMock(TestEventSenderInterface::class);
        $sender->expects(self::never())->method('canTest');
        $sender->expects(self::never())->method('send');

        $block = new TestEvent(
            $this->createMock(Context::class),
            $sender,
            new DeliveryAccess($authorization),
            $this->createMock(DeliveryActionsScriptInterface::class),
            new JsonHexTag(),
            $this->createMock(SecureHtmlRenderer::class)
        );

        self::assertSame('', $block->render($this->createMock(AbstractElement::class)));
        self::assertSame(['Hryvinskyi_DataLayerServerSideAdminUi::delivery'], $asked);
    }
}
