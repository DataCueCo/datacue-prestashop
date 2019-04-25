<?php

namespace DataCue\PrestaShop;

class Utils
{
    public static function isStaging()
    {
        return file_exists(__DIR__ . '/../staging');
    }

    public static function baseURL()
    {
        return sprintf(
            "%s://%s",
            isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off' ? 'https' : 'http',
            $_SERVER['HTTP_HOST']
        );
    }
}
