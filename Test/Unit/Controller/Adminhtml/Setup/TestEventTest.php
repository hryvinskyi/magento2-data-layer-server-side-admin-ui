<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideAdminUi\Test\Unit\Controller\Adminhtml\Setup;

use Hryvinskyi\DataLayerAdminUi\Api\Setup\InvalidSetupRequestException;
use Hryvinskyi\DataLayerAdminUi\Model\Setup\Http\JsonAnswer;
use Hryvinskyi\DataLayerAdminUi\Test\Unit\Model\Setup\RecordingLogger;
use Hryvinskyi\DataLayerServerSideAdminUi\Api\Setup\TestEventRequestReaderInterface;
use Hryvinskyi\DataLayerServerSideAdminUi\Api\TestEventSenderInterface;
use Hryvinskyi\DataLayerServerSideAdminUi\Controller\Adminhtml\Setup\TestEvent;
use Hryvinskyi\DataLayerServerSideAdminUi\Model\Data\TestResult;
use Hryvinskyi\DataLayerServerSideAdminUi\Test\Unit\Fake\RecordingDraftSettings;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Phrase;
use PHPUnit\Framework\TestCase;

/**
 * The wizard's test endpoint sends the test for the scope's website while the unsaved settings are drafted, and
 * answers with the outcome; a request it cannot read answers 400 and sends nothing; a failure of the server answers
 * 500 with a generic sentence and is logged
 */
class TestEventTest extends TestCase
{
    /**
     * What the endpoint logged
     *
     * @var RecordingLogger
     */
    private RecordingLogger $logger;

    /**
     * Start every test with nothing logged
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->logger = new RecordingLogger();
    }

    /**
     * @var list<mixed>
     */
    private array $answers = [];

    /**
     * @var list<int>
     */
    private array $codes = [];

    /**
     * @var list<array{websiteId: int, draft: array<string, string>|null}>
     */
    private array $sent = [];

    /**
     * The test runs inside the draft, for the website the request names
     *
     * @return void
     */
    public function testSendsWithTheDraft(): void
    {
        $draft = ['hryvinskyi_datalayer_server/ga4/measurement_id' => 'G-DRAFT'];
        $draftSettings = new RecordingDraftSettings();

        $this->controller(['websiteId' => 3, 'draft' => $draft], $draftSettings)->execute();

        self::assertSame([['websiteId' => 3, 'draft' => $draft]], $this->sent);
        self::assertNull($draftSettings->active);
        self::assertSame([['valid' => false, 'messages' => ['NAME_INVALID · x'], 'error' => '']], $this->answers);
    }

    /**
     * A request that cannot be read answers 400 with the reason
     *
     * @return void
     */
    public function testRefusedRequestAnswers400(): void
    {
        $draftSettings = new RecordingDraftSettings();

        $refusal = new InvalidSetupRequestException(new Phrase('"%1" is not a scope of this store.', ['shop:1']));
        $this->controller($refusal, $draftSettings)->execute();

        self::assertSame([], $this->sent);
        self::assertSame([], $draftSettings->drafts);
        self::assertSame([400], $this->codes);
        self::assertSame([['error' => '"shop:1" is not a scope of this store.']], $this->answers);
        self::assertSame([], $this->logger->records);
    }

    /**
     * Anything else that goes wrong, a framework's own invalid argument included, answers 500 with a generic sentence
     * and is logged; the draft is closed
     *
     * @return void
     */
    public function testFailureAnswers500AndIsLogged(): void
    {
        $draftSettings = new RecordingDraftSettings();

        $this->controller(new \InvalidArgumentException('Unknown column in field list'), $draftSettings)->execute();

        self::assertSame([500], $this->codes);
        self::assertSame(
            [['error' => 'The request could not be completed. Details are in the log.']],
            $this->answers
        );
        self::assertSame('testEvent', $this->logger->records[0]['context']['endpoint'] ?? null);
        self::assertNull($draftSettings->active);
    }

    /**
     * Build the controller over a reader answering as given
     *
     * @param array{websiteId: int, draft: array<string, string>}|\Throwable $request
     * @param RecordingDraftSettings $draftSettings
     * @return TestEvent
     */
    private function controller(
        array|\Throwable $request,
        RecordingDraftSettings $draftSettings
    ): TestEvent {
        $context = $this->createStub(Context::class);
        $context->method('getRequest')->willReturn($this->createStub(RequestInterface::class));
        $reader = $this->createStub(TestEventRequestReaderInterface::class);
        if ($request instanceof \Throwable) {
            $reader->method('read')->willThrowException($request);
        } else {
            $reader->method('read')->willReturn($request);
        }
        $sender = $this->createStub(TestEventSenderInterface::class);
        $sender->method('send')->willReturnCallback(function (int $websiteId) use ($draftSettings): TestResult {
            $this->sent[] = ['websiteId' => $websiteId, 'draft' => $draftSettings->active];

            return new TestResult(false, ['NAME_INVALID · x']);
        });

        return new TestEvent(
            $context,
            $reader,
            $draftSettings,
            $sender,
            new JsonAnswer($this->jsonFactory(), $this->logger)
        );
    }

    /**
     * Build a JSON result factory that records each answer and status
     *
     * @return JsonFactory
     */
    private function jsonFactory(): JsonFactory
    {
        $json = $this->createStub(Json::class);
        $json->method('setData')->willReturnCallback(function (mixed $data) use ($json): Json {
            $this->answers[] = $data;

            return $json;
        });
        $json->method('setHttpResponseCode')->willReturnCallback(function (int $code) use ($json): Json {
            $this->codes[] = $code;

            return $json;
        });
        $factory = $this->createStub(JsonFactory::class);
        $factory->method('create')->willReturn($json);

        return $factory;
    }
}
