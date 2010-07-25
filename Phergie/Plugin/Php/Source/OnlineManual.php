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
 * Manual source searching for function references in the online PHP manual. The
 * manual path can be configured by setting the php.manual.path setting in
 * Settings.php. For example:
 * {{{
 *  ...
 *  'php.source' => 'OnlineManual',
 *  'php.manual.path' => 'http://www.php.net/manual/en/',
 *  ...
 * }}
 * The language of the manual can be changed by replacing 'en' with one of the
 * supported manual languages. The server that is contacted for the manual can
 * be changed by replacing 'www' with a mirror site. This may speed up the
 * lookup of the function description.
 *
 * @category Phergie
 * @package  Phergie_Plugin_Php_Source_OnlineManual
 * @author   Phergie Development Team <team@phergie.org>
 * @license  http://phergie.org/license New BSD License
 * @link     http://pear.phergie.org/package/Phergie_Plugin_Php_Source_OnlineManual
 */
class Phergie_Plugin_Php_Source_OnlineManual
    extends Phergie_Plugin_Php_Source_Manual
{
    /**
     * Path to the PHP online manual
     * @var string
     */
    protected $manualPath = 'http://www.php.net/manual/en';

    /** **/

    /**
     * Find a function in the online manual.
     * @see Phergie_Plugin_Php_Source
     * @param string $function The name of the function
     * @return array|null Associative array containing the function name, synopsis
     *                    and description or NULL if no results are found
     */
    public function findFunction($function)
    {
        // Get the reference for the function used in the manual
        $reference = $this->_createFunctionReference($function);

        // Build the URL to the manual entry
        $http = $this->plugin->getPluginHandler()->getPlugin('Http');
        $url = rtrim($this->manualPath, '/') . '/' . $reference . '.php';

        // Get the manual entry
        $response = $http->get($url);
        if ($response->isError()) {
            return null;
        }
        $html = $response->getContent();

        // Extract the function reference from the HTML
        $function = $this->_extractFromHtml($html, $reference);
        unset($html);
        return $function;
    }
}