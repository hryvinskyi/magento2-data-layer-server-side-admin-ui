<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideAdminUi\Model\Delivery;

use Hryvinskyi\DataLayerServerSideAdminUi\Api\TestableTransportsInterface;

/**
 * The testable transports listed in the `codes` argument in `di.xml`, checked when the list is built
 */
class TestableTransports implements TestableTransportsInterface
{
    /**
     * @var list<string>
     */
    private readonly array $codes;

    /**
     * @param array<mixed> $codes Codes of the transports whose debug mode records nothing
     * @throws \InvalidArgumentException When a listed code is not a non-empty text
     */
    public function __construct(array $codes = [])
    {
        $accepted = [];
        foreach ($codes as $code) {
            if (!is_string($code) || $code === '') {
                throw new \InvalidArgumentException('Every testable transport is a non-empty code.');
            }
            $accepted[] = $code;
        }
        $this->codes = array_values(array_unique($accepted));
    }

    /**
     * @inheritDoc
     */
    public function isTestable(string $code): bool
    {
        return in_array($code, $this->codes, true);
    }

    /**
     * @inheritDoc
     */
    public function getCodes(): array
    {
        return $this->codes;
    }
}
