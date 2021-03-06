<?php

namespace Backend\Modules\Locale\Engine;

/*
 * This file is part of Fork CMS.
 *
 * For the full copyright and license information, please view the license
 * file that was distributed with this source code.
 */

use Symfony\Component\Filesystem\Filesystem;

/**
 * In this file, the locale cache is build
 *
 * @author Wouter Sioen <wouter@wijs.be>
 */
class CacheBuilder
{
    /**
     * @var \SpoonDatabase
     */
    protected $database;

    /**
     * @var array
     */
    protected $types;
    protected $locale;

    /**
     * @param \SpoonDatabase $database
     */
    public function __construct(\SpoonDatabase $database)
    {
        $this->database = $database;
    }

    /**
     * @param string $language
     * @param string $application Backend or Frontend
     */
    public function buildCache($language, $application)
    {
        // get types
        $this->types = $this->database->getEnumValues('locale', 'type');
        $this->locale = $this->getLocale($language, $application);
        $this->dumpPhpCache($language, $application);
        $this->dumpJavascriptCache($language, $application);
    }

    /**
     * Fetches locale for a certain language application combo
     *
     * @param  string $language
     * @param  string $application
     * @return array
     */
    protected function getLocale($language, $application)
    {
        return (array) $this->database->getRecords(
            'SELECT type, module, name, value
             FROM locale
             WHERE language = ? AND application = ?
             ORDER BY type ASC, name ASC, module ASC',
            array($language, $application)
        );
    }

    /**
     * Builds the php string that will be put in cache
     *
     * @todo A lot of this could be replace by a var_export from the json cache
     * @param  string $language
     * @param  string $application
     * @return string
     */
    protected function buildPhpCache($language, $application)
    {
        // start generating PHP
        $value = '<?php' . "\n\n";
        $value .= '/**' . "\n";
        $value .= ' *' . "\n";
        $value .= ' * This file is generated by Fork CMS, it contains' . "\n";
        $value .= ' * more information about the locale. Do NOT edit.' . "\n";
        $value .= ' * ' . "\n";
        $value .= ' * @author Fork CMS' . "\n";
        $value .= ' * @generated    ' . date('Y-m-d H:i:s') . "\n";
        $value .= ' */' . "\n";
        $value .= "\n";

        foreach ($this->types as $type) {

            // default module
            $modules = array('Core');

            // continue output
            $value .= "\n";
            $value .= '// init var' . "\n";
            $value .= '$' . $type . ' = array();' . "\n";
            $value .= '$' . $type . '[\'Core\'] = array();' . "\n";

            // loop locale
            foreach ($this->locale as $i => $item) {

                // types match
                if ($item['type'] == $type) {

                    // new module
                    if (!in_array($item['module'], $modules)) {
                        $value .= '$' . $type . '[\'' . $item['module'] . '\'] = array();' . "\n";
                        $modules[] = $item['module'];
                    }

                    // parse
                    if ($application == 'Backend') {
                        $value .= '$' . $type . '[\'' . $item['module'] . '\'][\'' . $item['name'] . '\'] = \'' . str_replace(
                                '\"',
                                '"',
                                addslashes($item['value'])
                            ) . '\';' . "\n";
                    } else {
                        $value .= '$' . $type . '[\'' . $item['name'] . '\'] = \'' . str_replace(
                                '\"',
                                '"',
                                addslashes($item['value'])
                            ) . '\';' . "\n";
                    }
                }
            }
        }

        $value .= "\n";
        $value .= '?>';

        return $value;
    }

    /**
     * dumps the locale in cache as a php string
     *
     * @param string $language
     * @param string $application
     */
    protected function dumpPhpCache($language, $application)
    {
        $fs = new Filesystem();
        $fs->dumpFile(
            constant(mb_strtoupper($application) . '_CACHE_PATH') . '/Locale/' . $language . '.php',
            $this->buildPhpCache($language, $application)
        );
    }

    /**
     * Builds the array that will be put in cache
     *
     * @param  string $language
     * @param  string $application
     * @return array
     */
    protected function buildJavascriptCache($language, $application)
    {
        // init var
        $json = array();
        foreach ($this->types as $type) {

            // loop locale
            foreach ($this->locale as $i => $item) {

                // types match
                if ($item['type'] == $type) {
                    if ($application == 'Backend') {
                        $json[$type][$item['module']][$item['name']] = $item['value'];
                    } else {
                        $json[$type][$item['name']] = $item['value'];
                    }
                }
            }
        }
        $this->addSpoonLocale($json, $language);

        return $json;
    }

    /**
     * Adds months and days from spoonLocale to the json
     *
     * @param array  $json
     * @param string $language
     */
    protected function addSpoonLocale(&$json, $language)
    {
        // get months
        $monthsLong = \SpoonLocale::getMonths($language, false);
        $monthsShort = \SpoonLocale::getMonths($language, true);

        // get days
        $daysLong = \SpoonLocale::getWeekDays($language, false, 'sunday');
        $daysShort = \SpoonLocale::getWeekDays($language, true, 'sunday');

        // build labels
        foreach ($monthsLong as $key => $value) {
            $json['loc']['MonthLong' . \SpoonFilter::ucfirst($key)] = $value;
        }
        foreach ($monthsShort as $key => $value) {
            $json['loc']['MonthShort' . \SpoonFilter::ucfirst($key)] = $value;
        }
        foreach ($daysLong as $key => $value) {
            $json['loc']['DayLong' . \SpoonFilter::ucfirst($key)] = $value;
        }
        foreach ($daysShort as $key => $value) {
            $json['loc']['DayShort' . \SpoonFilter::ucfirst($key)] = $value;
        }
    }

    /**
     * dumps the locale in cache as a json object
     *
     * @param string $language
     * @param string $application
     */
    protected function dumpJavascriptCache($language, $application)
    {
        $fs = new Filesystem();
        $fs->dumpFile(
            constant(mb_strtoupper($application) . '_CACHE_PATH') . '/Locale/' . $language . '.json',
            json_encode($this->buildJavascriptCache($language, $application))
        );
    }
}
