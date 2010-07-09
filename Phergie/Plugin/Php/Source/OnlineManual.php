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
 * @package   Phergie_Plugin_Php_Source_OnlineManual
 * @author    Phergie Development Team <team@phergie.org>
 * @copyright 2008-2010 Phergie Development Team (http://phergie.org)
 * @license   http://phergie.org/license New BSD License
 * @link      http://pear.phergie.org/package/Phergie_Plugin_Php_Source_OnlineManual
 */

/**
 * PHP source backend getting information from the online manual at
 * http://www.php.net
 *
 * @category Phergie
 * @package  Phergie_Plugin_Php_Source_OnlineManual
 * @author   Phergie Development Team <team@phergie.org>
 * @license  http://phergie.org/license New BSD License
 * @link     http://pear.phergie.org/package/Phergie_Plugin_Php_Source_OnlineManual
 * @uses     Phergie_Plugin_Php_Source pear.phergie.org
 * @uses     Phergie_Plugin_Http pear.phergie.org
 */
class Phergie_Plugin_Php_Source_OnlineManual implements Phergie_Plugin_Php_Source
{
    /**
     * The PHP plugin this source is used by.
     * @var Phergie_Plugin_Php
     */
    protected $plugin = null;
    
    /**
     * HTTP plugin reference
     * @var Phergie_Plugin_Http
     */
    protected $http = null;

    /**
     * Base url to the php online manual
     * @var string
     */
    protected $manualUrl = 'http://www.php.net/manual';

    /**
     * Manual language. Default: en
     * @todo Make this configurable through Settings.php
     * @var string
     */
    protected $manualLanguage = 'en';

    /** **/

    /**
     * Creates a new Http plugin for fetching manual entries.\
     * @param Phergie_Plugin_Handler $plugins Phergie plugin handler
     */
    public function __construct(Phergie_Plugin_Php $plugin)
    {
        $this->plugin = $plugin;
        $this->http = $plugin->getPluginHandler()->getPlugin('Http');
    }

    /**
     * @see Phergie_Plugin_Php_Source::findFunction()
     */
    public function findFunction($function)
    {
        // Convert the function name to the function reference from the manual
        $functionRef = trim($function, "\r\n\t ()");
        list($method, $classOrFunction) = array_reverse(explode('::', $functionRef, 2)) + array(null, 'function');
        $functionRef = $classOrFunction . '.' . trim($method, '_');
        $functionRef = str_replace('_', '-', $functionRef);
        $functionRef = strtolower($functionRef);

        // Build the url to the manual entry
        $url = $this->manualUrl . '/' . $this->manualLanguage . '/' . $functionRef . '.php';

        // Get the manual entry
        $response = $this->http->get($url);
        if ($response->isError()) {
            return null;
        }
        $html = $response->getContent();
        
        // Build a DOMDocument from the HTML source
        libxml_use_internal_errors(true);
        $domdoc = new DOMDocument('1.0', 'UTF-8');
        $domdoc->loadHTML($html);

        // Create a new XPath object for finding specific elements
        $xpath = new DOMXPath($domdoc);

        // Check to see if we have the function page, or the search page when the function was not found
        $functionElement = $xpath->evaluate('/html/body//div[@id="' . $functionRef .'"]');
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
     * Strips tags from a string, removes new lines and reduces multiple spaces
     * to single ones.
     * @param string $value
     * @return string
     */
    protected function _cleanString($value)
    {
        $cleaned = strip_tags($value);
        $cleaned = str_replace("\n", '', $cleaned);
        $cleaned = trim(preg_replace('/[\s]{2,}/i', " ", $cleaned));

        return $cleaned;
    }
    
}