<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideAdminUi\Test\Integration;

use Hryvinskyi\DataLayerAdminUi\Test\Integration\RendersSettingsForm;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\TestFramework\TestCase\AbstractBackendController;

/**
 * The data layer's settings page links this module's stylesheets after the admin UI kit's, because the warning comment
 * reads the kit's tokens and sets its colour by order alone
 *
 * @magentoAppArea adminhtml
 * @magentoAppIsolation enabled
 * @magentoDbIsolation enabled
 */
class SettingsLookTest extends AbstractBackendController
{
    use RendersSettingsForm;

    /**
     * @var string
     */
    protected $resource = 'Hryvinskyi_DataLayerAdminUi::config';

    /**
     * @var string
     */
    protected $uri = 'backend/admin/system_config/edit/section/hryvinskyi_datalayer';

    /**
     * @var string
     */
    protected $httpMethod = HttpRequest::METHOD_GET;

    /**
     * The configuration page sends an administrator without access to the section back to the settings index
     *
     * @var int
     */
    protected $expectedNoAccessResponseCode = 302;

    /**
     * The section's page links the warning comment's stylesheet, and links it after the kit's
     *
     * @return void
     */
    public function testLinksTheWarningCommentStylesheetAfterTheKits(): void
    {
        $this->dispatch($this->uri);
        $response = $this->getResponse();
        self::assertInstanceOf(HttpResponse::class, $response);
        self::assertSame(200, $response->getHttpResponseCode());

        $hrefs = array_map(
            static fn (\DOMElement $link): string => $link->getAttribute('href'),
            $this->elements($this->page($response->getBody()), '//head/link[@rel="stylesheet"]')
        );
        $kit = $this->position($hrefs, '/Hryvinskyi_AdminUi/css/admin-ui.css');
        $own = $this->position($hrefs, '/Hryvinskyi_DataLayerServerSideAdminUi/css/warning-comment.css');

        self::assertLessThan($own, $kit, 'the kit stylesheet is linked before the warning comment\'s');
    }

    /**
     * Parse a whole page the way a browser builds its document
     *
     * @param string $html
     * @return \DOMXPath
     */
    private function page(string $html): \DOMXPath
    {
        $document = new \DOMDocument();
        $errors = libxml_use_internal_errors(true);
        $document->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($errors);

        return new \DOMXPath($document);
    }

    /**
     * Find where the first address that holds a path stands in a list of addresses
     *
     * @param list<string> $hrefs
     * @param string $path
     * @return int
     */
    private function position(array $hrefs, string $path): int
    {
        foreach ($hrefs as $index => $href) {
            if (str_contains($href, $path)) {
                return $index;
            }
        }

        self::fail(sprintf('No stylesheet at %s is linked; the head links: %s', $path, implode(', ', $hrefs)));
    }
}
