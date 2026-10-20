<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideAdminUi\Test\Unit\Model\Delivery;

use Hryvinskyi\DataLayerApi\Api\Data\EventInterface;
use Hryvinskyi\DataLayerApi\Api\Data\EventInterfaceFactory;
use Hryvinskyi\DataLayerApi\Api\Data\VisitorContextInterface;
use Hryvinskyi\DataLayerApi\Api\Data\VisitorContextInterfaceFactory;
use Hryvinskyi\DataLayerServerSide\Api\ConsentStateInterface;
use Hryvinskyi\DataLayerServerSide\Api\Data\TransportResultInterface;
use Hryvinskyi\DataLayerServerSide\Api\TransportInterface;
use Hryvinskyi\DataLayerServerSide\Model\Data\TransportResult;
use Hryvinskyi\DataLayerServerSide\Model\Payload\MeasurementProtocolPayloadBuilder;
use Hryvinskyi\DataLayerServerSide\Model\Transport\CredentialRedactor;
use Hryvinskyi\DataLayerServerSide\Model\Transport\Ga4Transport;
use Hryvinskyi\DataLayerServerSide\Model\Transport\TransportPool;
use Hryvinskyi\DataLayerServerSide\Test\Unit\Fake\BuildsHttpSender;
use Hryvinskyi\DataLayerServerSide\Test\Unit\Fake\DeliveryConfig;
use Hryvinskyi\DataLayerServerSide\Test\Unit\Fake\FixedClock;
use Hryvinskyi\DataLayerServerSide\Test\Unit\Fake\FixedRandom;
use Hryvinskyi\DataLayerServerSide\Test\Unit\Fake\RecordingLogger;
use Hryvinskyi\DataLayerServerSide\Test\Unit\Fake\ScriptedTransport;
use Hryvinskyi\DataLayerServerSideAdminUi\Api\Data\TestResultInterface;
use Hryvinskyi\DataLayerServerSideAdminUi\Api\Data\TestResultInterfaceFactory;
use Hryvinskyi\DataLayerServerSideAdminUi\Model\Data\TestResult;
use Hryvinskyi\DataLayerServerSideAdminUi\Model\Delivery\TestableTransports;
use Hryvinskyi\DataLayerServerSideAdminUi\Model\Delivery\TestEventSender;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The test event goes only to the validation endpoint, at once, with the website's saved settings; every outcome is
 * a result, and the API secret is in none of them and in no log line
 */
class TestEventSenderTest extends TestCase
{
    use BuildsHttpSender;

    private const SECRET = 'ga4-Secret/Value+1';
    private const NOW = '2026-10-15 12:00:00';

    /**
     * @var DeliveryConfig
     */
    private DeliveryConfig $config;

    /**
     * @var RecordingLogger
     */
    private RecordingLogger $logger;

    /**
     * @var FixedRandom
     */
    private FixedRandom $random;

    /**
     * @var list<array<mixed>>
     */
    private array $createdEvents = [];

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->config = new DeliveryConfig([1], 'ga4', 'G-ABC123', false, true, self::SECRET);
        $this->logger = new RecordingLogger();
        $this->random = new FixedRandom(1234567890);
    }

    /**
     * A reply without findings is valid; the event went once, in debug mode, with the website's id, as the fixed
     * test event of a random visitor
     *
     * @return void
     */
    public function testValidReply(): void
    {
        $transport = $this->transport(fn (): TransportResultInterface => new TransportResult(200, true, false));

        $result = $this->sender(['ga4' => $transport])->send(1);

        self::assertTrue($result->isValid());
        self::assertSame([], $result->getMessages());
        self::assertSame('', $result->getError());
        self::assertCount(1, $transport->sent);
        self::assertTrue($transport->sent[0]['debug'], 'The test goes to the validation endpoint only.');
        self::assertSame(1, $transport->sent[0]['websiteId']);
        self::assertSame([['name' => 'hryvinskyi_datalayer_test', 'ecommerce' => false]], $this->createdEvents);
        self::assertSame([[1000000000, 9999999999]], $this->random->ranges);

        $body = json_decode($transport->sent[0]['body'], true);
        self::assertIsArray($body);
        $timestamp = (new \DateTimeImmutable(self::NOW, new \DateTimeZone('UTC')))->getTimestamp();
        self::assertSame('1234567890.' . $timestamp, $body['client_id'] ?? null);
        self::assertSame(
            [[
                'name' => 'hryvinskyi_datalayer_test',
                'timestamp_micros' => $timestamp * 1000000,
                'params' => ['engagement_time_msec' => 1],
            ]],
            $body['events'] ?? null
        );
        self::assertStringNotContainsString(self::SECRET, $transport->sent[0]['body']);
    }

    /**
     * The endpoint's findings are listed and the event is not valid
     *
     * @return void
     */
    public function testValidationMessages(): void
    {
        $findings = [
            'VALUE_INVALID · client_id: must be a non-empty string.',
            'NAME_INVALID · events[0].name: use letters, digits and underscores.',
        ];
        $transport = $this->transport(
            fn (): TransportResultInterface => new TransportResult(200, true, false, '', $findings)
        );

        $result = $this->sender(['ga4' => $transport])->send(1);

        self::assertFalse($result->isValid());
        self::assertSame($findings, $result->getMessages());
        self::assertSame('', $result->getError());
    }

    /**
     * An HTTP error or no answer at all is an error naming what happened, and is logged
     *
     * @dataProvider failures
     * @param TransportResultInterface $answer
     * @param string $expected
     * @return void
     */
    #[DataProvider('failures')]
    public function testFailureIsReported(TransportResultInterface $answer, string $expected): void
    {
        $transport = $this->transport(fn (): TransportResultInterface => $answer);

        $result = $this->sender(['ga4' => $transport])->send(1);

        self::assertFalse($result->isValid());
        self::assertSame([], $result->getMessages());
        self::assertSame($expected, $result->getError());
        self::assertStringContainsString('website 1 was not checked: ' . $expected, $this->logger->dump());
    }

    /**
     * Answers that are not a validation
     *
     * @return array<string, array{TransportResultInterface, string}>
     */
    public static function failures(): array
    {
        return [
            'forbidden' => [new TransportResult(403, false, false, 'HTTP 403: Forbidden'), 'HTTP 403: Forbidden'],
            'server error without text' => [new TransportResult(503, false, true), 'HTTP 503'],
            'timeout' => [
                new TransportResult(0, false, true, 'No answer: Operation timed out after 10001 milliseconds'),
                'No answer: Operation timed out after 10001 milliseconds',
            ],
            'no status and no text' => [new TransportResult(0, false, true), 'No answer came back.'],
        ];
    }

    /**
     * A setting the test needs is missing: nothing is sent and the result says what to save
     *
     * @dataProvider missingSettings
     * @param list<int> $enabledWebsites
     * @param string|null $measurementId
     * @param string|null $secret
     * @param string $expected
     * @return void
     */
    #[DataProvider('missingSettings')]
    public function testMissingSettingSendsNothing(
        array $enabledWebsites,
        ?string $measurementId,
        ?string $secret,
        string $expected
    ): void {
        $this->config = new DeliveryConfig($enabledWebsites, 'ga4', $measurementId, false, true, $secret);
        $transport = $this->transport(fn (): TransportResultInterface => new TransportResult(200, true, false));

        $result = $this->sender(['ga4' => $transport])->send(1);

        self::assertFalse($result->isValid());
        self::assertStringContainsString($expected, $result->getError());
        self::assertSame([], $transport->sent);
    }

    /**
     * Settings with one thing missing
     *
     * @return array<string, array{list<int>, string|null, string|null, string}>
     */
    public static function missingSettings(): array
    {
        return [
            'module disabled' => [[], 'G-ABC123', self::SECRET, 'Server-side sending is off for this scope.'],
            'no measurement id' => [[1], null, self::SECRET, 'A measurement ID and an API secret are needed'],
            'no secret' => [[1], 'G-ABC123', null, 'A measurement ID and an API secret are needed'],
        ];
    }

    /**
     * A server container has no validation endpoint and would record the event: it is not tested
     *
     * @return void
     */
    public function testServerContainerIsNotTested(): void
    {
        $this->config->transport = 'sgtm';
        $ga4 = $this->transport(fn (): TransportResultInterface => new TransportResult(200, true, false));
        $container = $this->transport(fn (): TransportResultInterface => new TransportResult(200, true, false));
        $sender = $this->sender(['ga4' => $ga4, 'sgtm' => $container]);

        $result = $sender->send(1);

        self::assertFalse($sender->canTest(1));
        self::assertSame('Testing is available for Google Analytics (Measurement Protocol) only.', $result->getError());
        self::assertSame([], $ga4->sent);
        self::assertSame([], $container->sent);

        $this->config->transport = 'ga4';
        self::assertTrue($sender->canTest(1));
    }

    /**
     * The secret is removed from the error, the findings, the log and the exception text, whatever echoes it
     *
     * @return void
     */
    public function testSecretAppearsNowhere(): void
    {
        $url = 'https://www.google-analytics.com/debug/mp/collect?measurement_id=G-ABC123&api_secret='
            . rawurlencode(self::SECRET);
        $answers = [
            fn (): TransportResultInterface => new TransportResult(0, false, true, 'No answer: reached ' . $url),
            fn (): TransportResultInterface => new TransportResult(200, true, false, '', ['X · y: ' . self::SECRET]),
            fn (): TransportResultInterface => throw new \RuntimeException('Connection refused for ' . $url),
        ];
        $results = [];
        foreach ($answers as $answer) {
            $results[] = $this->sender(['ga4' => $this->transport($answer)])->send(1);
        }

        self::assertStringContainsString('reached https://www.google-analytics.com', $results[0]->getError());
        self::assertSame('The test event could not be sent. The log has the details.', $results[2]->getError());
        self::assertStringContainsString('RuntimeException: Connection refused', $this->logger->dump());
        foreach ($results as $result) {
            $json = (string) json_encode([$result->isValid(), $result->getMessages(), $result->getError()]);
            self::assertStringNotContainsString(self::SECRET, $json);
            self::assertStringNotContainsString(rawurlencode(self::SECRET), $json);
        }
        self::assertStringNotContainsString(self::SECRET, $this->logger->dump());
        self::assertStringNotContainsString(rawurlencode(self::SECRET), $this->logger->dump());
    }

    /**
     * Through the real Google Analytics transport the request goes to the validation endpoint, never to the one
     * that records; its findings are read, and an HTTP client error echoing the URL leaks no secret
     *
     * @return void
     */
    public function testRealTransportCallsOnlyTheValidationEndpoint(): void
    {
        $reply = (string) json_encode(['validationMessages' => [[
            'fieldPath' => 'events',
            'description' => 'Event at index 0 has an invalid name.',
            'validationCode' => 'NAME_INVALID',
        ]]]);
        $findings = $this->sender(['ga4' => $this->ga4Transport(static fn (): array => [200, $reply])])->send(1);
        $valid = $this->sender(['ga4' => $this->ga4Transport(static fn (): array => [200, '{}'])])->send(1);
        $failed = $this->sender(['ga4' => $this->ga4Transport(static function (string $url): array {
            throw new \RuntimeException('Could not resolve host: ' . $url);
        })])->send(1);

        self::assertCount(3, $this->requests);
        foreach ($this->requests as $request) {
            self::assertStringStartsWith('https://www.google-analytics.com/debug/mp/collect?', $request->url);
        }
        self::assertSame(['NAME_INVALID · events: Event at index 0 has an invalid name.'], $findings->getMessages());
        self::assertTrue($valid->isValid());
        self::assertStringContainsString('resolve host: https://www.google-analytics.com', $failed->getError());
        foreach ([$findings, $valid, $failed] as $result) {
            self::assertStringNotContainsString(rawurlencode(self::SECRET), $result->getError());
            self::assertStringNotContainsString(self::SECRET, $result->getError());
        }
        self::assertStringNotContainsString(self::SECRET, $this->logger->dump());
        self::assertStringNotContainsString(rawurlencode(self::SECRET), $this->logger->dump());
    }

    /**
     * Build the sender over the given transports
     *
     * @param array<string, TransportInterface> $transports
     * @return TestEventSender
     */
    private function sender(array $transports): TestEventSender
    {
        $consent = $this->createStub(ConsentStateInterface::class);
        $consent->method('forVisitor')->willReturn(null);

        return new TestEventSender(
            $this->config,
            new TransportPool($transports),
            new MeasurementProtocolPayloadBuilder($consent),
            $this->eventFactory(),
            $this->visitorFactory(),
            new FixedClock(new \DateTimeImmutable(self::NOW, new \DateTimeZone('UTC'))),
            $this->random,
            new CredentialRedactor(),
            new Json(),
            $this->testResultFactory(),
            $this->logger,
            new TestableTransports(['ga4'])
        );
    }

    /**
     * Build a scripted transport
     *
     * @param \Closure(string): TransportResultInterface $script
     * @return ScriptedTransport
     */
    private function transport(\Closure $script): ScriptedTransport
    {
        return new ScriptedTransport($script);
    }

    /**
     * Build the real Google Analytics transport over a scripted HTTP client
     *
     * @param \Closure(string): array{int, string} $script
     * @return Ga4Transport
     */
    private function ga4Transport(\Closure $script): Ga4Transport
    {
        return new Ga4Transport(
            $this->config,
            $this->httpSender($script),
            $this->classifier(),
            new Json(),
            $this->logger
        );
    }

    /**
     * Build an event factory that records what it was asked to create
     *
     * @return EventInterfaceFactory
     */
    private function eventFactory(): EventInterfaceFactory
    {
        $factory = $this->createMock(EventInterfaceFactory::class);
        $factory->method('create')->willReturnCallback(function (array $data = []): EventInterface {
            $this->createdEvents[] = $data;
            $name = $data['name'] ?? '';
            $event = $this->createStub(EventInterface::class);
            $event->method('getName')->willReturn(is_string($name) ? $name : '');
            $event->method('isEcommerce')->willReturn(false);
            $event->method('getParameters')->willReturn([]);
            $event->method('getItems')->willReturn([]);

            return $event;
        });

        return $factory;
    }

    /**
     * Build a visitor factory that gives the client id and the moment it was asked for
     *
     * @return VisitorContextInterfaceFactory
     */
    private function visitorFactory(): VisitorContextInterfaceFactory
    {
        $factory = $this->createMock(VisitorContextInterfaceFactory::class);
        $factory->method('create')->willReturnCallback(function (array $data = []): VisitorContextInterface {
            $clientId = $data['clientId'] ?? null;
            $occurredAt = $data['occurredAt'] ?? null;
            if (!is_string($clientId) || !$occurredAt instanceof \DateTimeImmutable) {
                throw new \InvalidArgumentException('A visitor needs a client id and a moment.');
            }
            $visitor = $this->createStub(VisitorContextInterface::class);
            $visitor->method('getClientId')->willReturn($clientId);
            $visitor->method('getOccurredAt')->willReturn($occurredAt);
            $visitor->method('getClickIds')->willReturn([]);

            return $visitor;
        });

        return $factory;
    }

    /**
     * Build a test result factory creating the real result from named arguments
     *
     * @return TestResultInterfaceFactory
     */
    private function testResultFactory(): TestResultInterfaceFactory
    {
        $factory = $this->createMock(TestResultInterfaceFactory::class);
        $factory->method('create')->willReturnCallback(static function (array $data = []): TestResultInterface {
            $valid = $data['valid'] ?? null;
            $messages = $data['messages'] ?? [];
            $error = $data['error'] ?? '';
            if (!is_bool($valid) || !is_array($messages) || !is_string($error)) {
                throw new \InvalidArgumentException('A test result was created with wrong argument types.');
            }

            return new TestResult($valid, $messages, $error);
        });

        return $factory;
    }
}
