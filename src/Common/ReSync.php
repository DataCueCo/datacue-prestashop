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

use Configuration;
use DataCue\Client;
use DataCue\PrestaShop\Modules\Category;
use DataCue\PrestaShop\Utils\Log;
use DataCue\PrestaShop\Queue;
use DataCue\PrestaShop\Modules\User;
use DataCue\PrestaShop\Modules\Product;
use DataCue\PrestaShop\Modules\Variant;
use DataCue\PrestaShop\Modules\Order;
use DataCue\PrestaShop\Utils;

class ReSync
{
    /**
     * Interval between two cron job.
     */
    const INTERVAL = 900;

    /**
     * Max try times
     */
    const MAX_TRY_TIMES = 3;

    /**
     * @var \DataCue\Client
     */
    private $client;

    /**
     * @var Initializer
     */
    private $initializer = null;

    /**
     *
     */
    public function maybeScheduleCron()
    {
        $now = time();
        $lastJobTimestamp = Configuration::get('DATACUE_PRESTASHOP_LAST_RESYNC_TIMESTAMP', null);
        if ($lastJobTimestamp === false) {
            $lastJobTimestamp = 0;
        } else {
            $lastJobTimestamp = (int)$lastJobTimestamp;
        }

        if ($now - $lastJobTimestamp >= static::INTERVAL) {
            $this->executeCron();
            Configuration::updateValue('DATACUE_PRESTASHOP_LAST_RESYNC_TIMESTAMP', "$now");
        }
    }

    /**
     *
     */
    private function executeCron()
    {
        $apiKey = Configuration::get('DATACUE_PRESTASHOP_API_KEY', null);
        $apiSecret = Configuration::get('DATACUE_PRESTASHOP_API_SECRET', null);
        if (!$apiKey || !$apiSecret) {
            return;
        }

        $this->client = new Client(
            $apiKey,
            $apiSecret,
            ['max_try_times' => static::MAX_TRY_TIMES],
            Utils::isStaging() ? 'development' : 'production'
        );

        try {
            $res = $this->client->client->sync();
            Log::info('get resync info: ' . $res);
            $data = $res->getData();
            if (property_exists($data, 'users')) {
                $this->executeUsers($data->users);
            }
            if (property_exists($data, 'categories')) {
                $this->executeCategories($data->categories);
            }
            if (property_exists($data, 'products')) {
                $this->executeProducts($data->products);
            }
            if (property_exists($data, 'orders')) {
                $this->executeOrders($data->orders);
            }
        } catch (\Exception $e) {
            Log::info($e->getMessage());
        }
    }

    private function executeUsers($data)
    {
        if (is_null($data)) {
            return;
        }

        if ($data === 'full') {
            Queue::addJobWithoutModelId('delete_all', 'users', []);
            $this->getInitializer()->batchCreateUsers('reinit');
        } elseif (is_array($data)) {
            foreach ($data as $userId) {
                Queue::addJob('delete', 'users', $userId, ['userId' => $userId]);
                $user = User::getUserById($userId);
                if (empty($user) || empty($user->id)) {
                    continue;
                }
                Queue::addJob(
                    'update',
                    'users',
                    $userId,
                    [
                        'userId' => $userId,
                        'item' => User::buildUserForDataCue($user, false),
                    ]
                );
            }
        }
    }

    private function executeCategories($data)
    {
        if (is_null($data)) {
            return;
        }

        if ($data === 'full') {
            Queue::addJobWithoutModelId('delete_all', 'categories', []);
            $this->getInitializer()->batchCreateCategories('reinit');
        } elseif (is_array($data)) {
            foreach ($data as $categoryId) {
                Queue::addJob('delete', 'categories', $categoryId, ['categoryId' => $categoryId]);
                $category = Category::getCategoryById($categoryId);
                if (empty($category) || empty($category->id)) {
                    continue;
                }
                Queue::addJob(
                    'create',
                    'categories',
                    $categoryId,
                    [
                        'categoryId' => $categoryId,
                        'item' => Category::buildCategoryForDataCue($category, true),
                    ]
                );
            }
        }
    }

    private function executeProducts($data)
    {
        if (is_null($data)) {
            return;
        }

        if ($data === 'full') {
            Queue::addJobWithoutModelId('delete_all', 'products', []);
            $this->getInitializer()->batchCreateProducts('reinit');
        } elseif (is_array($data)) {
            foreach ($data as $productId) {
                Queue::addJob('delete', 'products', $productId, ['productId' => $productId, 'variantId' => null]);
                $product = Product::getProductById($productId);
                if (empty($product) || empty($product->id)) {
                    continue;
                }
                $combinations = $product->getWsCombinations();
                if (count($combinations) > 0) {
                    foreach ($combinations as $item) {
                        $variantId = $item['id'];
                        $combination = Variant::getVariantById($variantId);
                        $item = Variant::buildVariantForDataCue($combination, $product, true);
                        Queue::addJob(
                            'create',
                            'variants',
                            $variantId,
                            ['productId' => $productId, 'variantId' => $variantId, 'item' => $item]
                        );
                    }
                } else {
                    $item = Product::buildProductForDataCue($product, true);
                    Queue::addJob(
                        'create',
                        'products',
                        $productId,
                        ['productId' => $productId, 'variantId' => 'no-variants', 'item' => $item]
                    );
                }
            }
        }
    }

    private function executeOrders($data)
    {
        if (is_null($data)) {
            return;
        }

        if ($data === 'full') {
            Queue::addJobWithoutModelId('delete_all', 'orders', []);
            $this->getInitializer()->batchCreateOrders('reinit');
        } elseif (is_array($data)) {
            foreach ($data as $orderId) {
                $order = Order::getOrderById($orderId);
                if (empty($order) || empty($order->id)) {
                    continue;
                }
                $item = Order::buildOrderForDataCue($order, null, true);
                if (!is_null($item)) {
                    if (Order::isEmailGuestOrder($order)) {
                        Queue::addJob(
                            'create',
                            'guest_users',
                            $orderId,
                            ['item' => Order::buildGuestUserForDataCue($order)]
                        );
                    }
                    Queue::addJob('create', 'orders', $orderId, ['item' => $item]);
                }
            }
        }
    }

    private function getInitializer()
    {
        if (is_null($this->initializer)) {
            $this->initializer = new Initializer(
                Configuration::get('DATACUE_PRESTASHOP_API_KEY', null),
                Configuration::get('DATACUE_PRESTASHOP_API_SECRET', null)
            );
        }

        return $this->initializer;
    }
}
