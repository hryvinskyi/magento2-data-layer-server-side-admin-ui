<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideAdminUi\Test\Unit\Model\Data;

use Hryvinskyi\DataLayerServerSideAdminUi\Model\Data\TestResult;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A test result holds exactly one outcome: valid, validation messages, or an error
 */
class TestResultTest extends TestCase
{
    /**
     * Each outcome reads back as given
     *
     * @return void
     */
    public function testEachOutcomeReadsBack(): void
    {
        $valid = new TestResult(true);
        $findings = new TestResult(false, ['NAME_INVALID · name: too long']);
        $error = new TestResult(false, [], 'HTTP 403');

        self::assertTrue($valid->isValid());
        self::assertSame([], $valid->getMessages());
        self::assertSame('', $valid->getError());
        self::assertFalse($findings->isValid());
        self::assertSame(['NAME_INVALID · name: too long'], $findings->getMessages());
        self::assertSame('', $findings->getError());
        self::assertFalse($error->isValid());
        self::assertSame('HTTP 403', $error->getError());
    }

    /**
     * A result that holds no outcome, or several, or a message that is not text, is refused
     *
     * @dataProvider mixedOutcomes
     * @param bool $valid
     * @param array<mixed> $messages
     * @param string $error
     * @return void
     */
    #[DataProvider('mixedOutcomes')]
    public function testMixedOrMissingOutcomeIsRefused(bool $valid, array $messages, string $error): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new TestResult($valid, $messages, $error);
    }

    /**
     * Results that are not exactly one outcome
     *
     * @return array<string, array{bool, array<mixed>, string}>
     */
    public static function mixedOutcomes(): array
    {
        return [
            'nothing' => [false, [], ''],
            'valid with findings' => [true, ['X · y: z'], ''],
            'valid with an error' => [true, [], 'HTTP 500'],
            'findings and an error' => [false, ['X · y: z'], 'HTTP 500'],
            'a finding that is not text' => [false, [42], ''],
        ];
    }
}
