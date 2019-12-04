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

namespace DataCue\PrestaShop\Utils;

/**
 * Class Log
 * @package DataCue\MagentoModule\Utils
 */
class Log
{
    const TEMPORARY_DAYS = 3;

    /**
     * write logs
     * @param $message
     */
    public static function info($message)
    {
        if (!is_string($message)) {
            $message = json_encode($message);
        }
        $file = fopen(static::getLogFile(), "a");
        fwrite($file, date('Y-m-d H:i:s') . " :: " . $message . "\n");
        fclose($file);
    }

    private static function getLogFile()
    {
        $timestamp = time();
        $date = date('Y-m-d', $timestamp);
        $fileName = dirname(__FILE__) . "/../../datacue-$date.log";

        if (!file_exists($fileName)) {
            static::removeOldLogFile($timestamp);
        }

        return $fileName;
    }

    private static function removeOldLogFile($timestamp)
    {
        $oldTimestamp = $timestamp - static::TEMPORARY_DAYS * 24 * 3600;
        $date = date('Y-m-d', $oldTimestamp);
        $fileName = dirname(__FILE__) . "/../../datacue-$date.log";

        if (file_exists($fileName)) {
            unlink($fileName);
        }
    }
}
