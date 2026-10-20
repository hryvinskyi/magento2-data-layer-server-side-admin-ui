<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideAdminUi\Test\Unit\Model\Delivery;

use Hryvinskyi\DataLayerServerSideAdminUi\Model\Delivery\WebsiteParameter;
use Magento\Framework\App\RequestInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The scope of a delivery action is the default scope or one website; anything else is refused
 */
class WebsiteParameterTest extends TestCase
{
    /**
     * A missing, empty or zero value is the default scope; a positive whole number is a website
     *
     * @dataProvider scopes
     * @param mixed $value
     * @param int|null $expected
     * @return void
     */
    #[DataProvider('scopes')]
    public function testReadsTheScope(mixed $value, ?int $expected): void
    {
        self::assertSame($expected, (new WebsiteParameter())->read($this->request($value)));
    }

    /**
     * Values that name a scope
     *
     * @return array<string, array{mixed, int|null}>
     */
    public static function scopes(): array
    {
        return [
            'missing' => [null, null],
            'empty' => ['', null],
            'zero' => ['0', null],
            'zero as a number' => [0, null],
            'a website' => ['3', 3],
            'a website as a number' => [12, 12],
        ];
    }

    /**
     * A value that is not a website id is refused instead of being read as every website
     *
     * @dataProvider notWebsites
     * @param mixed $value
     * @return void
     */
    #[DataProvider('notWebsites')]
    public function testRefusesWhatIsNotAWebsite(mixed $value): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new WebsiteParameter())->read($this->request($value));
    }

    /**
     * Values that are not a website id
     *
     * @return array<string, array{mixed}>
     */
    public static function notWebsites(): array
    {
        return [
            'negative' => ['-1'],
            'negative number' => [-4],
            'a word' => ['base'],
            'leading zero' => ['01'],
            'a fraction' => ['1.5'],
            'a trailing newline' => ["5\n"],
            'a list' => [['1']],
        ];
    }

    /**
     * Build a request carrying a website parameter
     *
     * @param mixed $value
     * @return RequestInterface
     */
    private function request(mixed $value): RequestInterface
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('getParam')->with('website')->willReturn($value);

        return $request;
    }
}
