<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideAdminUi\Model\Data;

use Hryvinskyi\DataLayerServerSideAdminUi\Api\Data\TestResultInterface;

/**
 * The outcome of one test event, immutable and valid by construction
 */
class TestResult implements TestResultInterface
{
    /**
     * @var list<string>
     */
    private readonly array $messages;

    /**
     * @param bool $valid
     * @param array<mixed> $messages
     * @param string $error
     * @throws \InvalidArgumentException When a message is not text, or the outcome mixes a valid event, findings and
     *     an error
     */
    public function __construct(
        private readonly bool $valid,
        array $messages = [],
        private readonly string $error = ''
    ) {
        $accepted = [];
        foreach ($messages as $message) {
            if (!is_string($message)) {
                throw new \InvalidArgumentException('Every validation message is text.');
            }
            $accepted[] = $message;
        }
        $outcomes = (int) $valid + (int) ($accepted !== []) + (int) ($error !== '');
        if ($outcomes !== 1) {
            throw new \InvalidArgumentException(
                'A test result is valid, or carries validation messages, or carries an error: exactly one of them.'
            );
        }
        $this->messages = $accepted;
    }

    /**
     * @inheritDoc
     */
    public function isValid(): bool
    {
        return $this->valid;
    }

    /**
     * @inheritDoc
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * @inheritDoc
     */
    public function getError(): string
    {
        return $this->error;
    }
}
