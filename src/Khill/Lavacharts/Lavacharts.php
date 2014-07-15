<?php namespace Khill\Lavacharts;

/**
 * Lavacharts - A PHP wrapper library for the Google Chart API
 *
 *
 * @category  Class
 * @package   Khill\Lavacharts
 * @author    Kevin Hill <kevinkhill@gmail.com>
 * @copyright (c) 2014, KHill Designs
 * @link      https://github.com/kevinkhill/LavaCharts GitHub Repository Page
 * @link      http://kevinkhill.github.io/LavaCharts/ GitHub Project Page
 * @license   http://opensource.org/licenses/MIT MIT
 */

use Khill\Lavacharts\Configs\DataTable;
use Khill\Lavacharts\Exceptions\LabelNotFound;
use Khill\Lavacharts\Exceptions\InvalidChartLabel;
use Khill\Lavacharts\Exceptions\InvalidLavaObject;
use Khill\Lavacharts\Exceptions\InvalidConfigValue;
use Khill\Lavacharts\Exceptions\InvalidDivDimensions;
use Khill\Lavacharts\Exceptions\InvalidConfigProperty;

class Lavacharts
{
    /**
     * @var Khill\Lavacharts\Volcano Holds all of the defined Charts and DataTables.
     */
    public $volcano = null;

    /**
     * @var array Lavachart configuration options.
     */
    public $config = array();

    /**
     * @var array Types of charts that can be created.
     */
    private $chartClasses = array(
        'DataTable',
        'LineChart',
        'AreaChart',
        'PieChart',
        'DonutChart',
        'ColumnChart',
        'GeoChart',
        'ComboChart' // @TODO WIP
    );

    /**
     * @var array Holds all of the defined configuration class names.
     */
    private $configClasses = array(
        'ConfigOptions',
        'Annotation',
        'Axis',
//        'DataCell',
        'BoxStyle',
        'BackgroundColor',
        'ChartArea',
        'ColorAxis',
        'HorizontalAxis',
        'JsDate',
        'Gradient',
        'Legend',
        'MagnifyingGlass',
        'TextStyle',
        'Tooltip',
        'Series', // @TODO WIP
        'SizeAxis',
        'Slice',
        'VerticalAxis'
    );

    /**
     * @var array Acceptable global configuration options.
     */
    private $globalConfigs = array(
        'textStyle'
    );

    /**
     * Creates Volcano object for chart storage.
     */
    public function __construct()
    {
        $this->volcano = new Volcano();
    }

    /**
     * Magic function to reduce repetitive coding and create aliases.
     *
     * @access public
     *
     * @param string $member Name of method
     * @param array $arguments Passed arguments
     * @throws Khill\Lavacharts\Exceptions\InvalidLavaObject
     * @throws Khill\Lavacharts\Exceptions\InvalidChartLabel
     *
     * @return mixed Returns Charts, DataTables, and Config Objects
     */
    public function __call($member, $arguments)
    {
        if ($this->strStartsWith($member, 'render')) {
            $chartType = str_replace('render', '', $member);

            if (in_array($chartType, $this->chartClasses)) {
                return $this->render($chartType, $arguments[0], $arguments[1]);
            } else {
                throw new InvalidLavaObject($chartType);
            }
        } elseif ($member == 'DataTable') {
            return new DataTable();
        } elseif (in_array($member, $this->chartClasses)) {
            if (isset($arguments[0])) {
                if(is_string($arguments[0])) {
                    return $this->chartFactory($member, $arguments[0]);
                } else {
                    throw new InvalidChartLabel($arguments[0]);
                }
            } else {
                throw new InvalidChartLabel();
            }
        } elseif (in_array($member, $this->configClasses)) {
            if (isset($arguments[0]) && is_array($arguments[0])) {
                return $this->configFactory($member, $arguments[0]);
            } else {
                return $this->configFactory($member);
            }
        } else {
            throw new InvalidLavaObject($member);
        }
    }

    /**
     * Renders the chart into the page
     *
     * Given a chart label and an HTML element id, this will output
     * all of the necessary javascript to generate the chart.
     *
     * @param  string $label Label of a saved chart.
     * @param  string $elementId HTML element id to render the chart into.
     * @throws Khill\Lavacharts\Exceptions\InvalidConfigValue
     *
     * @return Khill\Lavachart\DataTable
     */
    public function render($chartType, $chartLabel, $elementId, $divDimensions = false)
    {
        $chart = $this->volcano->getChart($chartType, $chartLabel);

        $jsf = new JavascriptFactory($chart, $elementId);

        if ($divDimensions === false) {
            return $jsf->buildOutput();
        } else {
            if (is_array($divDimensions) && ! empty($divDimensions)) {
                return $this->generateDiv($elementId, $divDimensions) . $jsf->buildOutput();
            } else {
                throw new InvalidConfigValue(
                    __METHOD__,
                    'array'
                );
            }
        }
    }

    /**
     * Sets global configuration options for the whole Lavachart library.
     *
     * Accepted config options include:
     * errorPrepend: An html string
     *
     * @access public
     * @param  array $config Array of configurations options
     * @return void
     */
    public function setGlobals($config)
    {
        if (is_array($config)) {
            foreach ($config as $option => $value) {
                if (in_array($option, $this->validGlobals)) {
                    $this->config[$option] = $value;
                } else {
                    throw new InvalidConfigProperty(
                        __METHOD__,
                        $option,
                        $this->validGlobals
                    );
                }
            }
        } else {
            throw new InvalidConfigValue(
                __METHOD__,
                'array'
            );
        }
    }

    /**
     * Builds a div html element for the chart to be rendered into.
     *
     * Calling with no arguments will return a div with the ID set to what was
     * given to the outputInto() function.
     *
     * Passing two (int)s will set the width and height respectivly and the div
     * ID will be set via the string given in the outputInto() function.
     *
     *
     * This is useful for the AnnotatedTimeLine Chart since it MUST have explicitly
     * defined dimensions of the div it is rendered into.
     *
     * The other charts do not require height and width, but do require an ID of
     * the div that will be receiving the chart.
     *
     * @access private
     * @param  string $elementId Element id to apply to the div.
     * @param  array  $dimensions Height & width of the div.
     * @throws Khill\Lavacharts\Exceptions\InvalidDivDimensions
     * @throws Khill\Lavacharts\Exceptions\InvalidConfigValue
     * @return string HTML div element.
     */
    private function generateDiv($elementId, $dimensions = array())
    {
        if (empty($dimensions)) {
            return sprintf('<div id="%s"></div>', $elementId);
        } else {
            if (array_key_exists('height', $dimensions) && array_key_exists('width', $dimensions)) {
                if ((is_int($width) && $width > 0) && (is_int($height) && $height > 0)) {
                        return sprintf(
                            '<div id="%s" style="width:%spx; height:%spx;"></div>',
                            $elementId,
                            $width,
                            $height
                        );
                } else {
                    throw new InvalidConfigValue(
                        __METHOD__,
                        'int',
                        'greater than 0'
                    );
                }
            } else {
                throw new InvalidDivDimensions();
            }
        }
    }

    /**
     * Creates and stores Charts
     *
     * If the Chart is found in the Volcano, then it is returned.
     * Otherwise, a new chart is created and stored in the Volcano.
     *
     * @access private
     * @param  string $label Label applied to the chart.
     * @return Khill\Lavachart\Chart
     */
    private function chartFactory($type, $label)
    {
        $chartObj = __NAMESPACE__ . '\\Charts\\' . $type;

        if (class_exists($chartObj)) {
            if ($this->volcano->checkChart($type, $label)) {
                $chart = $this->volcano->getChart($type, $label);
            } else {
                $chart = new $chartObj($label);

                $this->volcano->storeChart($chart);
            }

            return $chart;
        } else {
            throw new InvalidLavaObject($type);
        }
    }

    /**
     * Creates ConfigObjects
     *
     * @access private
     * @param  string $type Type of configObject to create.
     * @param  array $options Array of options to pass to the config object.
     * @return Khill\Lavachart\Configs\ConfigObject
     */
    private function configFactory($type, $options = array())
    {
        $configObject = __NAMESPACE__ . '\\Configs\\' . $type;

        if (class_exists($configObject)) {
            return isset($options[0]) ? new $configObject($options[0]) : new $configObject();
        } else {
            throw new InvalidLavaObject($type);
        }
    }

    /**
     * Simple string starts with function
     */
    private function strStartsWith($haystack, $needle)
    {
        return $needle === "" || strpos($haystack, $needle) === 0;
    }
}
