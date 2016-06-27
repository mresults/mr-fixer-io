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
      static::$instance = new static();
    }

    # Return the plugin instance
    return static::$instance;
  }

  protected function __construct() {

    # Run the activate method when the plugin is activated
    register_activation_hook(__FILE__, array($this, 'activate'));

    # Implement plugin-specific shortcode hooks
    self::add_shortcodes();

    # Implement plugin-specific filters
    self::add_filters();

    # Implement plugin-specific widgets
    self::add_widgets();

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

    # Add a WordPress option containing the default currencies used
    # in the plugin
    add_option(self::prefix . 'allowed_currencies', array('AUD', 'USD', 'GBP', 'PHP'));

    # Add a WordPress option containing the currencies offered by fixer.io
    add_options(self::prefix . 'currencies', self::fetch_currencies_meta);

    # Add a WordPress option containing the timestamp the currencies were
    # fetched
    add_option(self::prefix . 'currencies_timestamp', time());

    # Add a WordPress option containing the default selected currency
    add_option(self::prefix . 'default_currency', 'AUD');

    # Add a WordPress option containing the base currency
    add_option(self::prefix . 'base', 'AUD');
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

    # Currency selector widget
    add_action('widgets_init', function() { 
      register_widget('mrFixerIO_Widget_Currency_Selector'); 
    });

    # Selected currency widget
    add_action('widgets_init', function() { 
      register_widget('mrFixerIO_Widget_Selected_Currency'); 
    });

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

    # Set some default attributes but allow them to be overridden
    $atts = shortcode_atts(array(

      # Currency to convert to
      "currency" => self::get_currency(),

      # Value to convert
      "value" => '1.00',
    ), $atts, self::prefix . 'convert');

    # Get the list of allowed currencies
    $currencies = self::get_allowed_currencies();

    # If the provided currency is the same as the base currency, don't bother
    # converting the value 
    if ($atts['currency'] === get_option(self::prefix . 'default')) {
      return "{$currencies[$atts['currency']]['symbol']}{$atts['value']}";
    }

    # Call the get_rates method which returns current currency conversion rates
    $rates = self::get_rates();

    # Convert the value to the given rate
    $converted = number_format(((float)$atts['value'] * (float)$rates['rates'][$currencies[$atts['currency']]['currency']]), 2);

    # Return the rate, prepended with the currency symbol for the given currency
    return "{$currencies[$atts['currency']]['symbol']}{$converted}";
  }

  # Returns an HTML fragment containing the selected currency
  public function selected_currency() {

    # Get the currently selected currency
    $currency = self::get_currency();

    # Return the currency code for the currency
    return '<span class="' . self::prefix . '"selected_currency">' . $currency['code'] . '</span>';

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
      $links .= "<a class=\"currency\" href=" . self::currency_url($currency) . ">{$currency['code']}</a>\n";
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

  # Return the current exchange rates
  private function get_rates() {

    # If there is no rates property it needs to be set
    if (null === $this->rates) {

      # Get our list of allowed currencies
      $allowed_currencies = self::get_allowed_currencies();

      # Get our base currency
      $base = get_option(self::prefix . 'base');

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

  # Gets a list of allowed currencies
  private function get_allowed_currencies() {

    # If there is no allowed currency list it needs to be set
    if (null === $this->allowed_currencies) {

      # Get the stored list of allowed currencies, if it exists
      $allowed_currencies = get_option(self::prefix . 'allowed_currencies');

      # Get the list of applicable currencies
      $currencies = get_currencies();

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
      if (!count($currencies) || (time() - $currencies_timestamp > 2592000 /* 30 days in seconds */)) {

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

      # Set the stored currency, depending on if it is in the session or not
      $stored_currency = (!empty($wp_session[self::prefix . 'currency'])) 

        # If in the session, set it to the currency in the session
        ? $wp_session[self::prefix . 'currency'] 

        # Otherwise set it to the default currency
        : get_option(self::prefix . 'default');

      # Set the currency property to the query var, defaulting to the stored
      # currency we already have
      $this->currency = get_query_var('mr-fixer-io-cur', $stored_currency);

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

# This widget is a functional replacement for the currency selector shortcode
class mrFixerIO_Widget_Currency_Selector extends WP_Widget {

  # Initialise the widget
  public function __construct() {

    $widget_ops = array(
      'classname' => 'mr_fixer_io_widget_currency_selector',
      'description' => 'Presents currency selection options',
    );

    parent::__construct('mr_fixer_op_widget_currency_selector', 'Fixer.io Currency Selector', $widget_ops);

  }

  # Outputs the widget instance's HTML code to WordPress
  public function widget($args, $instance) {

    #Before widget filter
    print $args['before_widget'];

    # Print the title if one exists
    if (!empty($instance['title'])) {
      print $args['before_title'] . apply_filters('widget_title', $instance['title']) . $args['after_title'];
    }

    # Get the currency selector HTML from the main plugin
    $mr_fixer_io = mrFixerIO::getInstance();

    # ... and then output it
    print $mr_fixer_io->currency_select();

    # After widget filter
    print $args['after_widget'];
  }

  # Simple form to set widget options - not much here at the moment
  public function form($instance) {
    $title = !empty($instance['title']) ? $instance['title'] : __('New title', 'text_domain');

    ?>
      <p>
        <label for="<?php print esc_attr($this->get_field_id('title')); ?>"><?php _e(esc_attr('Title:')); ?></label>
        <input 
          class="widefat" 
          id="<?php print esc_attr($this->get_field_id('title')); ?>"
          name="<?php print esc_attr($this->get_field_name('title')); ?>"
          type="text"
          value="<?php print esc_attr($title); ?>"
        />
    </p>
    <?php
  }

  # Handles input for the above form
  public function update($new_instance, $old_instance) {
    $instance = array();
    $instance['title'] = (!empty($new_instance['title'])) ? strip_tags($new_instance['title']) : '';
    return $instance;
  }

}

# This widget is a functional replacement for the selected currency shortcode
class mrFixerIO_Widget_Selected_Currency extends WP_Widget {

  # Initialise the widget
  public function __construct() {

    $widget_ops = array(
      'classname' => 'mr_fixer_io_widget_selected_currency',
      'description' => 'Presents the currently selected currency',
    );

    parent::__construct('mr_fixer_op_widget_selected_currency', 'Fixer.io Selected Currency', $widget_ops);

  }

  # Outputs the widget instance's HTML code to WordPress
  public function widget($args, $instance) {

    #Before widget filter
    print $args['before_widget'];

    # Print the title if one exists
    if (!empty($instance['title'])) {
      print $args['before_title'] . apply_filters('widget_title', $instance['title']) . $args['after_title'];
    }

    # Get the currency selector HTML from the main plugin
    $mr_fixer_io = mrFixerIO::getInstance();

    # ... and then output it
    print $mr_fixer_io->selected_currency();

    # After widget filter
    print $args['after_widget'];
  }

  # Simple form to set widget options - not much here at the moment
  public function form($instance) {
    $title = !empty($instance['title']) ? $instance['title'] : __('New title', 'text_domain');

    ?>
      <p>
        <label for="<?php print esc_attr($this->get_field_id('title')); ?>"><?php _e(esc_attr('Title:')); ?></label>
        <input 
          class="widefat" 
          id="<?php print esc_attr($this->get_field_id('title')); ?>"
          name="<?php print esc_attr($this->get_field_name('title')); ?>"
          type="text"
          value="<?php print esc_attr($title); ?>"
        />
    </p>
    <?php
  }

  # Handles input for the above form
  public function update($new_instance, $old_instance) {
    $instance = array();
    $instance['title'] = (!empty($new_instance['title'])) ? strip_tags($new_instance['title']) : '';
    return $instance;
  }

}

# Start the plugin
mrFixerIO::getInstance();

?>

