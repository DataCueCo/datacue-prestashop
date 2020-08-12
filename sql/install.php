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

$sql = array();

$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'datacue_queue` (
    `id_datacue_queue` int(11) NOT NULL AUTO_INCREMENT,
    `action` varchar(32) NOT NULL,
    `model` varchar(32) NOT NULL,
    `model_id` int(11) NOT NULL DEFAULT 0,
    `job` mediumtext NOT NULL,
    `status` int(11) NOT NULL DEFAULT 0,
    `executed_at` datetime DEFAULT NULL,
    `created_at` datetime NOT NULL,
    PRIMARY KEY  (`id_datacue_queue`),
    UNIQUE KEY `unique_job` (`action`, `model`, `model_id`, `status`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

foreach ($sql as $query) {
    if (Db::getInstance()->execute($query) == false) {
        return false;
    }
}
