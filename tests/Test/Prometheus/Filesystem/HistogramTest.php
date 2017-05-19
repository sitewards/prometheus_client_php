<?php


namespace Test\Prometheus\Filesystem;

use Prometheus\Storage\Filesystem;
use Test\Prometheus\AbstractHistogramTest;

/**
 * See https://prometheus.io/docs/instrumenting/exposition_formats/
 */
class HistogramTest extends AbstractHistogramTest
{
    public function configureAdapter()
    {
        $this->adapter = new Filesystem();
        $this->adapter->flushFilesystem();
    }
}
