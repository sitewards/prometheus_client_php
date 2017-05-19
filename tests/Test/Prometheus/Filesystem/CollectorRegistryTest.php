<?php

namespace Test\Prometheus\Filesystem;

use Prometheus\Storage\Filesystem;
use Test\Prometheus\AbstractCollectorRegistryTest;

class CollectorRegistryTest extends AbstractCollectorRegistryTest
{
    public function configureAdapter()
    {
        $this->adapter = new Filesystem();
        $this->adapter->flushFilesystem();
    }
}
