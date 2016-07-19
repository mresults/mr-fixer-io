<?php

/**
 * Plugin Name:  fixer.io Currency Conversion by Marketing Results
 * Plugin URI:   https://github.com/mresults/mr-fixer-io 
 * Description:  Provides currency conversion options via fixer.io
 * Version:      1.0.0
 * Author:       Shaun Johnston
 * Author URI:   https://www.marketingresults.com.au
 * Text Domain:  mr-fixer-io
 * License:      GPL-2.0+
 * License URI:  http://www.gnu.org/licenses/gpl-2.0.txt
 */

class MrFixerIO {

  # Prefix used for namespacing options and shortcodes
  const prefix = 'mr_fixer_io_';

  # Location of the fixer.io API endpoint
  const fixer_io_url = 'http://api.fixer.io/latest';

  # Location of the Google API csv for currency symbols
  const google_api_symbols_url = 'https://developers.google.com/adwords/api/docs/appendix/currencycodes.csv';

  private $settings = array(
    'allowed_currencies' => array('AUD','GBP','USD','PHP'),
    'default_currency' => 'AUD',
    'base_currency' => 'AUD'
  );

  # This plugin class
  private static $instance;

  # Stores rates after an initial call, for the life of the request
  private $rates;

  # Stores the preferred currency after an initial call, for the life of the 
  # request
  private $currency;

  # Stores a list of applicable currencies
  private $currencies;

  # Stores a list of allowed currencies
  private $allowed_currencies;

  # Stores a map of currency codes to metadata about those currencies
  private $currency_meta;

  # Get this plugin class instance
  public static function getInstance() {

    # If the plugin has not been instantiated
    if (null === static::$instance) {

      # Instantiate the plugin as a static instance
      static::$instance = new MrFixerIO();
    }

    # Return the plugin instance
    return static::$instance;
  }

  protected function __construct() {

    # Run the activate method when the plugin is activated
    register_activation_hook(__FILE__, array($this, 'activate'));

    # Run the deactivate method when the plugin is deactivated
    register_deactivation_hook(__FILE__, array($this, 'deactivate'));

    # Implement plugin-specific shortcode hooks
    self::add_shortcodes();

    # Implement plugin-specific filters
    self::add_filters();

    # Implement plugin-specific widgets
    self::add_widgets();

    # Implements an options page
    self::add_options_page();

    # Implements Javascript
    self::add_javascript();

  }

  # Dummy - prevents instantiation of a copy of this plugin
  private function __clone() {
  }

  # Dummy - prevents instantiation of a copy of this plugin from a session
  # variable
  private function __wakeup() {
  }

  # Set up this plugin on activation
  public function activate() {

    # Write the plugin's default settings to the database
    add_option(self::prefix . 'settings', $this->settings);

    # Add a WordPress option containing the currencies offered by fixer.io
    add_option(self::prefix . 'currencies', self::fetch_currencies_meta());

    # Add a WordPress option containing the timestamp the currencies were
    # fetched
    add_option(self::prefix . 'currencies_timestamp', time());

  }

  # Remove plugin settions on deactivation
  public function deactivate() {

    # Delete all the options added when activation occurred
    delete_option(self::prefix . 'settings');
    delete_option(self::prefix . 'currencies');
    delete_option(self::prefix . 'currencies_timestamp');
  }

  # Add some WordPress shortcode hooks
  public function add_shortcodes() {

    # Call the plugin's currency select shortcode method when the shortcode is 
    # called
    add_shortcode(self::prefix . 'currency_select', array($this, 'shortcode_currency_select'));
    # Call the plugin's selected currency shortcode method when the shortcode
    # is called
    add_shortcode(self::prefix . 'selected_currency', array($this, 'shortcode_selected_currency'));
    # Call the plugin's currency conversion shortcode method when the 
    # shortcode is called
    add_shortcode(self::prefix . 'convert', array($this, 'shortcode_convert'));
  }

  # Add some WordPress filter hooks
  public function add_filters() {

    # Call the queryvars method when WordPress' query_vars filter is called 
    add_filter('query_vars', array($this, 'queryvars'));
  }

  # Adds some WordPress widget hooks
  public function add_widgets() {

    # Require the widgets include
    require_once('lib/widgets.php');

    # Currency selector widget
    add_action('widgets_init', function() { 
      register_widget('mrFixerIO_Widget_Currency_Selector'); 
    });

    # Selected currency widget
    add_action('widgets_init', function() { 
      register_widget('mrFixerIO_Widget_Selected_Currency'); 
    });

  }

  # Enqueue Javascript
  public function add_javascript() {

    # Enqueue core conversion Javascript on enqueue_scripts hook
    add_action('wp_enqueue_scripts', array($this, 'enqueue_javascript_core'));

  }

  # Enqueue the core conversion Javascript
  public function enqueue_javascript_core() {

    # Build an array of data to supply to the core conversion script
    $data = array(
      'settings' => array(
        'allowed_currencies' => self::get_allowed_currencies(),
        'default_currency' => self::get_setting('default_currency'),
        'base_currency' => self::get_setting('base_currency'),
        'show_currency_code' => self::get_setting('show_currency_code'),
      ),
      'currency' => self::get_currency(),
      'rates' => self::get_rates(),
    );

    # Enqueue the core conversion script
    wp_enqueue_script('mr-ifs-sync-sync', plugin_dir_url(__FILE__) . 'js/mr-fixer-io.js', array('jquery'));
    wp_localize_script('mr-ifs-sync-sync', 'MRFixerIO', $data);

  }

  # Add an options page, using the Rational Option Pages library
  public function add_options_page() {

    # Include the Rational Option Pages library
    require_once('lib/RationalOptionPages.php');

    # Define the options page
    $pages = array(
      self::prefix . 'settings' => array(
        'parent_slug' => 'options-general.php',
        'page_title' => __('fixer.io Settings', 'text-domain'),
        'icon_url' => 'dashicons-chart-area',
        'sections' => array(
          'defaults' => array(
            'title' => __('Defaults', 'text-domain'),
            'text' => '<p>' . __('Set some default options for fixer.io', 'text-domain') . '</p>',
            'fields' => array(
              'allowed_currencies' => array(
                'title' => __('Allowed Currencies', 'text-domain'),
                'type' => 'select',
                'text' => __('Set the currencies that visitors can choose to convert to', 'text-domain'),
                'attributes' => array(
                  'multiple' => 'true',
                  'size' => '12',
                ),
                'choices' => call_user_func(function() {
                  $currencies = self::get_currencies();
                  $choices = array();
                  foreach ($currencies as $currency) {
                    $choices[$currency['code']] = "[{$currency['code']}] {$currency['name']}";
                  }
                  ksort($choices);
                  return $choices;
                }),
              ),
              'default_currency' => array(
                'title' => __('Default Currency', 'text-domain'),
                'type' => 'select',
                'text' => __('Set the currency that will be the default'),
                'choices' => call_user_func(function() {
                  $currencies = self::get_currencies();
                  $choices = array();
                  foreach ($currencies as $currency) {
                    $choices[$currency['code']] = "[{$currency['code']}] {$currency['name']}";
                  }
                  ksort($choices);
                  return $choices;
                }),
              ),
              'base_currency' => array(
                'title' => __('Base Currency', 'text-domain'),
                'type' => 'select',
                'text' => __('Set the base currency of your website'),
                'choices' => call_user_func(function() {
                  $currencies = self::get_currencies();
                  $choices = array();
                  foreach ($currencies as $currency) {
                    $choices[$currency['code']] = "[{$currency['code']}] {$currency['name']}";
                  }
                  ksort($choices);
                  return $choices;
                }),
              ),
              'show_currency_code' => array(
                'title' => __('Show Currency Code', 'text-domain'),
                'type' => 'select',
                'text' => __('Determine if / when to display the currency code'),
                'choices' => array(
                  'never' => 'Never',
                  'nosymbol' => 'When no symbol exists for the currency',
                  'always' => 'Always',
                ),
              ),
            ),
          ),
        ),
      ),
    );

    $options = new RationalOptionPages($pages);        

  }

  # Adds some query variables to WordPress' allowed query variables list
  public function queryvars($qvars) {

    # This variable tells the plugin which currency to convert to
    $qvars[] = 'mr-fixer-io-cur';
    return $qvars;
  }

  # This shortcode renders a currency selection widget
  public function shortcode_currency_select($atts) {
    return self::currency_select();
  }

  # This shortcode renders a fragment showing the selected currency 
  public function shortcode_selected_currency($atts) {
    return self::selected_currency();
  }

  # Perform currency conversion for given attributes
  public function shortcode_convert($atts) {

    # Check for the existence of a supplied currency attribute
    $currency_supplied = isset($atts['currency']);

    # Set some default attributes but allow them to be overridden
    $atts = shortcode_atts(array(

      # Currency to convert to
      "currency" => call_user_func(function() {
         $currency = self::get_currency();
         return $currency['code'];
      }),

      # Value to convert
      "value" => '1.00',
    ), $atts, self::prefix . 'convert');

    # Get the list of allowed currencies
    $currencies = self::get_allowed_currencies();

    # Initialise an empty HTML attributes array
    $html_atts = array();

    # Add a currency HTML attribute if it is present in the shortode
    if ($currency_supplied) {
      $html_atts[] = 'mrfixerio:curr="' . $atts['currency'] . '"';
    }

    $html_atts[] = 'mrfixerio:val="' . $atts['value'] . '"';

    # If the provided currency is the same as the base currency, don't bother
    # converting the value 
    if ($atts['currency'] === self::get_setting('base_currency')) {
      return "<span class=\"mr-fixer-io-value\" " . implode(" ", $html_atts) . ">{$currencies[$atts['currency']]['symbol']}{$atts['value']}</span>";
    }

    # Call the get_rates method which returns current currency conversion rates
    $rates = self::get_rates();

    # Convert the value to the given rate
    $converted = number_format(((float)$atts['value'] * (float)$rates['rates'][$currencies[$atts['currency']]['code']]), 2);

    $display_code = (self::get_currency_display_setting($currencies[$atts['currency']])) ? $currencies[$atts['currency']]['code'] : '';

    # Return the rate, prepended with the currency symbol for the given currency
    return "<span class=\"mr-fixer-io-value\" " . implode(" ", $html_atts) . ">{$currencies[$atts['currency']]['symbol']}{$display_code}{$converted}</span>";
  }

  # Returns an HTML fragment containing the selected currency
  public function selected_currency() {

    # Get the currently selected currency
    $currency = self::get_currency();

    # Return the currency code for the currency
    return '<span class="mr-fixer-io-currency">' . $currency['code'] . '</span>';

  }

  # Returns a currency selection widget
  public function currency_select() {

    # Get the list of currencies to render
    $currencies = self::get_allowed_currencies();

    # Instantiate the string containing the widget HTML
    $links = '';

    # Iterate through the currencies
    foreach ($currencies as $currency) {

      # Append a link to the currency conversion URL for this currency
      $links .= "<a class=\"mr-fixer-io-convert-trigger\" mrfixerio:curr=\"{$currency['code']}\" href=" . self::currency_url($currency['code']) . ">{$currency['code']}</a>\n";
    }

    # Return an HTML widget containing the links
    return "
      <div class=\"mr-fixer-io-currency-select\">
        {$links}
      </div>
    ";

  }

  # Set the conversion URL for the given currency
  private function currency_url($currency) {

    # Add the currency 
    return add_query_arg(array('mr-fixer-io-cur' => $currency));
  }

  # Returns the value of the supplied setting name
  private function get_setting($setting) {

    # Get the settions option - this should return an array of settings
    $settings = get_option(self::prefix . 'settings', $this->settings);

    # Return the given setting, or null if it doesn't exist
    return (isset($settings[$setting])) ? $settings[$setting] : NULL;

  }

  # Return the current exchange rates
  private function get_rates() {

    # If there is no rates property it needs to be set
    if (null === $this->rates) {

      # Get our list of allowed currencies
      $allowed_currencies = self::get_allowed_currencies();

      # Get our base currency
      $base = self::get_setting('base_currency');

      # Instantiate an empty container to fill with currencies
      $currencies = array();

      # Iterate through the allowed currencies
      foreach ($allowed_currencies as $allowed_currency) {

        # Append the currency of this allowed currency  to the currencies 
        # container
        $currencies[] = $allowed_currency['code'];
      }

      # Turn the currencies container into a comma delmited string
      $currencies = implode(',', $currencies);

      # Fetch the rates from fixer.io and save them to the rates property
      $this->rates = json_decode(file_get_contents(self::fixer_io_url . "?base={$base}&symbols={$currencies}"), TRUE);
    }

    # Return the rates
    return $this->rates;
  }

  private function get_currency_display_setting($currency) {

    $display_currency_setting = self::get_setting('show_currency_code');

    if ($display_currency_setting === 'always') {
      return true;
    }

    if ((!$currency['symbol']) && $display_currency_setting == 'nosymbol') {
     return true;
    }

    return false;

  }

  # Gets a list of allowed currencies
  private function get_allowed_currencies() {

    # If there is no allowed currency list it needs to be set
    if (null === $this->allowed_currencies) {

      # Get the stored list of allowed currencies, if it exists
      $allowed_currencies = self::get_setting('allowed_currencies');

      # Get the list of applicable currencies
      $currencies = self::get_currencies();

      # Initialise a shared allowed currencies array
      $this->allowed_currencies = array();

      # Iterate through the allowed currencies and append applicable currency
      # data to the shared array
      foreach ($allowed_currencies as $currency) {

        $this->allowed_currencies[$currency] = $currencies[$currency];

      }

    }

    return $this->allowed_currencies;

  }

  # Gets a list of applicable currencies
  private function get_currencies() {

    # If there is no currency list it needs to be set
    if (null === $this->currencies) {

      # Get the stored list of applicable currencies, if it exists
      $this->currencies = get_option(self::prefix . 'currencies');

      # Get the timestamp of the last currency update
      $currencies_timestamp = get_option(self::prefix . 'currencies_timestamp');

      # Check whether the currencies option is set, and whether it is
      # old enough to be refreshed.  If not, fetch a new set
      if (!count($this->currencies) || (time() - $currencies_timestamp > 2592000 /* 30 days in seconds */)) {

        self::fetch_currencies_meta();

        # Now we have a map of currencies to metadata, get the timestamp and
        # save the values
        update_option(self::prefix . 'currencies', $this->currencies);
        update_option(self::prefix . 'currencies_timestamp', time());

      }

    }

    return $this->currencies;

  }

  # Get the currently selected currency
  private function get_currency() {

    # If there is no currency property it needs to be set
    if (null === $this->currency) {

      # Call the WP_Session plugin as an object
      $wp_session = WP_Session::get_instance();

      # Get the list of applicable currencies
      $currencies = self::get_currencies();

      # Set the stored currency, depending on if it is in the session or not
      $stored_currency = (!empty($wp_session[self::prefix . 'currency'])) 

        # If in the session, set it to the currency in the session
        ? $wp_session[self::prefix . 'currency'] 

        # Otherwise set it to the default currency
        : $currencies[self::get_setting('default_currency')];

      # Set the currency property to the query var, defaulting to the stored
      # currency we already have
      $this->currency = $currencies[get_query_var('mr-fixer-io-cur', $stored_currency['code'])];

      # If the currency property does not match the stored currency, save the
      # property to the session
      if ($this->currency !== $stored_currency) {
        $wp_session[self::prefix . 'currency'] = $this->currency;
      }
    }

    # Return the currency we have fetched
    return $this->currency;
  }

  private function fetch_currencies_meta() {

    # First, get the currency meta

    # Convert a CSV fetched from Google to an array using str_getcsv
    $csv = array_map('str_getcsv', file(self::google_api_symbols_url));

    # Strip the headers off the array
    array_shift($csv);

    # Initialise a new meta array
    $meta = array();

    # Iterate through the array
    foreach ($csv as $cur) {

      # Append metadata about this currency
      $meta[$cur[0]] = array(
        'code' => $cur[0],
        'name' => $cur[1],
        'symbol' => $cur[2], 
      );

    }

    # Now fetch a list of applicable currencies from fixer.io
    $fixer = json_decode(file_get_contents(self::fixer_io_url), TRUE);

    # Initialise a currency array to fill
    $this->currencies = array();

    # Iterate through the array of currencies and cross-inject the meta
    foreach ($fixer['rates'] as $currency => $rate) {

      $this->currencies[$currency] = $meta[$currency];

    }

    return $this->currencies;

  }

}

# Start the plugin
mrFixerIO::getInstance();

?>
