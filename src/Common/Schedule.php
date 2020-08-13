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
use DataCue\Core\Response;
use DataCue\PrestaShop\Modules\Category;
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
    const JOBS_EACH_TIME = 1;

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
            $lastJobTimestamp = (int)$lastJobTimestamp;
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

                // handle all kinds of jobs
                // first, find if there's `init` or `reinit` job
                // if found, just do the job and skip other jobs at this time.
                $job = Queue::getNextAliveJobByAction('init');
                if (!empty($job)) {
                    try {
                        $res = $this->doInit($job['model'], $job['job'], $job['action']);
                        $this->updateMultiJobsStatus($res, [$job]);
                    } catch (Exception $e) {
                        Queue::updateJobStatus($job['id_datacue_queue'], static::STATUS_FAILURE);
                    }
                    continue;
                }

                $job = Queue::getNextAliveJobByAction('reinit');
                if (!empty($job)) {
                    try {
                        $res = $this->doInit($job['model'], $job['job'], $job['action']);
                        $this->updateMultiJobsStatus($res, [$job]);
                    } catch (Exception $e) {
                        Queue::updateJobStatus($job['id_datacue_queue'], static::STATUS_FAILURE);
                    }
                    continue;
                }

                // update products
                $jobs = Queue::getAliveJobsByModelAndAction('products', 'update');
                if (!empty($jobs) && count($jobs) > 0) {
                    $items = array_map(function ($job) {
                        return $job['job']->item;
                    }, $jobs);
                    $res = $this->client->products->batchUpdate($items);
                    Log::info('update products response: ' . $res);
                    $this->updateMultiJobsStatus($res, $jobs);
                }

                // delete products
                $jobs = Queue::getAliveJobsByModelAndAction('products', 'delete');
                if (!empty($jobs) && count($jobs) > 0) {
                    $items = array_map(function ($job) {
                        return [
                            'product_id' => $job['job']->productId,
                            'variant_id' => $job['job']->variantId,
                        ];
                    }, $jobs);
                    $res = $this->client->products->batchDelete($items);
                    Log::info('delete products response: ' . $res);
                    $this->updateMultiJobsStatus($res, $jobs);
                }

                // update variants
                $jobs = Queue::getAliveJobsByModelAndAction('variants', 'update');
                if (!empty($jobs) && count($jobs) > 0) {
                    $items = array_map(function ($job) {
                        return $job['job']->item;
                    }, $jobs);
                    $res = $this->client->products->batchUpdate($items);
                    Log::info('update variants response: ' . $res);
                    $this->updateMultiJobsStatus($res, $jobs);
                }

                // delete variants
                $jobs = Queue::getAliveJobsByModelAndAction('variants', 'delete');
                if (!empty($jobs) && count($jobs) > 0) {
                    $items = array_map(function ($job) {
                        return [
                            'product_id' => $job['job']->productId,
                            'variant_id' => $job['job']->variantId,
                        ];
                    }, $jobs);
                    $res = $this->client->products->batchDelete($items);
                    Log::info('delete variants response: ' . $res);
                    $this->updateMultiJobsStatus($res, $jobs);
                }

                // update categories
                $jobs = Queue::getAliveJobsByModelAndAction('categories', 'update');
                if (!empty($jobs) && count($jobs) > 0) {
                    $items = array_map(function ($job) {
                        return $job['job']->item;
                    }, $jobs);
                    $res = $this->client->categories->batchUpdate($items);
                    Log::info('update categories response: ' . $res);
                    $this->updateMultiJobsStatus($res, $jobs);
                }

                // delete categories
                $jobs = Queue::getAliveJobsByModelAndAction('categories', 'delete');
                if (!empty($jobs) && count($jobs) > 0) {
                    $items = array_map(function ($job) {
                        return $job['job']->categoryId;
                    }, $jobs);
                    $res = $this->client->categories->batchDelete($items);
                    Log::info('delete categories response: ' . $res);
                    $this->updateMultiJobsStatus($res, $jobs);
                }

                // update users
                $jobs = Queue::getAliveJobsByModelAndAction('users', 'update');
                if (!empty($jobs) && count($jobs) > 0) {
                    $items = array_map(function ($job) {
                        return $job['job']->item;
                    }, $jobs);
                    $res = $this->client->users->batchUpdate($items);
                    Log::info('update users response: ' . $res);
                    $this->updateMultiJobsStatus($res, $jobs);
                }

                // delete users
                $jobs = Queue::getAliveJobsByModelAndAction('users', 'delete');
                if (!empty($jobs) && count($jobs) > 0) {
                    $items = array_map(function ($job) {
                        return $job['job']->userId;
                    }, $jobs);
                    $res = $this->client->users->batchDelete($items);
                    Log::info('delete users response: ' . $res);
                    $this->updateMultiJobsStatus($res, $jobs);
                }

                // update guest_users
                $jobs = Queue::getAliveJobsByModelAndAction('guest_users', 'update');
                if (!empty($jobs) && count($jobs) > 0) {
                    $items = array_map(function ($job) {
                        return $job['job']->item;
                    }, $jobs);
                    $res = $this->client->users->batchUpdate($items);
                    Log::info('update guest users response: ' . $res);
                    $this->updateMultiJobsStatus($res, $jobs);
                }

                // create orders
                $jobs = Queue::getAliveJobsByModelAndAction('orders', 'create');
                if (!empty($jobs) && count($jobs) > 0) {
                    $items = array_map(function ($job) {
                        return $job['job']->item;
                    }, $jobs);
                    $res = $this->client->orders->batchCreate($items);
                    Log::info('update orders response: ' . $res);
                    $this->updateMultiJobsStatus($res, $jobs);
                }

                // cancel orders
                $jobs = Queue::getAliveJobsByModelAndAction('orders', 'cancel');
                if (!empty($jobs) && count($jobs) > 0) {
                    $items = array_map(function ($job) {
                        return $job['job']->orderId;
                    }, $jobs);
                    $res = $this->client->orders->batchCancel($items);
                    Log::info('cancel orders response: ' . $res);
                    $this->updateMultiJobsStatus($res, $jobs);
                }

                // delete orders
                $jobs = Queue::getAliveJobsByModelAndAction('orders', 'delete');
                if (!empty($jobs) && count($jobs) > 0) {
                    $items = array_map(function ($job) {
                        return $job['job']->orderId;
                    }, $jobs);
                    $res = $this->client->orders->batchDelete($items);
                    Log::info('delete orders response: ' . $res);
                    $this->updateMultiJobsStatus($res, $jobs);
                }

                // events
                $jobs = Queue::getAliveJobsByModelAndAction('events', 'track');
                if (!empty($jobs) && count($jobs) > 0) {
                    $items = array_map(function ($job) {
                        return [
                            'user' => $job['job']->user,
                            'event' => $job['job']->event,
                        ];
                    }, $jobs);
                    $res = $this->client->events->batchTrack($items);
                    Log::info('track events response: ' . $res);
                    $this->updateMultiJobsStatus($res, $jobs);
                }
            }
        } catch (Exception $e) {
            Log::info($e->getMessage());
        }
    }

    /**
     * @param $model
     * @param $job
     * @param $action
     * @return false|Response
     * @throws \DataCue\Exceptions\ClientException
     * @throws \DataCue\Exceptions\ExceedBodySizeLimitationException
     * @throws \DataCue\Exceptions\ExceedListDataSizeLimitationException
     * @throws \DataCue\Exceptions\InvalidEnvironmentException
     * @throws \DataCue\Exceptions\NetworkErrorException
     * @throws \DataCue\Exceptions\RetryCountReachedException
     * @throws \DataCue\Exceptions\UnauthorizedException
     */
    private function doInit($model, $job, $action)
    {
        if ($model === 'products') {
            $items = [];
            $variantIds = [];
            foreach ($job->ids as $id) {
                $product = Product::getProductById($id);
                $combinations = $product->getWsCombinations();
                if (count($combinations) === 0) {
                    $items[] = Product::buildProductForDataCue($product, true);
                } else {
                    $variantIds = array_merge($variantIds, array_map(function ($item) {
                        return $item['id'];
                    }, $combinations));
                }
            }
            if ($action === 'reinit' && count($variantIds) > 0) {
                Queue::addJobWithoutModelId('reinit', 'variants', ['ids' => $variantIds]);
            }
            $res = $this->client->products->batchCreate($items);
            Log::info('batch create products response: ' . $res);
            return $res;
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
            return $res;
        } elseif ($model === 'users') {
            $res = $this->client->users->batchCreate(
                array_map(function ($id) {
                    return User::buildUserForDataCue(User::getUserById($id), true);
                }, $job->ids)
            );
            Log::info('batch create users response: ' . $res);
            return $res;
        } elseif ($model === 'orders') {
            $guestData = [];
            $orderData = [];
            foreach ($job->ids as $id) {
                $order = Order::getOrderById($id);
                if (Order::isEmailGuestOrder($order)) {
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
            return $res;
        } elseif ($model === 'categories') {
            $data = [];
            foreach ($job->ids as $id) {
                $category = Category::getCategoryById($id);
                $data[] = Category::buildCategoryForDataCue($category, true);
            }
            $res = $this->client->categories->batchCreate($data);
            Log::info('batch create categories response: ' . $res);
            return $res;
        }
    }

    private function updateMultiJobsStatus(Response $res, $jobs)
    {
        $jobIds = array_map(function ($job) {
            return $job['id_datacue_queue'];
        }, $jobs);

        if ($res->getHttpCode() >= 200 && $res->getHttpCode() < 300) {
            Queue::updateMultiJobsStatus($jobIds, static::STATUS_SUCCESS);
        } elseif ($res->getHttpCode() >= 500) { //retry due to temporary server issue
            Queue::updateMultiJobsStatus($jobIds, static::STATUS_NONE);
        } else {
            Queue::updateMultiJobsStatus($jobIds, static::STATUS_FAILURE);
        }
    }
}
