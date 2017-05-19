<?php


namespace Test\Prometheus\Filesystem;

use Prometheus\Storage\Filesystem;
use Test\Prometheus\AbstractGaugeTest;

/**
 * See https://prometheus.io/docs/instrumenting/exposition_formats/
 */
class GaugeTest extends AbstractGaugeTest
{

    public function configureAdapter()
    {
        $this->adapter = new Filesystem();
        $this->adapter->flushFilesystem();
    }
}
