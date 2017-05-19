<?php


namespace Test\Prometheus\Filesystem;

use Prometheus\Storage\Filesystem;
use Test\Prometheus\AbstractCounterTest;

/**
 * See https://prometheus.io/docs/instrumenting/exposition_formats/
 */
class CounterTest extends AbstractCounterTest
{

    public function configureAdapter()
    {
        $this->adapter = new Filesystem();
        $this->adapter->flushFilesystem();
    }
}
