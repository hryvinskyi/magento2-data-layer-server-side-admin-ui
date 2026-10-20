<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideAdminUi\Test\Unit\Controller\Adminhtml\Delivery;

use Hryvinskyi\DataLayerServerSideAdminUi\Api\TestEventSenderInterface;
use Hryvinskyi\DataLayerServerSideAdminUi\Controller\Adminhtml\Delivery\TestEvent;
use Hryvinskyi\DataLayerServerSideAdminUi\Model\Data\TestResult;
use Hryvinskyi\DataLayerServerSideAdminUi\Model\Delivery\DeliveryActionNotices;
use Hryvinskyi\DataLayerServerSideAdminUi\Model\Delivery\WebsiteParameter;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use PHPUnit\Framework\TestCase;

/**
 * The test endpoint reads the scope, delegates to the sender, and answers with the result as JSON
 */
class TestEventTest extends TestCase
{
    /**
     * @var list<int>
     */
    private array $tested = [];

    /**
     * @var list<mixed>
     */
    private array $answers = [];

    /**
     * Each outcome is answered as `{valid, messages, error}`, for the website asked, 0 at the default scope
     *
     * @return void
     */
    public function testAnswersWithTheOutcome(): void
    {
        $this->controller('4', new TestResult(true))->execute();
        $this->controller(null, new TestResult(false, ['NAME_INVALID · name: too long']))->execute();
        $this->controller('', new TestResult(false, [], 'HTTP 403'))->execute();

        self::assertSame([4, 0, 0], $this->tested);
        self::assertSame([
            ['valid' => true, 'messages' => [], 'error' => ''],
            ['valid' => false, 'messages' => ['NAME_INVALID · name: too long'], 'error' => ''],
            ['valid' => false, 'messages' => [], 'error' => 'HTTP 403'],
        ], $this->answers);
    }

    /**
     * A scope that is not a website sends nothing and answers with an error
     *
     * @return void
     */
    public function testUnknownScopeSendsNothing(): void
    {
        $this->controller('-2', new TestResult(true))->execute();

        self::assertSame([], $this->tested);
        self::assertSame([[
            'valid' => false,
            'messages' => [],
            'error' => 'The scope of this action is not a website. Open the settings again and retry.',
        ]], $this->answers);
    }

    /**
     * Build the controller over a request carrying the website parameter
     *
     * @param string|null $website
     * @param TestResult $result What the sender answers
     * @return TestEvent
     */
    private function controller(?string $website, TestResult $result): TestEvent
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('getParam')->willReturnCallback(
            static fn (string $name): ?string => $name === 'website' ? $website : null
        );
        $context = $this->createMock(Context::class);
        $context->method('getRequest')->willReturn($request);

        $json = $this->createMock(Json::class);
        $json->method('setData')->willReturnCallback(function (mixed $data) use ($json): Json {
            $this->answers[] = $data;

            return $json;
        });
        $jsonFactory = $this->createMock(JsonFactory::class);
        $jsonFactory->method('create')->willReturn($json);

        $sender = $this->createMock(TestEventSenderInterface::class);
        $sender->method('send')->willReturnCallback(function (int $websiteId) use ($result): TestResult {
            $this->tested[] = $websiteId;

            return $result;
        });

        return new TestEvent($context, $sender, new WebsiteParameter(), new DeliveryActionNotices(), $jsonFactory);
    }
}
