<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideAdminUi\Test\Integration\Fake;

use Hryvinskyi\DataLayerServerSide\Api\Data\TransportResultInterface;
use Hryvinskyi\DataLayerServerSide\Api\TransportInterface;

/**
 * A transport that records what it was asked to send and answers with a prepared result, without any network
 */
class RecordingTransport implements TransportInterface
{
    /**
     * @var list<array{body: string, websiteId: int, debug: bool}>
     */
    public array $sent = [];

    /**
     * @param TransportResultInterface $answer
     */
    public function __construct(private readonly TransportResultInterface $answer)
    {
    }

    /**
     * @inheritDoc
     */
    public function send(string $body, int $websiteId, bool $debug): TransportResultInterface
    {
        $this->sent[] = ['body' => $body, 'websiteId' => $websiteId, 'debug' => $debug];

        return $this->answer;
    }
}
