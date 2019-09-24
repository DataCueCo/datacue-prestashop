<?php

namespace DataCue\PrestaShop\Common;

use Configuration;
use DataCue\Client;
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
    const INTERVAL = 3600;

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
            $lastJobTimestamp = intval($lastJobTimestamp);
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
                    if ($order->getCustomer()->isGuest()) {
                        Queue::addJob('create', 'guest_users', $orderId, ['item' => Order::buildGuestUserForDataCue($order)]);
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
