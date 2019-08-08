<?php

namespace DataCue\PrestaShop\Common;

use Configuration;
use DataCue\PrestaShop\Modules\Order;
use DataCue\PrestaShop\Modules\Product;
use DataCue\PrestaShop\Modules\User;
use DataCue\PrestaShop\Modules\Variant;
use DataCue\PrestaShop\Utils;
use Exception;
use DataCue\PrestaShop\Queue;
use DataCue\Client;
use DataCue\PrestaShop\Utils\Log;

/**
 * Class Schedule
 * @package DataCue\PrestaShop\Common
 */
class Schedule
{
    /**
     * chunk size of each package
     */
    const CHUNK_SIZE = 200;

    /**
     * Interval between two cron job.
     */
    const INTERVAL = 20;

    /**
     * Process job count each time.
     */
    const JOBS_EACH_TIME = 3;

    /**
     * Task status after initial
     */
    const STATUS_NONE = 0;

    /**
     * Task status for pending
     */
    const STATUS_PENDING = 1;

    /**
     * Task status for success
     */
    const STATUS_SUCCESS = 2;

    /**
     * Task status for failure
     */
    const STATUS_FAILURE = 3;

    /**
     * Max try times
     */
    const MAX_TRY_TIMES = 3;

    /**
     * @var \DataCue\Client
     */
    private $client;

    /**
     *
     */
    public function maybeScheduleCron()
    {
        $now = time();
        $lastJobTimestamp = Configuration::get('DATACUE_PRESTASHOP_LAST_JOB_TIMESTAMP', null);
        if ($lastJobTimestamp === false) {
            $lastJobTimestamp = 0;
        } else {
            $lastJobTimestamp = intval($lastJobTimestamp);
        }

        if ($now - $lastJobTimestamp >= static::INTERVAL) {
            $this->executeCron();
            Configuration::updateValue('DATACUE_PRESTASHOP_LAST_JOB_TIMESTAMP', "$now");
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
            for ($i = 0; $i < static::JOBS_EACH_TIME; $i++) {
                Log::info('executeCron');
                $job = Queue::getNextAliveJob();
                if (!$job) {
                    return;
                }
                
                if ($job['action'] === 'init') {
                    $this->doInit($job['model'], $job['job']);
                } else {
                    switch ($job['model']) {
                        case 'products':
                            $this->doProductsJob($job['action'], $job['job']);
                            break;
                        case 'variants':
                            $this->doVariantsJob($job['action'], $job['job']);
                            break;
                        case 'users':
                            $this->doUsersJob($job['action'], $job['job']);
                            break;
                        case 'guest_users':
                            $this->doUsersJob($job['action'], $job['job']);
                            break;
                        case 'orders':
                            $this->doOrdersJob($job['action'], $job['job']);
                            break;
                        case 'events':
                            $this->doEventJob($job['action'], $job['job']);
                            break;
                        default:
                            break;
                    }
                }
                Queue::updateJobStatus($job['id_datacue_queue'], static::STATUS_SUCCESS);
            }
        } catch (Exception $e) {
            Log::info($e->getMessage());
            Queue::updateJobStatus($job['id_datacue_queue'], static::STATUS_FAILURE);
        }
    }

    /**
     * @param $model
     * @param $job
     * @throws \DataCue\Exceptions\ClientException
     * @throws \DataCue\Exceptions\ExceedBodySizeLimitationException
     * @throws \DataCue\Exceptions\ExceedListDataSizeLimitationException
     * @throws \DataCue\Exceptions\InvalidEnvironmentException
     * @throws \DataCue\Exceptions\NetworkErrorException
     * @throws \DataCue\Exceptions\RetryCountReachedException
     * @throws \DataCue\Exceptions\UnauthorizedException
     */
    private function doInit($model, $job)
    {
        if ($model === 'products') {
            $items = [];
            foreach ($job->ids as $id) {
                $product = Product::getProductById($id);
                $combinations = $product->getWsCombinations();
                if (count($combinations) === 0) {
                    $items[] = Product::buildProductForDataCue($product, true);
                }
            }
            $res = $this->client->products->batchCreate($items);
            Log::info('batch create products response: ' . $res);
        } elseif ($model === 'variants') {
            $items = [];
            foreach ($job->ids as $id) {
                $item = Variant::buildVariantForDataCue(Variant::getVariantById($id), null, true);
                if (!is_null($item)) {
                    $items[] = $item;
                }
            }
            $res = $this->client->products->batchCreate($items);
            Log::info('batch create variants response: ' . $res);
        } elseif ($model === 'users') {
            $res = $this->client->users->batchCreate(
                array_map(function ($id) {
                    return User::buildUserForDataCue(User::getUserById($id), true);
                }, $job->ids)
            );
            Log::info('batch create users response: ' . $res);
        } elseif ($model === 'orders') {
            $guestData = [];
            $orderData = [];
            foreach ($job->ids as $id) {
                $order = Order::getOrderById($id);
                if ($order->getCustomer()->isGuest()) {
                    $existing = false;
                    foreach ($guestData as $guest) {
                        if ($guest['user_id'] === $order->getCustomer()->email) {
                            $existing = true;
                            break;
                        }
                    }
                    if (!$existing) {
                        $guestData[] = Order::buildGuestUserForDataCue($order);
                    }
                }
                $orderData[] = Order::buildOrderForDataCue($order, null, true);
            }
            if (count($guestData) > 0) {
                $res = $this->client->users->batchCreate($guestData);
                Log::info('batch create guest users response: ' . $res);
            }
            $res = $this->client->orders->batchCreate($orderData);
            Log::info('batch create orders response: ' . $res);
        }
    }

    /**
     * @param $action
     * @param $job
     * @throws \DataCue\Exceptions\ClientException
     * @throws \DataCue\Exceptions\ExceedBodySizeLimitationException
     * @throws \DataCue\Exceptions\ExceedListDataSizeLimitationException
     * @throws \DataCue\Exceptions\InvalidEnvironmentException
     * @throws \DataCue\Exceptions\NetworkErrorException
     * @throws \DataCue\Exceptions\RetryCountReachedException
     * @throws \DataCue\Exceptions\UnauthorizedException
     */
    private function doProductsJob($action, $job)
    {
        switch ($action) {
            case 'create':
                $res = $this->client->products->create($job->item);
                Log::info('create variant response: ' . $res);
                break;
            case 'update':
                $res = $this->client->products->update($job->productId, $job->variantId, $job->item);
                Log::info('update product response: ' . $res);
                break;
            case 'delete':
                if ($job->variantId) {
                    $res = $this->client->products->delete($job->productId, $job->variantId);
                    Log::info('delete variant response: ' . $res);
                } else {
                    $res = $this->client->products->delete($job->productId);
                    Log::info('delete product response: ' . $res);
                }
                break;
            default:
                break;
        }
    }

    /**
     * @param $action
     * @param $job
     * @throws \DataCue\Exceptions\ClientException
     * @throws \DataCue\Exceptions\ExceedBodySizeLimitationException
     * @throws \DataCue\Exceptions\ExceedListDataSizeLimitationException
     * @throws \DataCue\Exceptions\InvalidEnvironmentException
     * @throws \DataCue\Exceptions\NetworkErrorException
     * @throws \DataCue\Exceptions\RetryCountReachedException
     * @throws \DataCue\Exceptions\UnauthorizedException
     */
    private function doVariantsJob($action, $job)
    {
        $this->doProductsJob($action, $job);
    }

    /**
     * @param $action
     * @param $job
     * @throws \DataCue\Exceptions\ClientException
     * @throws \DataCue\Exceptions\ExceedBodySizeLimitationException
     * @throws \DataCue\Exceptions\ExceedListDataSizeLimitationException
     * @throws \DataCue\Exceptions\InvalidEnvironmentException
     * @throws \DataCue\Exceptions\NetworkErrorException
     * @throws \DataCue\Exceptions\RetryCountReachedException
     * @throws \DataCue\Exceptions\UnauthorizedException
     */
    private function doUsersJob($action, $job)
    {
        switch ($action) {
            case 'create':
                $res = $this->client->users->create($job->item);
                Log::info('create user response: ' . $res);
                break;
            case 'update':
                $res = $this->client->users->update($job->userId, $job->item);
                Log::info('update user response: ' . $res);
                break;
            case 'delete':
                $res = $this->client->users->delete($job->userId);
                Log::info('delete user response: ' . $res);
                break;
            default:
                break;
        }
    }

    /**
     * @param $action
     * @param $job
     * @throws \DataCue\Exceptions\ClientException
     * @throws \DataCue\Exceptions\ExceedBodySizeLimitationException
     * @throws \DataCue\Exceptions\ExceedListDataSizeLimitationException
     * @throws \DataCue\Exceptions\InvalidEnvironmentException
     * @throws \DataCue\Exceptions\NetworkErrorException
     * @throws \DataCue\Exceptions\RetryCountReachedException
     * @throws \DataCue\Exceptions\UnauthorizedException
     */
    private function doOrdersJob($action, $job)
    {
        switch ($action) {
            case 'create':
                $res = $this->client->orders->create($job->item);
                Log::info('create order response: ', $res);
                break;
            case 'cancel':
                $res = $this->client->orders->cancel($job->orderId);
                Log::info('cancel order response: ', $res);
                break;
            case 'delete':
                $res = $this->client->orders->delete($job->orderId);
                Log::info('delete order response: ', $res);
                break;
            default:
                break;
        }
    }

    /**
     * @param $action
     * @param $job
     * @throws \DataCue\Exceptions\ClientException
     * @throws \DataCue\Exceptions\ExceedBodySizeLimitationException
     * @throws \DataCue\Exceptions\ExceedListDataSizeLimitationException
     * @throws \DataCue\Exceptions\InvalidEnvironmentException
     * @throws \DataCue\Exceptions\NetworkErrorException
     * @throws \DataCue\Exceptions\RetryCountReachedException
     * @throws \DataCue\Exceptions\UnauthorizedException
     */
    private function doEventJob($action, $job)
    {
        switch ($action) {
            case 'track':
                $res = $this->client->events->track($job->user, $job->event);
                Log::info('track event response: ', $res);
                break;
            default:
                break;
        }
    }
}
