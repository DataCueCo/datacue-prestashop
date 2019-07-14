<?php

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
        if(!is_string($message)) {
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
        $fileName = __DIR__ . "/../../datacue-$date.log";

        if (!file_exists($fileName)) {
            static::removeOldLogFile($timestamp);
        }

        return $fileName;
    }

    private static function removeOldLogFile($timestamp)
    {
        $oldTimestamp = $timestamp - static::TEMPORARY_DAYS * 24 * 3600;
        $date = date('Y-m-d', $oldTimestamp);
        $fileName = __DIR__ . "/../../datacue-$date.log";

        if (file_exists($fileName)) {
            unlink($fileName);
        }
    }
}
