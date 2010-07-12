<?php
/**
 * Phergie
 *
 * PHP version 5
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.
 * It is also available through the world-wide-web at this URL:
 * http://phergie.org/license
 *
 * @category  Phergie
 * @package   Phergie_Plugin_Php_Source_Manual
 * @author    Phergie Development Team <team@phergie.org>
 * @copyright 2008-2010 Phergie Development Team (http://phergie.org)
 * @license   http://phergie.org/license New BSD License
 * @link      http://pear.phergie.org/package/Phergie_Plugin_Php_Source_Manual
 */

/**
 * PHP source backend getting information from the PHP manual. Different types
 * of the PHP manual are supported:
 * - Online manual
 * - Downloaded manual in many HTML files
 * - Downloaded manual in a single HTML file
 *
 * The type of manual can be specified by setting the php.manual.type value to
 * one of the class constants in Settings.php. The path to the manual (either
 * local online) can be specified by setting the php.manual.path value in
 * Settings.php.
 *
 * @category Phergie
 * @package  Phergie_Plugin_Php_Source_Manual
 * @author   Phergie Development Team <team@phergie.org>
 * @license  http://phergie.org/license New BSD License
 * @link     http://pear.phergie.org/package/Phergie_Plugin_Php_Source_Manual
 * @uses     Phergie_Plugin_Php_Source pear.phergie.org
 */
class Phergie_Plugin_Php_Source_Manual implements Phergie_Plugin_Php_Source
{

    /* Manual types */
    CONST MANUAL_TYPE_ONLINE = 1;
    CONST MANUAL_TYPE_MANY = 2;
    CONST MANUAL_TYPE_SINGLE = 3;

    /**
     * The PHP plugin this source is used by.
     * @var Phergie_Plugin_Php
     */
    protected $plugin = null;

    /**
     * Abosulte path to the local manual
     * @var string
     */
    protected $manualPath = 'http://www.php.net/manual/en';

    /**
     * Type of manual to use. Either many for the "Many HTML files" version
     * or single for the "Single HTML file" version. Default: many
     * @var string
     */
    protected $manualType = null;

    /** **/

    public function __construct(Phergie_Plugin_Php $plugin)
    {
        $this->plugin = $plugin;

        // Check manualType configuration
        $this->manualType = $this->plugin->getConfig('php.manual.type', self::MANUAL_TYPE_ONLINE);
        if (!in_array($this->manualType, array(self::MANUAL_TYPE_ONLINE, self::MANUAL_TYPE_MANY, self::MANUAL_TYPE_SINGLE))) {
            throw new Phergie_Exception('Unknown PHP manual type ' . $manualType .'. Please use one of the class constants.');
        }

        $this->manualPath = rtrim($this->plugin->getConfig('php.manual.path', null), '/\\ ');
        if (null === $this->manualPath) {
            throw new Phergie_Exception('No path specified for local PHP manual.');
        }
        else if(self::MANUAL_TYPE_MANY === $this->manualType && !is_dir($this->manualPath)) {
            throw new Phergie_Exception('Could not find manual path for many HTML files manual.');
        }
        else if (self::MANUAL_TYPE_SINGLE === $this->manualType && !is_file($this->manualPath)) {
            throw new Phergie_Exception('Could not find manual path for single HTML file manual.');
        }
    }

    /**
     * @see Phergie_Plugin_Php_Source::findFunction()
     */
    public function findFunction($function)
    {
        // Check the cache
        /** @todo implement a sqlite3 cache */

        // Collect the function information
        switch ($this->manualType)
        {
            case self::MANUAL_TYPE_SINGLE:
                return $this->_findInSingle($function);
                break;
            case self::MANUAL_TYPE_MANY:
                return $this->_findInMany($function);
                break;
            case self::MANUAL_TYPE_ONLINE:
            default:
                return $this->_findInOnline($function);
                break;
        }

        return null;
    }

    /**
     * Creates a reference to the function as used in the manual.
     * @param string $function The name of the function
     * @return string The reference by which the funciton is found in the manual
     */
    protected function _createFunctionReference($function)
    {
        $referece = trim($function, "\r\n\t ()");
        list($method, $classOrFunction) = array_reverse(explode('::', $referece, 2)) + array(null, 'function');
        $referece = $classOrFunction . '.' . trim($method, '_');
        $referece = str_replace('_', '-', $referece);
        $referece = strtolower($referece);

        return $referece;
    }

    /**
     * Strips tags from a string, removes new lines and reduces multiple spaces
     * to single ones.
     * @param string $value The string to clean
     * @return string The cleaned string
     */
    protected function _cleanString($value)
    {
        $cleaned = strip_tags($value);
        $cleaned = str_replace("\n", '', $cleaned);
        $cleaned = trim(preg_replace('/[\s]{2,}/i', " ", $cleaned));

        return $cleaned;
    }

    /**
     * Extracts function information from HTML generated by the PHP manual using
     * DOMXML.
     * @param string $html The HTML string to extract the reference from
     * @param string $reference The reference name for the function
     * @return array|null Associative array containing the function name, synopsis
     *                    and description or NULL if no results are found
     */
    protected function _extractFromHtml($html, $reference)
    {
        libxml_use_internal_errors(true);
        $domdoc = new DOMDocument('1.0', 'UTF-8');
        $domdoc->loadHTML($html);

        // Create a new XPath object for finding specific elements
        $xpath = new DOMXPath($domdoc);

        // Check to see if the information is contained within the HTML
        $functionElement = $xpath->evaluate('/html/body//div[@id="' . $reference .'"]');
        if (0 >= $functionElement->length) {
            return null;
        }

        // Find the function synopsis
        $synopsisElement = $xpath->evaluate('/html/body//div[@class="methodsynopsis dc-description"]');
        if (0 >= $synopsisElement->length) {
            return null;
        }
        $synopsis = $this->_cleanString($domdoc->saveXML($synopsisElement->item(0)));

        // Find the function description
        $descriptionElement = $xpath->evaluate('/html/body//div[@class="refnamediv"]//p[@class="refpurpose"]//span[@class="dc-title"]');
        if (0 >= $descriptionElement->length) {
            return null;
        }
        $description = $this->_cleanString($domdoc->saveXML($descriptionElement->item(0)));

        unset($domdoc);
        libxml_clear_errors();

        return array (
            'name' => $function,
            'synopsis' => $synopsis,
            'description' => $description,
        );
    }

    /**
     * Find a function in the online manual.
     * @param string $function The name of the function
     * @return array|null Associative array containing the function name, synopsis
     *                    and description or NULL if no results are found
     */
    protected function _findInOnline($function)
    {
        $reference = $this->_createFunctionReference($function);

        $http = $this->plugin->getPluginHandler()->getPlugin('Http');
        $url = $this->manualPath . '/' . $reference . '.php';

        // Get the manual entry
        $response = $http->get($url);
        if ($response->isError()) {
            return null;
        }
        $html = $response->getContent();

        $function = $this->_extractFromHtml($html, $reference);
        unset($html);
        return $function;
    }

    /**
     * Find a function in the single HTML file manual stored locally.
     * @param string $function The name of the function
     * @return array|null Associative array containing the function name, synopsis
     *                    and description or NULL if no results are found
     */
    protected function _findInSingle($function)
    {
        $reference = $this->_createFunctionReference($function);

        $html = file_get_contents($this->manualPath);
        if (false === $html) {
            return null;
        }

        $function = $this->_extractFromHtml($html, $reference);
        unset($html);
        return $function;
    }

    /**
     * Find a function in the many HTML files manual stored locally.
     * @param string $function The name of the function
     * @return array|null Associative array containing the function name, synopsis
     *                    and description or NULL if no results are found
     */
    protected function _findInMany($function)
    {
        $reference = $this->_createFunctionReference($function);

        // Find the correct file from the manual
        $file = $this->manualPath . DIRECTORY_SEPARATOR . $reference . '.html';

        if (!is_file($file)) {
            return null;
        }

        $html = file_get_contents($file);
        if (false === $html) {
            return null;
        }

        $function = $this->_extractFromHtml($html, $reference);
        unset($html);
        return $function;
    }

}