<?php
/**
 * MIT License
 * Copyright (c) 2019 DataCue
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 *  @author    DataCue <contact@datacue.co>
 *  @copyright 2019 DataCue
 *  @license   https://opensource.org/licenses/MIT MIT License
 */

namespace DataCue\PrestaShop\Common;

use DataCue\Client;
use DataCue\PrestaShop\Queue;
use DataCue\PrestaShop\Utils;
use DataCue\PrestaShop\Utils\Log;

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
        $this->batchCreateCategories();
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
    public function batchCreateProducts($type = 'init')
    {
        $this->log('batchCreateProducts');

        $products = \Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS(
            'SELECT `id_product` FROM `' . _DB_PREFIX_ . 'product` ORDER BY `id_product` ASC'
        );
        $productIds = array_map(function ($item) {
            return $item['id_product'];
        }, $products);

        if (count($productIds) > 0) {
            if ($type === 'init') {
                $res = $this->client->overview->products();
                $existingIds = !is_null($res->getData()->ids) ? $res->getData()->ids : [];
                $productIdsList = array_chunk(array_diff($productIds, $existingIds), static::CHUNK_SIZE);
            } else {
                $productIdsList = array_chunk($productIds, static::CHUNK_SIZE);
            }

            foreach ($productIdsList as $ids) {
                Queue::addJobWithoutModelId($type, 'products', ['ids' => $ids]);
            }
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
    public function batchCreateVariants($type = 'init')
    {
        $this->log('batchCreateVariants');

        if ($type === 'init') {
            $res = $this->client->overview->products();
            $existingIds = !is_null($res->getData()->ids) ? $res->getData()->ids : [];
        } else {
            $existingIds = [];
        }

        if (count($existingIds) === 0) {
            $variants = \Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS(
                'SELECT `id_product_attribute` FROM `'
                . _DB_PREFIX_ . 'product_attribute` ORDER BY `id_product_attribute` ASC'
            );
        } else {
            $variants = \Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS(
                'SELECT `id_product_attribute` FROM `'
                . _DB_PREFIX_ . 'product_attribute` WHERE `id_product` NOT IN (' . join(',', $existingIds) . ')'
                .'ORDER BY `id_product_attribute` ASC'
            );
        }

        $variantIds = array_map(function ($item) {
            return $item['id_product_attribute'];
        }, $variants);

        if (count($variantIds) > 0) {
            $variantIdsList = array_chunk(array_diff($variantIds, $existingIds), static::CHUNK_SIZE);

            foreach ($variantIdsList as $ids) {
                Queue::addJobWithoutModelId($type, 'variants', ['ids' => $ids]);
            }
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
    public function batchCreateUsers($type = 'init')
    {
        $this->log('batchCreateUsers');

        $users = \Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS(
            'SELECT `id_customer` FROM `' . _DB_PREFIX_ . 'customer` ORDER BY `id_customer` ASC'
        );
        $userIds = array_map(function ($item) {
            return $item['id_customer'];
        }, $users);

        if (count($userIds) > 0) {
            if ($type === 'init') {
                $res = $this->client->overview->users();
                $existingIds = !is_null($res->getData()->ids) ? $res->getData()->ids : [];
                $userIdsList = array_chunk(array_diff($userIds, $existingIds), static::CHUNK_SIZE);
            } else {
                $userIdsList = array_chunk($userIds, static::CHUNK_SIZE);
            }

            foreach ($userIdsList as $ids) {
                Queue::addJobWithoutModelId($type, 'users', ['ids' => $ids]);
            }
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
    public function batchCreateOrders($type = 'init')
    {
        $this->log('batchCreateOrders');

        $orders = \Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS(
            'SELECT `id_order` FROM `' . _DB_PREFIX_ . 'orders` ORDER BY `id_order` ASC'
        );
        $orderIds = array_map(function ($item) {
            return $item['id_order'];
        }, $orders);

        if (count($orderIds) > 0) {
            if ($type === 'init') {
                $res = $this->client->overview->orders();
                $existingIds = !is_null($res->getData()->ids) ? $res->getData()->ids : [];
                $orderIdsList = array_chunk(array_diff($orderIds, $existingIds), static::CHUNK_SIZE);
            } else {
                $orderIdsList = array_chunk($orderIds, static::CHUNK_SIZE);
            }

            foreach ($orderIdsList as $ids) {
                Queue::addJobWithoutModelId($type, 'orders', ['ids' => $ids]);
            }
        }
    }

    /**
     * @param string $type
     * @throws \DataCue\Exceptions\ClientException
     * @throws \DataCue\Exceptions\ExceedBodySizeLimitationException
     * @throws \DataCue\Exceptions\ExceedListDataSizeLimitationException
     * @throws \DataCue\Exceptions\InvalidEnvironmentException
     * @throws \DataCue\Exceptions\NetworkErrorException
     * @throws \DataCue\Exceptions\RetryCountReachedException
     * @throws \DataCue\Exceptions\UnauthorizedException
     * @throws \PrestaShopDatabaseException
     */
    public function batchCreateCategories($type = 'init')
    {
        $this->log('batchCreateCategories');

        $categories = \Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS(
            'SELECT `id_category` FROM `' . _DB_PREFIX_ . 'category` ORDER BY `id_category` ASC'
        );
        $categoryIds = array_map(function ($item) {
            return $item['id_category'];
        }, $categories);

        if (count($categoryIds) > 0) {
            if ($type === 'init') {
                $res = $this->client->overview->categories();
                $existingIds = !is_null($res->getData()->ids) ? $res->getData()->ids : [];
                $categoryIdsList = array_chunk(array_diff($categoryIds, $existingIds), static::CHUNK_SIZE);
            } else {
                $categoryIdsList = array_chunk($categoryIds, static::CHUNK_SIZE);
            }

            foreach ($categoryIdsList as $ids) {
                Queue::addJobWithoutModelId($type, 'categories', ['ids' => $ids]);
            }
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
        Log::info($message);
    }
}
