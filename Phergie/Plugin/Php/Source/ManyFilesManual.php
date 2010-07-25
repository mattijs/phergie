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
 * @package   Phergie_Plugin_Php_Source_ManyFilesManual
 * @author    Phergie Development Team <team@phergie.org>
 * @copyright 2008-2010 Phergie Development Team (http://phergie.org)
 * @license   http://phergie.org/license New BSD License
 * @link      http://pear.phergie.org/package/Phergie_Plugin_Php_Source_ManyFilesManual
 */

/**
 * Manual source searching for function references in a downloaded version of 
 * the PHP manual consisting of many HTML files. The manual path can be
 * configured by setting the php.manual.path setting in Settings.php to an
 * absolute path on the filesystem. For example:
 * {{{
 *  ...
 *  'php.source' => 'ManyFilesManual',
 *  'php.manual.path' => '/opt/php-5.3.2-manual/',
 *  ...
 * }}
 * The language of the manual can be changed by downloading the manual in one of
 * the supported languages.
 *
 * @category Phergie
 * @package  Phergie_Plugin_Php_Source_ManyFilesManual
 * @author   Phergie Development Team <team@phergie.org>
 * @license  http://phergie.org/license New BSD License
 * @link     http://pear.phergie.org/package/Phergie_Plugin_Php_Source_ManyFilesManual
 */
class Phergie_Plugin_Php_Source_ManyFilesManual
    extends Phergie_Plugin_Php_Source_Manual
{
    /**
     * Find a function in a downloaded version of the manual consisting of many
     * HTML files.
     * @see Phergie_Plugin_Php_Source
     * @param string $function The name of the function
     * @return array|null Associative array containing the function name, synopsis
     *                    and description or NULL if no results are found
     */
    public function findFunction($function)
    {
        // Get the reference for the function used in the manual
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

        // Extract the function reference from the HTML
        $function = $this->_extractFromHtml($html, $reference);
        unset($html);
        return $function;
    }

}