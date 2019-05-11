<?php

namespace DataCue\PrestaShop\Common;

use DataCue\Client;
use DataCue\PrestaShop\Queue;
use DataCue\PrestaShop\Utils;

/**
 * Class Initializer
 * @package DataCue\PrestaShop\Common
 */
class Initializer
{
    /**
     * chunk size of each package
     */
    const CHUNK_SIZE = 200;

    /**
     * Max try times
     */
    const MAX_TRY_TIMES = 3;

    /**
     * @var string api key
     */
    private $apiKey;

    /**
     * @var string api secret
     */
    private $apiSecret;

    /**
     * @var \DataCue\Client
     */
    private $client;

    /**
     * Initializer constructor.
     * @param $apiKey
     * @param $apiSecret
     */
    public function __construct($apiKey, $apiSecret)
    {
        $this->apiKey = $apiKey;
        $this->apiSecret = $apiSecret;
    }

    /**
     * @throws \DataCue\Exceptions\ClientException
     * @throws \DataCue\Exceptions\ExceedBodySizeLimitationException
     * @throws \DataCue\Exceptions\ExceedListDataSizeLimitationException
     * @throws \DataCue\Exceptions\InvalidEnvironmentException
     * @throws \DataCue\Exceptions\NetworkErrorException
     * @throws \DataCue\Exceptions\RetryCountReachedException
     * @throws \DataCue\Exceptions\UnauthorizedException
     */
    public function maybeSyncData()
    {
        $this->initRequest();

        // Check api_key&api_secret
        $this->client->overview->all();

        if (Queue::isActionExisting('init')) {
            return;
        }

        $this->batchCreateProducts();
        $this->batchCreateVariants();
        $this->batchCreateUsers();
        $this->batchCreateOrders();
    }

    /**
     * @throws \DataCue\Exceptions\ClientException
     * @throws \DataCue\Exceptions\ExceedBodySizeLimitationException
     * @throws \DataCue\Exceptions\ExceedListDataSizeLimitationException
     * @throws \DataCue\Exceptions\InvalidEnvironmentException
     * @throws \DataCue\Exceptions\NetworkErrorException
     * @throws \DataCue\Exceptions\RetryCountReachedException
     * @throws \DataCue\Exceptions\UnauthorizedException
     * @throws \PrestaShopDatabaseException
     */
    public function batchCreateProducts()
    {
        $this->log('batchCreateProducts');

        $products = \Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS('
            SELECT `id_product` FROM `' . _DB_PREFIX_ . 'product` ORDER BY `id_product` ASC'
        );
        $productIds = array_map(function ($item) {
            return $item['id_product'];
        }, $products);

        $res = $this->client->overview->products();
        $existingIds = !is_null($res->getData()->ids) ? $res->getData()->ids : [];

        $productIdsList = array_chunk(array_diff($productIds, $existingIds), static::CHUNK_SIZE);

        foreach($productIdsList as $ids) {
            Queue::addJobWithoutModelId('init', 'products', ['ids' => $ids]);
        }
    }

    /**
     * @throws \DataCue\Exceptions\ClientException
     * @throws \DataCue\Exceptions\ExceedBodySizeLimitationException
     * @throws \DataCue\Exceptions\ExceedListDataSizeLimitationException
     * @throws \DataCue\Exceptions\InvalidEnvironmentException
     * @throws \DataCue\Exceptions\NetworkErrorException
     * @throws \DataCue\Exceptions\RetryCountReachedException
     * @throws \DataCue\Exceptions\UnauthorizedException
     * @throws \PrestaShopDatabaseException
     */
    public function batchCreateVariants()
    {
        $this->log('batchCreateVariants');

        $res = $this->client->overview->products();
        $existingIds = !is_null($res->getData()->ids) ? $res->getData()->ids : [];

        if (count($existingIds) === 0) {
            $variants = \Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS('
                SELECT `id_product_attribute` FROM `' . _DB_PREFIX_ . 'product_attribute` ORDER BY `id_product_attribute` ASC'
            );
        } else {
            $variants = \Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS('
                SELECT `id_product_attribute` FROM `' . _DB_PREFIX_ . 'product_attribute` WHERE `id_product` NOT IN (' . join(',', $existingIds) . ') ORDER BY `id_product_attribute` ASC'
            );
        }

        $variantIds = array_map(function ($item) {
            return $item['id_product_attribute'];
        }, $variants);

        $variantIdsList = array_chunk(array_diff($variantIds, $existingIds), static::CHUNK_SIZE);

        foreach($variantIdsList as $ids) {
            Queue::addJobWithoutModelId('init', 'variants', ['ids' => $ids]);
        }
    }

    /**
     * @throws \DataCue\Exceptions\ClientException
     * @throws \DataCue\Exceptions\ExceedBodySizeLimitationException
     * @throws \DataCue\Exceptions\ExceedListDataSizeLimitationException
     * @throws \DataCue\Exceptions\InvalidEnvironmentException
     * @throws \DataCue\Exceptions\NetworkErrorException
     * @throws \DataCue\Exceptions\RetryCountReachedException
     * @throws \DataCue\Exceptions\UnauthorizedException
     * @throws \PrestaShopDatabaseException
     */
    public function batchCreateUsers()
    {
        $this->log('batchCreateUsers');

        $users = \Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS('
            SELECT `id_customer` FROM `' . _DB_PREFIX_ . 'customer` ORDER BY `id_customer` ASC'
        );
        $userIds = array_map(function ($item) {
            return $item['id_customer'];
        }, $users);

        $res = $this->client->overview->users();
        $existingIds = !is_null($res->getData()->ids) ? $res->getData()->ids : [];

        $userIdsList = array_chunk(array_diff($userIds, $existingIds), static::CHUNK_SIZE);

        foreach($userIdsList as $ids) {
            Queue::addJobWithoutModelId('init', 'users', ['ids' => $ids]);
        }
    }

    /**
     * @throws \DataCue\Exceptions\ClientException
     * @throws \DataCue\Exceptions\ExceedBodySizeLimitationException
     * @throws \DataCue\Exceptions\ExceedListDataSizeLimitationException
     * @throws \DataCue\Exceptions\InvalidEnvironmentException
     * @throws \DataCue\Exceptions\NetworkErrorException
     * @throws \DataCue\Exceptions\RetryCountReachedException
     * @throws \DataCue\Exceptions\UnauthorizedException
     * @throws \PrestaShopDatabaseException
     */
    public function batchCreateOrders()
    {
        $this->log('batchCreateOrders');

        $orders = \Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS('
            SELECT `id_order` FROM `' . _DB_PREFIX_ . 'orders` ORDER BY `id_order` ASC'
        );
        $orderIds = array_map(function ($item) {
            return $item['id_order'];
        }, $orders);

        $res = $this->client->overview->orders();
        $existingIds = !is_null($res->getData()->ids) ? $res->getData()->ids : [];

        $orderIdsList = array_chunk(array_diff($orderIds, $existingIds), static::CHUNK_SIZE);

        foreach($orderIdsList as $ids) {
            Queue::addJobWithoutModelId('init', 'orders', ['ids' => $ids]);
        }
    }

    /**
     * @throws \DataCue\Exceptions\ClientException
     * @throws \DataCue\Exceptions\ExceedBodySizeLimitationException
     * @throws \DataCue\Exceptions\ExceedListDataSizeLimitationException
     * @throws \DataCue\Exceptions\InvalidEnvironmentException
     * @throws \DataCue\Exceptions\NetworkErrorException
     * @throws \DataCue\Exceptions\RetryCountReachedException
     * @throws \DataCue\Exceptions\UnauthorizedException
     */
    public function clearClient()
    {
        $this->initRequest();

        $this->client->client->clear();
    }

    /**
     * init request library
     */
    private function initRequest()
    {
        $this->client = new Client(
            $this->apiKey,
            $this->apiSecret,
            ['max_try_times' => static::MAX_TRY_TIMES],
            Utils::isStaging() ? 'development' : 'production'
        );
    }

    /**
     * @param $message
     */
    private function log($message)
    {

    }
}
