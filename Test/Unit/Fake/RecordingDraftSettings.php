<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideAdminUi\Test\Unit\Fake;

use Hryvinskyi\DataLayer\Api\Config\DraftSettingsInterface;

/**
 * Records each draft, and tells whether work is running inside one
 */
class RecordingDraftSettings implements DraftSettingsInterface
{
    /**
     * @var list<array<string, string>>
     */
    public array $drafts = [];

    /**
     * @var array<string, string>|null The draft the running work reads; null outside
     */
    public ?array $active = null;

    /**
     * @inheritDoc
     */
    public function withDraft(array $values, callable $work): mixed
    {
        $this->drafts[] = $values;
        $this->active = $values;
        try {
            return $work();
        } finally {
            $this->active = null;
        }
    }
}
