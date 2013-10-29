<?php

namespace Git;

/**
 * Autoloads Git classes
 *
 * @package git
 */
class Autoloader
{
    /**
     * Register the autoloader
     *
     * @return  void
     */
    public static function register()
    {
        spl_autoload_register(array(new self, 'autoload'));
    }

    /**
     * Autoloader
     *
     * @param   string
     * @return  mixed
     */
    public static function autoload($class)
    {
        if (0 === stripos($class, 'Git')) {
            $file = preg_replace('{^Git\\\?}', '', $class);
            $file = str_replace('\\', '/', $file);
            $file = realpath(__DIR__ . (empty($file) ? '' : '/') . $file . '.php');
            if (is_file($file)) {
                require_once $file;
                return true;
            }
        }
        return null;
    }
}