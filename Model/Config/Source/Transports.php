<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideAdminUi\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * The transports events can be delivered through, registered through the `transports` argument (code to label)
 *
 * Labels are kept in the source language they are registered in and translated each time the options are read, so
 * the shared list answers each request in its own locale.
 */
class Transports implements OptionSourceInterface
{
    /**
     * @var array<string, string>
     */
    private readonly array $labels;

    /**
     * @param array<mixed> $transports Admin label of each transport, keyed by its code
     * @throws \InvalidArgumentException When a code is not text or a label is not text
     */
    public function __construct(array $transports = [])
    {
        $labels = [];
        foreach ($transports as $code => $label) {
            if (!is_string($code) || (!is_string($label) && !$label instanceof \Stringable)) {
                throw new \InvalidArgumentException(
                    sprintf('The transport "%s" needs a text code and a text label.', (string) $code)
                );
            }
            $labels[$code] = (string) $label;
        }
        $this->labels = $labels;
    }

    /**
     * @inheritDoc
     *
     * @return list<array{value: string, label: string}>
     * @phpstan-impure The answer follows the locale active when it is read
     */
    public function toOptionArray(): array
    {
        $options = [];
        foreach ($this->labels as $code => $label) {
            $options[] = ['value' => $code, 'label' => (string) __($label)];
        }

        return $options;
    }
}
