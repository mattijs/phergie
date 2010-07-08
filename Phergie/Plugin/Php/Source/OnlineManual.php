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
 * @package   Phergie_Plugin_Php_Source
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
 * @package  Phergie_Plugin_Php_Source
 * @author   Phergie Development Team <team@phergie.org>
 * @license  http://phergie.org/license New BSD License
 * @link     http://pear.phergie.org/package/Phergie_Plugin_Php_Source_OnlineManual
 * @uses     extension curl
 * @uses     extension pdo_sqlite
 * @uses     Phergie_Plugin_Php_Source pear.phergie.org
 */
class Phergie_Plugin_Php_Source_OnlineManual implements Phergie_Plugin_Php_Source
{
    /**
     * Base url to the php online manual
     * @var string
     */
    protected $_manualUrl = 'http://www.php.net/manual';

    /**
     * Manual language. Default: en
     * @var string
     */
    protected $_manualLanguage = 'en';

    /** **/

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
        $url = $this->_manualUrl . '/' . $this->_manualLanguage . '/' . $functionRef . '.php';

        // Get the HTML from the manual entry, either with file_get_contents or cUrl
        if ((boolean) ini_get('allow_url_fopen')) {
            $html = @file_get_contents($url);
            if (false === $html) {
                return null;
            }
        }
        else if (extension_loaded('curl')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
            curl_setopt($ch, CURLOPT_HEADER, 1);
            $response = curl_exec($ch);
            curl_close($ch);

            // Separate headers from body
            list($headerString, $html) = explode("\r\n\r\n", $response, 2);
            // Separate headers
            $headers = explode("\r\n", $headerString);
            // Check if we're OK
            if (!preg_match('/HTTP\/1.(?:0|1) 200 OK/i', array_shift($headers)) || empty($html)) {
                return null;
            }
        }
        else {
            throw new Phergie_Exception('opening external files or cUrl must be enabled to use this data source.');
        }
        
        // Build a DOMDocument from the HTML source
        $domdoc = new DOMDocument('1.0', 'UTF-8');
        @$domdoc->loadHTML($html);

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