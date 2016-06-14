<?php

/**
 * Plugin Name:  fixer.io Currency Conversion by Marketing Results
 * Plugin URI:   https://www.marketingresults.com.au
 * Description:  Provides currency conversion options via fixer.io
 * Version:      1.0.0
 * Author:       Shaun Johnston
 * Author URI:   https://www.marketingresults.com.au
 * Text Domain:  mr-fixer-io
 * License:      GPL-2.0+
 * License URI:  http://www.gnu.org/licenses/gpl-2.0.txt
 */

class MrFixerIO {

  const prefix = 'mr_fixer_io_';
  const fixer_io_url = 'http://api.fixer.io/latest';

  private static $instance;
  private $rates;
  private $country;

  public static function getInstance() {
    if (null === static::$instance) {
      static::$instance = new static();
    }
    return static::$instance;
  }

  protected function __construct() {
    register_activation_hook(__FILE__, array($this, 'activate'));
    self::add_shortcodes();
    self::add_filters();
  }

  private function __clone() {
  }

  private function __wakeup() {
  }

  public function activate() {
    add_option(self::prefix . 'countries', array(
      'au' => array(
        'currency' => 'AUD',
        'symbol' => '&#36;',
      ),
      'us' => array(
        'currency' => 'USD',
        'symbol' => '&#36;',
      ),
      'uk' => array(
        'currency' => 'GBP',
        'symbol' => '&#8356;',
      ),
      'ph' => array(
        'currency' => 'PHP',
        'symbol' => '&#8369;',
    )));
    add_option(self::prefix . 'default', 'au');
    get_option(self::prefix . 'country', 'au');
    add_option(self::prefix . 'base', 'AUD');
  }

  public function add_shortcodes() {
    add_shortcode(self::prefix . 'country_select', array($this, 'shortcode_country_select'));
    add_shortcode(self::prefix . 'convert', array($this, 'shortcode_convert'));
  }

  public function add_filters() {
    add_filter('query_vars', array($this, 'queryvars'));
  }

  public function queryvars($qvars) {
    $qvars[] = 'mr-fixer-io-cur';
    return $qvars;
  }

  public function shortcode_country_select($atts) {
    $countries = get_option(self::prefix . 'countries');
    $links = '';
    foreach ($countries as $country => $currency) {
      $links .= "<a class=\flag flag-{$country}\" href=" . self::currency_url($country) . ">{$country}</a>\n";
    }
    return "
      <div class=\"mr-fixer-io-country-select\">
        {$links}
      </div>
    ";
  }

  public function shortcode_convert($atts) {
    $atts = shortcode_atts(array(
      "country" => self::get_country(),
      "value" => '1.00',
    ), $atts, self::prefix . 'convert');
    $countries = get_option(self::prefix . 'countries');
    if ($atts['country'] === get_option(self::prefix . 'default')) {
      return "{$countries[$atts['country']]['symbol']}{$atts['value']}";
    }
    $rates = self::get_rates();
    $converted = number_format(((float)$atts['value'] * (float)$rates['rates'][$countries[$atts['country']]['currency']]), 2);
    return "{$countries[$atts['country']]['symbol']}{$converted}";
  }

  private function currency_url($country) {
    return add_query_arg(array('mr-fixer-io-cur' => $country));
  }

  private function get_rates() {
    if (null === $this->rates) {
      $countries = get_option(self::prefix . 'countries');
      $base = get_option(self::prefix . 'base');
      $currencies = array();
      foreach ($countries as $country) {
        $currencies[] = $country['currency'];
      }
      $currencies = implode(',', $currencies);
      $this->rates = json_decode(file_get_contents(self::fixer_io_url . "?base={$base}&symbols={$currencies}"), TRUE);
    }
    return $this->rates;
  }

  private function get_country() {
    if (null === $this->country) {
      $this->country = get_query_var('mr-fixer-io-cur', get_option(self::prefix . 'default'));
      if ($this->country !== get_option(self::prefix . 'country')) {
        update_option(self::prefix . 'country', $this->country);
      }
    }
    return $this->country;
  }


}

mrFixerIO::getInstance();

?>
