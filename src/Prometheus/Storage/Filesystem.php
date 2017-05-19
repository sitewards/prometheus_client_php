<?php

namespace Prometheus\Storage;

use Prometheus\MetricFamilySamples;
use Prometheus\Exception\StorageException;
use Prometheus\Gauge;
use Prometheus\Histogram;
use Prometheus\ParseTextFormat;
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
     * @var ParseTextFormat
     */
    private $parser;

    /**
     * The default filename for the metrics to be persisted. The .prom suffix comes from the node exporter's convention
     * for files that can be read from disk.
     *
     * @see https://github.com/prometheus/node_exporter#textfile-collector
     */
    const PROMETHEUS_METRICS_FILENAME = 'php.prom';

    /**
     * Designed for namespacing keys in key => value pairs.
     */
    const PROMETHEUS_PREFIX = 'prom';

    const FILE_MODE_WRITE = 'w';
    const FILE_MODE_READ = 'r';

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
        $this->parser   = new ParseTextFormat();
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
        // Todo: This will all need to be pulled out into a "getMetric" or something. Manipulating the blob for each
        // metric is too messy.

        $typeKey     = $this->typeKey($dataToStore);
        $valueKey    = $this->valueKey($dataToStore);
        $currentMetric = array_filter(
            $metrics,
            function ($family) use ($dataToStore) {
                /** @var $family MetricFamilySamples */
                if ($family->getName() === $dataToStore['name']) {
                    return true;
                }

                return false;
            }
        );

        $metricData = array(
            'name'       => $dataToStore['name'],
            'help'       => $dataToStore['help'],
            'type'       => $dataToStore['type'],
            'labelNames' => $dataToStore['labelNames']
        );

        if ($currentMetric) {
            /** @var MetricFamilySamples $currentMetric */
            $currentMetric = array_shift($currentMetric);

            /** @var \Prometheus\Sample[] $samples */
            $samples = $currentMetric->getSamples();
            foreach ($samples as $sampleObject) {
                $sample = array(
                    'name' => $sampleObject->getName(),
                    'labelNames' => array(),
                    'labelValues' => $sampleObject->getLabelValues(),
                    'value' => $sampleObject->getValue()
                );

                $metricData['samples'][$this->valueKey($sample)] = $sample;
            }
        }

        $sample = array(
            'name'        => $dataToStore['name'],
            'labelNames'  => array(),
            'labelValues' => $dataToStore['labelValues'],
            'value'       => $dataToStore['value']
        );

        $metricData['samples'][$this->valueKey($sample)] = $sample;

        $gauges = new MetricFamilySamples($metricData);

        $metrics = array_merge($metrics, array($typeKey => $gauges));

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
        $handle   = $this->getFileHandle(self::FILE_MODE_READ);

        // The results of previous filesize checks in this file will be cached by PHP, and will influence the amount of
        // content read by PHP the second filesize check. We are deliberately persisting the metrics to disk multiple
        // times in a request, so as not to lose the content.
        //
        // Thus, we need to clear the stat cache and ensure the filesize lookup is fresh reach read.
        //
        // See http://php.net/manual/en/function.filesize.php#refsect1-function.filesize-notes
        clearstatcache();

        $filesize = filesize($this->options['path']);

        if ($filesize === 0) {
            return array();
        }

        $contents   = fread($handle, filesize($this->options['path']));
        $metrics    = $this->parser->parse($contents);
        $keyMetrics = array();

        // Add keys to the metrics.
        // Todo: This needs thinking about; this is not a good place for it.
        foreach ($metrics as $metric) {
            /** @var MetricFamilySamples $metric */
            $key = $this->typeKey(
                array(
                    'type' => $metric->getType(),
                    'name' => $metric->getName(),
                    'labelNames' => $metric->getLabelNames()
                )
            );

            $keyMetrics[$key] = $metric;
        }

        return $keyMetrics;
    }

    /**
     * Takes an array of MetricFamilySamples objects, and persists them to disk.
     *
     * @param $metrics
     */
    private function writeMetricsToDisk(array $metrics)
    {
        // Sort the metrics.
        usort(
            $metrics,
            function ($element) {
                $foo = $element;
            }
        );

        $content = $this->renderer->render($metrics);

        fwrite($this->getFileHandle(self::FILE_MODE_WRITE), $content);
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
    private function getFileHandle($mode)
    {

        $handle = fopen($this->options['path'], $mode);

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
     * Calculates a key for use in storing a series of MetricFamilySamples, and being able to look up which samples
     * exists. Expects an array of the form
     *
     * array(
     *   'type' => 'gauge',
     *   'name' => 'foo_metric_name',
     *   'labelNames' => array(
     *     'foo'
     *   )
     *
     * @param array $data
     *
     * @return string
     */
    private function typeKey(array $data)
    {
        return implode(
            ':',
            array(
                self::PROMETHEUS_PREFIX,
                $data['type'],
                $data['name'],
                json_encode($data['labelNames'])
            )
        );
    }

    /**
     * Calculates a key for use in storing a series of Samples, and being able to look up which samples exist. Expects
     * an array of the form:
     *
     * array(
     *   'name' => 'foo_metric_name',
     *   'labelValues' => array(
     *      'bar'
     *   ),
     *
     * @param array $data
     * @return string
     */
    private function valueKey(array $data)
    {
        return implode(
            ':',
            array(
                self::PROMETHEUS_PREFIX,
                $data['name'],
                json_encode($data['labelValues'])
            )
        );
    }
}
