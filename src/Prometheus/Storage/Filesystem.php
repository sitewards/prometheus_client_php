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
     * @var RenderTextFormat;
     */
    private $renderer;

    /**
     * The default filename for the metrics to be persisted. The .prom suffix comes from the node exporter's convention
     * for files that can be read from disk.
     *
     * @see https://github.com/prometheus/node_exporter#textfile-collector
     */
    const PROMETHEUS_METRICS_FILENAME = 'php.prom';

    /**
     * The same as the file resource modifiers used in PHP. Duplicated here for clarity, as there are no PHP constants
     *
     * @see http://php.net/manual/en/function.fopen.php
     */
    const FILE_MODE_READ  = 'r';
    const FILE_MODE_WRITE = 'w';

    /**
     * Designed for namespacing keys in key => value pairs.
     */
    const PROMETHEUS_PREFIX = 'prom';

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

        $this->renderer = new RenderTextFormat();
        $this->options  = array_merge(self::$defaultOptions, $options);
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

    /**
     * Takes an array of data of the form
     *
     * array(
     *   name => <string> "test_some_metric",
     *   help => <string> "This is for testing",
     *   type => <string> "gauge",
     *   labelNames => array(
     *     <string> "foo"
     *   ),
     *   labelValues => array(
     *     <string> "bbb"
     *   ),
     *   "value" => <int> 35,
     *   // The type of action to perform on the data store. See self::COMMAND_*
     *   "command" => <int> 3
     *
     * @todo: These need to be atomic. See the APCu adapter
     *
     * @param array $dataToStore
     * @return void;
     */
    public function updateGauge(array $dataToStore)
    {

        // Todo: Need to add or update it based on the command entry

        $metrics = $this->readMetricsFromDisk();
        $key     = $this->storageKey($dataToStore);

        // Todo: This metricData stuff is probably going to need ot be parsed form disk also
        $metricData = array(
            'name'       => $dataToStore['name'],
            'help'       => $dataToStore['help'],
            'type'       => $dataToStore['type'],
            'labelNames' => $dataToStore['labelNames']
        );

        $metricData['samples'][] = array(
            'name'        => $dataToStore['name'],
            'labelNames'  => array(),
            'labelValues' => $dataToStore['labelValues'],
            'value'       => $dataToStore['value']
        );

        $gauges = new MetricFamilySamples($metricData);

        $metrics = array_merge($metrics, array($key => $gauges));

        $this->writeMetricsToDisk($metrics);
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
     * Takes the metrics from disk, and converts them into an an array of MetricFamilySamples objects, expected
     * by the rest of the stack.
     */
    private function readMetricsFromDisk()
    {
        $handle   = $this->getFileHandle();
        $filesize = filesize($this->options['path']);

        if ($filesize === 0) {
            return array();
        }

        $contents = fread($handle, filesize($this->options['path']));

        // Todo: Implement the parser here. Needs to generate an array of MetricFamilySamples
        return array();
    }

    /**
     * Takes an array of MetricFamilySamples objects, and persists them to disk.
     *
     * @param $metrics
     */
    private function writeMetricsToDisk(array $metrics)
    {
        $content = $this->renderer->render($metrics);

        fwrite($this->getFileHandle(), $content);
    }

    /**
     * Returns the file handle that will read / write files to disk. Does not keep the file open in a persistent way so
     * that the file write behaviours of "read only" or "write but truncate" more accurately represent the state of
     * persisting metrics to disk, and so that if the file is deleted part of the way through the request (such as
     * cleaning of /tmp/ during a long running job) the application does not crash.)
     *
     * @throws StorageException if the file is not writable.
     * @return resource;
     */
    private function getFileHandle()
    {
        $handle = fopen($this->options['path'], 'w+');

        if ($handle === false) {
            throw new StorageException(
                sprintf(
                    'Unable to open the path "%s" to write metrics with the filesystem adapter',
                    $this->options['path']
                )
            );
        }

        return $handle;
    }

    /**
     * @param array $data
     * @return string
     */
    private function storageKey(array $data)
    {
        return implode(
            ':',
            array(
                self::PROMETHEUS_PREFIX,
                $data['type'],
                $data['name'],
                json_encode($data['labelValues']),
                'value'
            )
        );
    }
}
