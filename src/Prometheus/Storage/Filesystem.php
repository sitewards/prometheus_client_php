<?php

namespace Prometheus\Storage;

use Prometheus\MetricFamilySamples;
use Prometheus\Exception\StorageException;
use Prometheus\Gauge;
use Prometheus\Histogram;
use Prometheus\RenderTextFormat;

/**
 * This class implements a filesystem adapter for the prometheus metrics storage. It persists metrics to disk in the
 * Prometheus format (so they can be easily inspected), but allows them to be read back and modified by the application.
 *
 * @package Prometheus\Storage
 */
class Filesystem implements Adapter
{
    private static $defaultOptions = array();

    private $options;

    /**
     * The default filename for the metrics to be persisted. The .prom suffix comes from the node exporter's convention
     * for files that can be read from disk.
     *
     * @see https://github.com/prometheus/node_exporter#textfile-collector
     */
    const PROMETHEUS_METRICS_FILENAME = 'php.prom';

    /*
     * @param array $options
     */
    public function __construct(array $options = array())
    {
        // with php 5.3 we cannot initialize the options directly on the field definition
        // so we initialize them here for now
        if (!isset(self::$defaultOptions['path'])) {
            self::$defaultOptions['path'] = sys_get_temp_dir() .
                DIRECTORY_SEPARATOR .
                self::PROMETHEUS_METRICS_FILENAME;
        }

        $this->options = array_merge(self::$defaultOptions, $options);
    }

    /**
     * @param array $options
     */
    public static function setDefaultOptions(array $options)
    {
        self::$defaultOptions = array_merge(self::$defaultOptions, $options);
    }

    public function updateHistogram(array $data)
    {
        // TODO: Implement updateHistogram() method.
    }

    public function updateCounter(array $data)
    {
        // TODO: Implement updateCounter() method.
    }

    public function updateGauge(array $data)
    {
        // TODO: Implement updateGauge() method.
    }

    public function collect()
    {
        return $this->readMetricsFromDisk();
    }

    /**
     * Clears all metrics on disk
     */
    public function flushFilesystem()
    {
        $this->writeMetricsToDisk(array());
    }

    /**
     * Takes the metrics from disk, and converts them into an array object expected by the methods
     */
    private function readMetricsFromDisk()
    {
        return array();
    }

    /**
     * Takes an array of metrics in a defined format, and persists them to disk.
     */
    private function writeMetricsToDisk(array $metrics)
    {
    }

    /**
     * Returns the file handle that will read / write files to disk.
     *
     * @throws StorageException if the file is not writable.
     */
    private function getFile()
    {
    }
}
