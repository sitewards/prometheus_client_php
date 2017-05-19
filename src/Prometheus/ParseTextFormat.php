<?php

namespace Prometheus;

use Prometheus\Exception\ParseException;

/**
 * Responsible for reading the text format from TXT input, and parsing it into an array of metrics suitable for
 * incrementing.
 *
 * @package Prometheus
 */
class ParseTextFormat
{
    /**
     * A list of the acceptable meta directives understood by the parser
     *
     * @var array
     */
    private $metaTypes = array(
        'HELP',
        'TYPE'
    );

    /**
     * Takes a text format of metrics of the form:
     *
     * # HELP test_some_metric this is for testing
     * # TYPE test_some_metric gauge
     * test_some_metric{foo="bbb"} 35
     *
     * and returns an array of MetricFamilySamples. The prometheus format only requires the foo_bar_baz = "1" set of
     * values, with the rest of the content being optional (such as labels, type and help). Comments (# without "HELP"
     * or "TYPE" are deliberately discarded.
     *
     * The format is strict, including whitespace.
     *
     * @param $text
     *
     * @return array|MetricFamilySamples[]
     */
    public function parse($text)
    {
        // Clean text. Prevents a case where a line ending is the only content in the file. Uses a custom character list
        // as the default character list includes the "#" character.
        $text = trim($text, " \r\n");

        if (strlen($text) === 0) {
            return array();
        }

        $lines = explode(PHP_EOL, $text);

        // Metadata is compiled separately, as it can be pushed to multiple metric samples
        $metrics       = array();
        $metadata      = array();
        $familySamples = array();

        foreach ($lines as $line) {
            // Determine if it's a metadata reference
            if (substr($line, 0, 1) === '#') {
                $metadata = array_merge_recursive($metadata, $this->getMeta($line));
            } else {
                // If the record is malformed (for example, if a write to the file did not complete during the last
                // request) this will throw an exception as it attempts to parse. We drop this exception delibrately, as
                // metric collecting should not block a request.
                try {
                    $metric  = $this->getMetric($line);
                    $metrics = array_merge_recursive($metrics, $metric);
                } catch (\Exception $e) {
                    // Exception is discarded. Ideally, this would be logged, but there is no logging interface for this
                    // library to tap into.
                }
            }
        }

        // At this point, we do not know how many metrics have how many series, and there is no way to "push" the series
        // onto the MetricFamilySamples object. So, we must first compile our own primitive in an associative array,
        // then use that primitive to build MetricFamilySamples
        foreach ($metrics as $name => $data) {
            if (!isset($familySamples[$name])) {
                $sample = $data[0];

                $familySamples[$name] = array(
                    'name'       => $name,
                    'help'       => isset($metadata[$name]['help']) ? $metadata[$name]['help'] : '',
                    'type'       => isset($metadata[$name]['type']) ? $metadata[$name]['type'] : '',
                    'labelNames' => $sample['labels'],
                    'samples'    => array()
                );
            }

            foreach ($data as $sample) {
                $familySamples[$name]['samples'][] = array(
                    'name'        => $name,
                    'labelNames'  => array(),
                    'labelValues' => $sample['labelValues'],
                    'value'       => $sample['value']
                );
            }
        }

        // Build the metricFamilySamples objects
        $objects = array();
        foreach ($familySamples as $sample) {
            $objects[] = new MetricFamilySamples($sample);
        }

        return $objects;
    }

    /**
     * Fetches the metadata (HELP or TYPE) from the line. Discards comments, or other content that does not match the
     * expected format.
     *
     * @param string $line
     *
     * @return array
     */
    private function getMeta($line)
    {
        // Remove redundant info from line
        $line = trim($line, '# ');

        // Splits the parts into
        // [#][HELP][test_some_metric][this is for testing]
        //      ^ type     ^ metric         ^ content
        $components = explode(" ", $line);
        $type       = array_shift($components);
        $metric     = array_shift($components);
        $content    = implode(' ', $components);

        if (!in_array($type, $this->metaTypes)) {
            // Does not throw an exception, as malformed metric records should not terminate an application.
            return array();
        }

        return array(
            $metric => array(
                strtolower($type) => $content
            )
        );
    }

    /**
     * Fetches the metric from the line. Returns an array of the form
     *
     * array(
     *   'metric_name' => array(
     *     'labels' => array(
     *     'label1',
     *     'label2',
     *   ),
     *   'labelValues' => array(
     *     'foo',
     *     'bar'
     *    ),
     *   'value' => 48
     * );
     *
     * @param $line
     *
     * @return array
     * @throws ParseException if it's unable to successfully parse the record
     */
    private function getMetric($line)
    {
        // Todo: Regex isn't a super nice way to do this, but I can't think no fanything else at the minute.
        $matches = array();
        preg_match_all('/^(?<metric_name>[A-z_]+){(?<labels>.*)}\s{0,}(?<value>[0-9]+)/', $line, $matches);

        $metricName = array_shift($matches['metric_name']);
        $value      = array_shift($matches['value']);

        if (!$metricName || !$value) {
            throw new ParseException(
                sprintf('Unable to fetch metric data from "%s": Could not read metric name and value.', $line)
            );
        }

        // Labels are a bunch of entries of the form 'foo="bar",baz="herp"'. Need to split these out into ordered pairs
        $labelString = array_shift($matches['labels']);
        $labelPairs  = explode(',', $labelString);
        $labels      = array();
        $labelValues = array();

        foreach ($labelPairs as $pair) {
            $labelParts    = explode('=', $pair);
            $labels[]      = $labelParts[0];
            $labelValues[] = trim($labelParts[1], '"');
        }

        return array(
            $metricName => array(
                array(
                    'labels'      => $labels,
                    'labelValues' => $labelValues,
                    'value'       => $value
                )
            )
        );
    }
}
