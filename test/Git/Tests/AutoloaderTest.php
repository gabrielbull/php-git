<?php

namespace Git\Tests;
use PHPUnit_Framework_TestCase;
use Git;
use Git\Autoloader;

class AutoloaderTest extends PHPUnit_Framework_TestCase
{
    public function testAutoload()
    {
        $this->assertNull(Autoloader::autoload('Foo'), 'Git\\Autoloader::autoload() is trying to load classes outside of the Git namespace');
        $this->assertTrue(Autoloader::autoload('Git'), 'Git\\Autoloader::autoload() failed to autoload the Git class');
    }
}
