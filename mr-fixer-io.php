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

  # This plugin class
  private static $instance;

  # Stores rates after an initial call, for the life of the request
  private $rates;

  # Stores the preferred country after an initial call, for the life of the 
  # request
  private $country;

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

    # Add a WordPress option containing the countries / currencies used
    # in the plugin
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

    # Add a WordPress option containing the default selected country
    add_option(self::prefix . 'default', 'au');

    # Add a WordPress option containing the base currency
    add_option(self::prefix . 'base', 'AUD');
  }

  # Add some WordPress shortcode hooks
  public function add_shortcodes() {

    # Call the plugin's country select shortcode method when the shortcode is 
    # called
    add_shortcode(self::prefix . 'country_select', array($this, 'shortcode_country_select'));
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
  }

  # Adds some query variables to WordPress' allowed query variables list
  public function queryvars($qvars) {

    # This variable tells the plugin which currency to convert to
    $qvars[] = 'mr-fixer-io-cur';
    return $qvars;
  }

  # This shortcode renders a country selection widget
  public function shortcode_country_select($atts) {
    return self::country_select();
  }

  # Perform currency conversion for given attributes
  public function shortcode_convert($atts) {

    # Set some default attributes but allow them to be overridden
    $atts = shortcode_atts(array(

      # Country to convert currency to
      "country" => self::get_country(),

      # Value to convert
      "value" => '1.00',
    ), $atts, self::prefix . 'convert');

    # Get the lst of allowed countries
    $countries = get_option(self::prefix . 'countries');

    # If the provided country is the same as the base country, don't bother
    # converting the value 
    if ($atts['country'] === get_option(self::prefix . 'default')) {
      return "{$countries[$atts['country']]['symbol']}{$atts['value']}";
    }

    # Call the get_rates method which returns current currency conversion rates
    $rates = self::get_rates();

    # Convert the value to the given rate
    $converted = number_format(((float)$atts['value'] * (float)$rates['rates'][$countries[$atts['country']]['currency']]), 2);

    # Return the rate, prepended with the currency symbol for the given country
    return "{$countries[$atts['country']]['symbol']}{$converted}";
  }

  # Returns a country selection widget
  public function country_select() {

    # Get the list of countries to render
    $countries = get_option(self::prefix . 'countries');

    # Instantiate the string containing the widget HTML
    $links = '';

    # Iterate through the countries
    foreach ($countries as $country => $currency) {

      # Append a link to the currency conversion URL for this country
      $links .= "<a class=\flag flag-{$country}\" href=" . self::currency_url($country) . ">{$country}</a>\n";
    }

    # Return an HTML widget containing the links
    return "
      <div class=\"mr-fixer-io-country-select\">
        {$links}
      </div>
    ";

  }

  # Set the conversion URL for the given country
  private function currency_url($country) {

    # Add the country 
    return add_query_arg(array('mr-fixer-io-cur' => $country));
  }

  # Return the current exchange rates
  private function get_rates() {

    # If there is no rates property it needs to be set
    if (null === $this->rates) {

      # Get our list of allowed countries
      $countries = get_option(self::prefix . 'countries');

      # Get our base currency
      $base = get_option(self::prefix . 'base');

      # Instantiate an empty container to fill with currencies
      $currencies = array();

      # Iterate through the countries
      foreach ($countries as $country) {

        # Append the currency of this country to the currencies container
        $currencies[] = $country['currency'];
      }

      # Turn the currencies container into a comma delmited string
      $currencies = implode(',', $currencies);

      # Fetch the rates from fixer.io and save them to the rates property
      $this->rates = json_decode(file_get_contents(self::fixer_io_url . "?base={$base}&symbols={$currencies}"), TRUE);
    }

    # Return the rates
    return $this->rates;
  }

  # Get the currently selected country
  private function get_country() {

    # If there is no country property it needs to be set
    if (null === $this->country) {

      # Call the WP_Session plugin as an object
      $wp_session = WP_Session::get_instance();

      # Set the stored country, depending on if it is in the session or not
      $stored_country = (!empty($wp_session[self::prefix . 'country'])) 

        # If in the session, set it to the country in the session
        ? $wp_session[self::prefix . 'country'] 

        # Otherwise set it to the default country
        : get_option(self::prefix . 'default');

      # Set the country property to the query var, defaulting to the stored
      # country we already have
      $this->country = get_query_var('mr-fixer-io-cur', $stored_country);

      # If the country property does not match the stored country, save the
      # property to the session
      if ($this->country !== $stored_country) {
        $wp_session[self::prefix . 'country'] = $this->country;
      }
    }

    # Return the country we have fetched
    return $this->country;
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
    print $mr_fixer_io->country_select();

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
