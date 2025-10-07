<?php

/**
 * Stream Video
 * php version 7
 *
 * @category  StreamVideo
 * @package   StreamVideo
 * @author    Ramesh N Jangid <polygon.co.in@gmail.com>
 * @copyright 2025 Ramesh N Jangid
 * @license   MIT https://opensource.org/license/mit
 * @link      https://github.com/polygoncoin/Stream-Video
 * @since     Class available since Release 1.0.0
 */

namespace StreamVideo;

/**
 * Autoload
 * php version 7
 *
 * @category  Autoload
 * @package   StreamVideo
 * @author    Ramesh N Jangid <polygon.co.in@gmail.com>
 * @copyright 2025 Ramesh N Jangid
 * @license   MIT https://opensource.org/license/mit
 * @link      https://github.com/polygoncoin/Stream-Video
 * @since     Class available since Release 1.0.0
 */
class Autoload
{
    /**
     * Autoload Register function
     *
     * @param string $className Class name
     *
     * @return void
     */
    public static function register($className): void
    {
        $className = str_replace(
            search: "\\",
            replace: DIRECTORY_SEPARATOR,
            subject: $className
        );
        $file = __DIR__ . DIRECTORY_SEPARATOR . $className . '.php';
        if (!file_exists(filename: $file)) {
            echo PHP_EOL . "File '{$file}' missing" . PHP_EOL;
        }
        include_once $file;
    }
}
