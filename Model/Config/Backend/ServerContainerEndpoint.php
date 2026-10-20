<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideAdminUi\Model\Config\Backend;

use Hryvinskyi\DataLayerServerSide\Api\ServerContainerHostPolicyInterface;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;

/**
 * Saves the server container's address: empty, or an https URL of a host (and port) alone, without path, query string
 * or fragment, on a host the server may call
 *
 * The trailing slash is removed, so the transport's path can be appended to the stored value as it is. A host in the
 * store's own network (`localhost`, a loopback, private or link-local address) is refused: the relay would call it
 * from inside that network.
 */
class ServerContainerEndpoint extends Value
{
    /**
     * An https URL of a host and at most a port; one trailing slash is accepted, since it is removed before saving
     */
    public const HTTPS_ORIGIN_PATTERN = '~^https://[a-z0-9]([a-z0-9.-]*[a-z0-9])?(:\d{1,5})?/?$~iD';

    /**
     * @param Context $context
     * @param Registry $registry
     * @param ScopeConfigInterface $config
     * @param TypeListInterface $cacheTypeList
     * @param ServerContainerHostPolicyInterface $hostPolicy
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array<string,mixed> $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        private readonly ServerContainerHostPolicyInterface $hostPolicy,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    /**
     * Refuse a value that is not the https address of a host
     *
     * @return $this
     * @throws LocalizedException When the value is neither empty nor an https URL without path or query string, or
     *     its host is in the store's own network
     */
    public function beforeSave()
    {
        $value = $this->getValue();
        $endpoint = is_scalar($value) ? rtrim(trim((string) $value), '/') : '';
        if ($endpoint !== '' && !$this->isHttpsOrigin($endpoint)) {
            throw new LocalizedException(
                __('Enter an https:// address with no path or query, for example https://sst.example.com.')
            );
        }
        if ($endpoint !== '' && !$this->hostPolicy->accepts($endpoint)) {
            throw new LocalizedException(
                __('Enter the public address of your server container, not one in the store’s own network.')
            );
        }
        $this->setValue($endpoint);

        return parent::beforeSave();
    }

    /**
     * Whether a URL is https with a host and at most a port: no credentials, path, query string or fragment
     *
     * @param string $url
     * @return bool
     */
    private function isHttpsOrigin(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && preg_match(self::HTTPS_ORIGIN_PATTERN, $url) === 1;
    }
}
