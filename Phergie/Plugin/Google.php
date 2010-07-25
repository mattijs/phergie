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
 * @package   Phergie_Plugin_Google
 * @author    Phergie Development Team <team@phergie.org>
 * @copyright 2008-2010 Phergie Development Team (http://phergie.org)
 * @license   http://phergie.org/license New BSD License
 * @link      http://pear.phergie.org/package/Phergie_Plugin_Google
 */

/**
 * Provides commands used to access several services offered by Google
 * including search, translation, weather, maps, and currency and general
 * value unit conversion.
 *
 * @category Phergie
 * @package  Phergie_Plugin_Google
 * @author   Phergie Development Team <team@phergie.org>
 * @license  http://phergie.org/license New BSD License
 * @link     http://pear.phergie.org/package/Phergie_Plugin_Google
 * @uses     Phergie_Plugin_Command pear.phergie.org
 * @uses     Phergie_Plugin_Http pear.phergie.org
 * @uses     Phergie_Plugin_Temperature pear.phergie.org
 *
 * @pluginDesc Provide access to some Google services
 */
class Phergie_Plugin_Google extends Phergie_Plugin_Abstract
{

    /**
     * HTTP plugin
     *
     * @var Phergie_Plugin_Http
     */
    protected $http;

    /**
     * Language for Google Services
     */
    protected $lang;

    /**
     * Checks for dependencies.
     *
     * @return void
     */
    public function onLoad()
    {
        $plugins = $this->getPluginHandler();
        $plugins->getPlugin('Command');
        $this->http = $plugins->getPlugin('Http');
        $plugins->getPlugin('Help')->register($this);
        $plugins->getPlugin('Weather');

        $this->lang = $this->getConfig('google.lang', 'en');
    }

    /**
     * Returns the first result of a Google search.
     *
     * @param string $query Search term
     *
     * @return void
     * @todo Implement use of URL shortening here
     *
     * @pluginCmd [query] do a search on google
     */
    public function onCommandG($query)
    {
        $url = 'http://ajax.googleapis.com/ajax/services/search/web';
        $params = array(
            'v' => '1.0',
            'q' => $query
        );
        $response = $this->http->get($url, $params);
        $json = $response->getContent()->responseData;
        $event = $this->getEvent();
        $source = $event->getSource();
        $nick = $event->getNick();
        if ($json->cursor->estimatedResultCount > 0) {
            $msg
                = $nick
                . ': [ '
                . $json->results[0]->titleNoFormatting
                . ' ] - '
                . $json->results[0]->url
                . ' - More results: '
                . $json->cursor->moreResultsUrl;
            $this->doPrivmsg($source, $msg);
        } else {
            $msg = $nick . ': No results for this query.';
            $this->doPrivmsg($source, $msg);
        }
    }

    /**
     * Performs a Google Count search for the given term.
     *
     * @param string $query Search term
     *
     * @return void
     *
     * @pluginCmd [query] Do a search on Google and count the results
     */
    public function onCommandGc($query)
    {
        $url = 'http://ajax.googleapis.com/ajax/services/search/web';
        $params = array(
            'v' => '1.0',
            'q' => $query
        );
        $response = $this->http->get($url, $params);
        $json = $response->getContent()->responseData->cursor;
        $count = $json->estimatedResultCount;
        $event = $this->getEvent();
        $source = $event->getSource();
        $nick = $event->getNick();
        if ($count) {
            $msg
                = $nick . ': ' .
                number_format($count, 0) .
                ' estimated results for ' . $query;
            $this->doPrivmsg($source, $msg);
        } else {
            $this->doPrivmsg($source, $nick . ': No results for this query.');
        }
    }

    /**
     * Performs a Google Translate search for the given term.
     *
     * @param string $from  Language of the search term
     * @param string $to    Language to which the search term should be
     *        translated
     * @param string $query Term to translate
     *
     * @return void
     *
     * @pluginCmd [from language] [to language] [text to translate] Do a translation on Google
     */
    public function onCommandGt($from, $to, $query)
    {
        $url = 'http://ajax.googleapis.com/ajax/services/language/translate';
        $params = array(
            'v' => '1.0',
            'q' => $query,
            'langpair' => $from . '|' . $to
        );
        $response = $this->http->get($url, $params);
        $json = $response->getContent();
        $event = $this->getEvent();
        $source = $event->getSource();
        $nick = $event->getNick();
        if (empty($json->responseData->translatedText)) {
            $this->doPrivmsg($source, $nick . ': ' . $json->responseDetails);
        } else {
            $this->doPrivmsg(
                $source,
                $nick . ': ' . $json->responseData->translatedText
            );
        }
    }

    /**
     * Performs a Google Weather search for the given term.
     *
     * @param string $location Location to search for
     * @param int    $offset   Optional day offset from the current date
     *        between 0 and 3 to get the forecast
     *
     * @return void
     *
     * @pluginCmd [location] Show the weather for the specified location
     */
    public function onCommandGw($location, $offset = null)
    {
        $url = 'http://www.google.com/ig/api';
        $params = array(
            'weather' => $location,
            'hl' => $this->lang,
            'oe' => 'UTF-8'
        );
        $response = $this->http->get($url, $params);
        $xml = $response->getContent()->weather;

        $event = $this->getEvent();
        $source = $event->getSource();
        $msg = '';
        if ($event->isInChannel()) {
            $msg .= $event->getNick() . ': ';
        }

        if (isset($xml->problem_cause)) {
            $msg .= $xml->problem_cause->attributes()->data[0];
            $this->doPrivmsg($source, $msg);
            return;
        }

        $temperature = $this->plugins->getPlugin('Temperature');

        $forecast = $xml->forecast_information;
        $city = $forecast->city->attributes()->data[0];
        $zip = $forecast->postal_code->attributes()->data[0];

        if ($offset !== null) {
            $offset = (int) $offset;
            if ($offset < 0) {
                $this->doNotice($source, 'Past weather data is not available');
                return;
            } elseif ($offset > 3) {
                $this->doNotice($source, 'Future weather data is limited to 3 days from today');
                return;
            }

            $linha = $xml->forecast_conditions[$offset];
            $low = $linha->low->attributes()->data[0];
            $high = $linha->high->attributes()->data[0];
            $units = $forecast->unit_system->attributes()->data[0];
            $condition = $linha->condition->attributes()->data[0];
            $day = $linha->day_of_week->attributes()->data[0];

            $date = ($offset == 0) ? time() : strtotime('next ' . $day);
            $day = ucfirst($day) . ' ' . date('n/j/y', $date);

            if ($units == 'US') {
                $lowF = $low;
                $lowC = $temperature->convertFahrenheitToCelsius($low);
                $highF = $high;
                $highC = $temperature->convertFahrenheitToCelsius($high);
            } else {
                $lowC = $low;
                $lowF = $temperature->convertCelsiusToFahrenheit($lowC);
                $highC = $high;
                $highF = $temperature->convertCelsiusToFahrenheit($high);
            }

            $msg .= 'Forecast for ' . $city . ' (' . $zip . ')'
                . ' on ' . $day . ' ::'
                . ' Low: ' . $lowF . 'F/' . $lowC . 'C,'
                . ' High: ' . $highF . 'F/' . $highC . 'C,'
                . ' Conditions: ' . $condition;
        } else {
            $conditions = $xml->current_conditions;
            $condition = $conditions->condition->attributes()->data[0];
            $tempF = $conditions->temp_f->attributes()->data[0];
            $tempC = $conditions->temp_c->attributes()->data[0];
            $humidity = $conditions->humidity->attributes()->data[0];
            $wind = $conditions->wind_condition->attributes()->data[0];
            $time = $forecast->current_date_time->attributes()->data[0];
            $time = date('n/j/y g:i A', strtotime($time)) . ' +0000';

            $hiF = $temperature->getHeatIndex($tempF, $humidity);
            $hiC = $temperature->convertFahrenheitToCelsius($hiF);

            $msg .= 'Weather for ' . $city . ' (' . $zip . ') -'
                . ' Temperature: ' . $tempF . 'F/' . $tempC . 'C,'
                . ' ' . $humidity . ','
                . ' Heat Index: ' . $hiF . 'F/' . $hiC . 'C,'
                . ' Conditions: ' . $condition . ','
                . ' Updated: ' . $time;
        }

        $this->doPrivmsg($source, $msg);
    }

    /**
     * Performs a Google Maps search for the given term.
     *
     * @param string $location Location to search for
     *
     * @return void
     *
     * @pluginCmd [location] Get the location from Google Maps to the location specified
     */
    public function onCommandGmap($location)
    {
        $event = $this->getEvent();
        $source = $event->getSource();
        $nick = $event->getNick();

        $location = utf8_encode($location);
        $url = 'http://maps.google.com/maps/geo';
        $params = array(
            'q' => $location,
            'output' => 'json',
            'gl' => $this->lang,
            'sensor' => 'false',
            'oe' => 'utf8',
            'mrt' => 'all',
            'key' => $this->getConfig('google.key')
        );
        $response = $this->http->get($url, $params);
        $json =  $response->getContent();
        if (!empty($json)) {
            $qtd = count($json->Placemark);
            if ($qtd > 1) {
                if ($qtd <= 3) {
                    foreach ($json->Placemark as $places) {
                        $xy = $places->Point->coordinates;
                        $address = utf8_decode($places->address);
                        $url = 'http://maps.google.com/maps?sll=' . $xy[1] . ','
                            . $xy[0] . '&z=15';
                        $msg = $nick . ' -> ' . $address . ' - ' . $url;
                        $this->doPrivmsg($source, $msg);
                    }
                } else {
                    $msg
                        = $nick .
                        ', there are a lot of places with that query.' .
                        ' Try to be more specific!';
                    $this->doPrivmsg($source, $msg);
                }
            } elseif ($qtd == 1) {
                $xy = $json->Placemark[0]->Point->coordinates;
                $address = utf8_decode($json->Placemark[0]->address);
                $url = 'http://maps.google.com/maps?sll=' . $xy[1] . ',' . $xy[0]
                    . '&z=15';
                $msg = $nick . ' -> ' . $address . ' - ' . $url;
                $this->doPrivmsg($source, $msg);
            } else {
                $this->doPrivmsg($source, $nick . ', I found nothing.');
            }
        } else {
            $this->doPrivmsg($source, $nick . ', we have a problem.');
        }
    }

    /**
     * Perform a Google Convert query to convert a value from one metric to
     * another.
     *
     * @param string $value Value to convert
     * @param string $from  Source metric
     * @param string $to    Destination metric
     *
     * @return void
     *
     * @pluginCmd [value] [currency from] [currency to] Converts a monetary value from one currency to another
     */
    public function onCommandGconvert($value, $from, $to)
    {
        $url = 'http://www.google.com/finance/converter';
        $params = array(
            'a' => $value,
            'from' => $from,
            'to' => $to
        );
        $response = $this->http->get($url, $params);
        $contents = $response->getContent();
        $event = $this->getEvent();
        $source = $event->getSource();
        $nick = $event->getNick();
        if ($contents) {
            preg_match(
                '#<span class=bld>.*? ' . $to . '</span>#im',
                $contents,
                $matches
            );
            if (!$matches[0]) {
                $this->doPrivmsg($source, $nick . ', I can\'t do that.');
            } else {
                $str = str_replace('<span class=bld>', '', $matches[0]);
                $str = str_replace($to . '</span>', '', $str);
                $text
                    = number_format($value, 2, ',', '.') . ' ' . $from .
                    ' => ' . number_format($str, 2, ',', '.') . ' ' . $to;
                $this->doPrivmsg($source, $text);
            }
        } else {
            $this->doPrivmsg($source, $nick . ', we had a problem.');
        }
    }

    /**
     * Performs a Google search to convert a value from one unit to another.
     *
     * @param string $query Query of the form "[quantity] [unit] to [unit2]"
     *
     * @return void
     *
     * @pluginCmd [quantity] [unit] to [unit2] Convert a value from one
     *            metric to another
     */
    public function onCommandConvert($query)
    {
        $url = 'http://www.google.com/search?q=' . urlencode($query);
        $response = $this->http->get($url);
        $contents = $response->getContent();
        $event = $this->getEvent();
        $source = $event->getSource();
        $nick = $event->getNick();

        if ($response->isError()) {
            $code = $response->getCode();
            $message = $response->getMessage();
            $this->doNotice($nick, 'ERROR: ' . $code . ' ' . $message);
            return;
        }

        $start = strpos($contents, '<h3 class=r>');
        if ($start !== false) {
            $end = strpos($contents, '</b>', $start);
            $text = strip_tags(substr($contents, $start, $end - $start));
            $text = str_replace(
                array(chr(195), chr(151), chr(160)),
                array('x', '', ' '),
                $text
            );
        }

        if (isset($text)) {
            $this->doPrivmsg($source, $nick . ': ' . $text);
        } else {
            $this->doNotice($nick, 'Sorry I couldn\'t find an answer.');
        }
    }


    /**
     * Returns the first definition of a Google Dictionary search.
     *
     * @param string $query Word to get the definition
     *
     * @return void
     * @todo Implement use of URL shortening here
     *
     * @pluginCmd [query] do a search of a definition on Google Dictionary
     */
    public function onCommandDefine($query)
    {
        $query = urlencode($query);
        $url = 'http://www.google.com/dictionary/json?callback=result'.
               '&q='.$query.'&sl='.$this->lang.'&tl='.$this->lang.
               '&restrict=pr,de';
        $json = file_get_contents($url);

        //Remove some garbage from the json
        $json = str_replace(array("result(", ",200,null)"), "", $json);

        //Awesome workaround to remove a lot of slashes from json
        $json = str_replace('"', '¿?¿', $json);
        $json = strip_tags(stripcslashes($json));
        $json = str_replace('"', "'", $json);
        $json = str_replace('¿?¿', '"', $json);

        $json = json_decode($json);

        $event = $this->getEvent();
        $source = $event->getSource();
        $nick = $event->getNick();
        if (!empty($json->webDefinitions)){
            $results = count($json->webDefinitions[0]->entries);
            $more = $results > 1 ? ($results-1).' ' : NULL;
            $lang_code = substr($this->lang, 0, 2);
            $msg =
                $nick . ': ' .
                $json->webDefinitions[0]->entries[0]->terms[0]->text .
                ' - You can find more '.$more.'results at '.
                'http://www.google.com/dictionary?aq=f&langpair='.
                $lang_code.'%7C'.$lang_code.'&q='.$query.'&hl='.$lang_code;
            $this->doPrivmsg($source, $msg);
        }else{
            if ($this->lang != 'en'){
               $temp = $this->lang;
               $this->lang = 'en';
               $this->onCommandDefine($query);
               $this->lang = $temp;
            }else{
               $msg = $nick . ': No results for this query.';
               $this->doPrivmsg($source, $msg);
            }
        }
    }
}
