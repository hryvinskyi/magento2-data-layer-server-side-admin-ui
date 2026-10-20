<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideAdminUi\Test\Integration\Fake;

use Hryvinskyi\DataLayerServerSide\Api\ConfigInterface;
use Hryvinskyi\DataLayerServerSide\Api\Data\TransportResultInterface;
use Hryvinskyi\DataLayerServerSide\Api\TransportInterface;

/**
 * A transport that records the settings the real configuration reader answers while it sends, as a real transport
 * reads them to address its request, and answers with a prepared result without any network
 */
class ConfigReadingTransport implements TransportInterface
{
    /**
     * @var list<array{websiteId: int, debug: bool, measurementId: string|null, secret: string|null}>
     */
    public array $sent = [];

    /**
     * @param ConfigInterface $config
     * @param TransportResultInterface $answer
     */
    public function __construct(
        private readonly ConfigInterface $config,
        private readonly TransportResultInterface $answer
    ) {
    }

    /**
     * @inheritDoc
     */
    public function send(string $body, int $websiteId, bool $debug): TransportResultInterface
    {
        $this->sent[] = [
            'websiteId' => $websiteId,
            'debug' => $debug,
            'measurementId' => $this->config->getMeasurementId($websiteId),
            'secret' => $this->config->getGa4ApiSecret($websiteId),
        ];

        return $this->answer;
    }
}
