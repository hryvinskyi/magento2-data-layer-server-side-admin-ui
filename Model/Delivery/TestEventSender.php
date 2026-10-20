<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideAdminUi\Model\Delivery;

use Hryvinskyi\DataLayerApi\Api\Data\EventInterface;
use Hryvinskyi\DataLayerApi\Api\Data\EventInterfaceFactory;
use Hryvinskyi\DataLayerApi\Api\Data\VisitorContextInterfaceFactory;
use Hryvinskyi\DataLayerServerSide\Api\ClockInterface;
use Hryvinskyi\DataLayerServerSide\Api\ConfigInterface;
use Hryvinskyi\DataLayerServerSide\Api\CredentialRedactorInterface;
use Hryvinskyi\DataLayerServerSide\Api\Data\TransportResultInterface;
use Hryvinskyi\DataLayerServerSide\Api\Payload\PayloadBuilderInterface;
use Hryvinskyi\DataLayerServerSide\Api\RandomSourceInterface;
use Hryvinskyi\DataLayerServerSide\Api\TransportPoolInterface;
use Hryvinskyi\DataLayerServerSideAdminUi\Api\Data\TestResultInterface;
use Hryvinskyi\DataLayerServerSideAdminUi\Api\Data\TestResultInterfaceFactory;
use Hryvinskyi\DataLayerServerSideAdminUi\Api\TestableTransportsInterface;
use Hryvinskyi\DataLayerServerSideAdminUi\Api\TestEventSenderInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Psr\Log\LoggerInterface;

/**
 * Sends one fixed event, `hryvinskyi_datalayer_test`, through the website's transport in debug mode
 *
 * Debug mode sends to the transport's validation endpoint, which records nothing; the event never goes through the
 * outbox, so no delivery run can send it to the recording endpoint later. Only the testable transports have such an
 * endpoint: a server container has none and would record the event, so it is not tested. The event carries a random
 * client id of the protocol's shape (`<10 digits>.<unix time>`) so it belongs to no real visitor. The website's
 * settings as the store reads them address the request (saved ones, or the setup wizard's unsaved ones while they are
 * drafted), so a missing one is reported as missing, not as one to save, without sending anything. The API secret is
 * removed from every error, finding and log line.
 */
class TestEventSender implements TestEventSenderInterface
{
    private const EVENT_NAME = 'hryvinskyi_datalayer_test';
    private const CLIENT_NUMBER_MIN = 1000000000;
    private const CLIENT_NUMBER_MAX = 9999999999;

    /**
     * @param ConfigInterface $config
     * @param TransportPoolInterface $transports
     * @param PayloadBuilderInterface $payloadBuilder
     * @param EventInterfaceFactory $eventFactory
     * @param VisitorContextInterfaceFactory $visitorFactory
     * @param ClockInterface $clock
     * @param RandomSourceInterface $random
     * @param CredentialRedactorInterface $redactor
     * @param SerializerInterface $serializer Encodes the request body as JSON
     * @param TestResultInterfaceFactory $results
     * @param LoggerInterface $logger
     * @param TestableTransportsInterface $testableTransports The transports whose debug mode records nothing
     */
    public function __construct(
        private readonly ConfigInterface $config,
        private readonly TransportPoolInterface $transports,
        private readonly PayloadBuilderInterface $payloadBuilder,
        private readonly EventInterfaceFactory $eventFactory,
        private readonly VisitorContextInterfaceFactory $visitorFactory,
        private readonly ClockInterface $clock,
        private readonly RandomSourceInterface $random,
        private readonly CredentialRedactorInterface $redactor,
        private readonly SerializerInterface $serializer,
        private readonly TestResultInterfaceFactory $results,
        private readonly LoggerInterface $logger,
        private readonly TestableTransportsInterface $testableTransports
    ) {
    }

    /**
     * @inheritDoc
     */
    public function canTest(int $websiteId): bool
    {
        return $this->testableTransports->isTestable($this->config->getTransport($websiteId));
    }

    /**
     * @inheritDoc
     */
    public function send(int $websiteId): TestResultInterface
    {
        $secrets = [];
        try {
            $secret = $this->config->getGa4ApiSecret($websiteId);
            $secrets = $secret !== null && $secret !== '' ? [$secret] : [];

            return $this->check($websiteId, $secrets);
        } catch (\Throwable $exception) {
            $this->logger->error(sprintf(
                'The test event for website %d could not be sent: %s: %s',
                $websiteId,
                $exception::class,
                $this->redactor->redact($exception->getMessage(), $secrets)
            ));

            return $this->error((string) __('The test event could not be sent. The log has the details.'));
        }
    }

    /**
     * Check the settings, then send the event and read the answer
     *
     * @param int $websiteId
     * @param list<string> $secrets The website's API secret, when it has one
     * @return TestResultInterface
     */
    private function check(int $websiteId, array $secrets): TestResultInterface
    {
        if (!$this->config->isEnabled($websiteId)) {
            return $this->error((string) __('Server-side sending is off for this scope.'));
        }
        if (!$this->canTest($websiteId)) {
            return $this->error((string) __('Testing is available for Google Analytics (Measurement Protocol) only.'));
        }
        $transport = $this->config->getTransport($websiteId);
        if ($this->config->getMeasurementId($websiteId) === null || $secrets === []) {
            return $this->error((string) __('A measurement ID and an API secret are needed for this scope.'));
        }

        $result = $this->transports->get($transport)->send($this->body($transport), $websiteId, true);

        return $result->isDelivered()
            ? $this->answered($result, $secrets)
            : $this->notChecked($result, $websiteId, $secrets);
    }

    /**
     * Read the findings of an endpoint that answered
     *
     * @param TransportResultInterface $result
     * @param list<string> $secrets
     * @return TestResultInterface
     */
    private function answered(TransportResultInterface $result, array $secrets): TestResultInterface
    {
        $messages = array_map(
            fn (string $message): string => $this->redactor->redact($message, $secrets),
            $result->getValidationMessages()
        );

        return $this->results->create(['valid' => $messages === [], 'messages' => $messages]);
    }

    /**
     * Report a request the endpoint did not accept, or that got no answer
     *
     * @param TransportResultInterface $result
     * @param int $websiteId
     * @param list<string> $secrets
     * @return TestResultInterface
     */
    private function notChecked(TransportResultInterface $result, int $websiteId, array $secrets): TestResultInterface
    {
        $error = trim($this->redactor->redact($result->getMessage(), $secrets));
        if ($error === '') {
            $error = $result->getStatus() > 0
                ? sprintf('HTTP %d', $result->getStatus())
                : (string) __('No answer came back.');
        }
        $this->logger->warning(sprintf('The test event for website %d was not checked: %s', $websiteId, $error));

        return $this->error($error);
    }

    /**
     * Build the request body of the test event
     *
     * @param string $transport
     * @return string
     * @throws \UnexpectedValueException When no body can be built
     */
    private function body(string $transport): string
    {
        $now = $this->clock->now();
        $clientId = sprintf(
            '%d.%d',
            $this->random->between(self::CLIENT_NUMBER_MIN, self::CLIENT_NUMBER_MAX),
            $now->getTimestamp()
        );
        $payload = $this->payloadBuilder->build(
            $this->event(),
            $this->visitorFactory->create(['occurredAt' => $now, 'clientId' => $clientId]),
            $transport
        );
        $body = $payload !== null ? $this->serializer->serialize($payload) : false;
        if (!is_string($body)) {
            throw new \UnexpectedValueException('The test event has no request body.');
        }

        return $body;
    }

    /**
     * Create the test event
     *
     * @return EventInterface
     */
    private function event(): EventInterface
    {
        return $this->eventFactory->create(['name' => self::EVENT_NAME, 'ecommerce' => false]);
    }

    /**
     * Create the result of a test that found no answer to read
     *
     * @param string $error
     * @return TestResultInterface
     */
    private function error(string $error): TestResultInterface
    {
        return $this->results->create(['valid' => false, 'error' => $error]);
    }
}
