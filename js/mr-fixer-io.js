/**
 * Core Fixer.io conversion functionality
 * 
 * Handles triggering of conversions from currency selector widgets and
 * shortcodes, and display of currently selected currency
 */

(function ($) {

  // Define the methods that will be used in the plugin
  var methods = {

    // Performs some initial DOM transformation
    init: function() {

      $(document).ready(function() {

        // Perform conversion on all fixer values
        $('.mr-fixer-io-value').each(function() {$(this).mrFixerIO('convert');});

        // Update all currency displays
        $('.mr-fixer-io-currency').each(function() {$(this).mrFixerIO('display');});

        // Add conversion hook to all fixer currency selector links
        $('.mr-fixer-io-convert-trigger').click(function(e) {

          // Remove the regular link click behaviour
          e.preventDefault();

          // Set a local storage value to the selected currency
          localStorage.setItem('MRFixerIOCurrency', $(this).attr('mrfixerio:curr'));

          // Convert all fixer values to the new currency
          $('.mr-fixer-io-value').each(function() {$(this).mrFixerIO('convert');});

          // Update all currency displays
          $('.mr-fixer-io-currency').each(function() {$(this).mrFixerIO('display');});

        });
      });

    },

    // Fetches data and overrides with a locally stored currency code
    data: function() {

      // Get a locally stored currency code if it exists
      var currency = localStorage.getItem('MRFixerIOCurrency');

      // If the currency code isn't null, override the supplied data object
      if (currency !== null) {
        MRFixerIO['currency'] = currency;
      }

      // Return the data object
      return MRFixerIO;

    },
        
    /**
     * Convert fixer values
     *
     * Options are overrideable
     *
     * currency: the currency to convert to
     * value: the value to convert
     */
    convert: function(options) {

      // Fetch fixer data
      var data = $.fn.mrFixerIO('data');

      // Compute settings based on supplied values
      var settings = $.extend({

        // Override currency defaults if in target element's attributes
        currency: ($(this).attr('mrfixerio:curr'))
          ? $(this).attr('mrfixerio:curr')
          : data.currency,

        // Override value defaults if in target element's attributes
        value: ($(this).attr('mrfixerio:val'))
          ? $(this).attr('mrfixerio:val')
          : 1

      /* If an options hash is supplied, its values will override anything
         set above */
      }, options);

      // If the supplied currency is a string convert to an object
      if (typeof settings.currency !== 'object') {

        /* Override it with the meta object from the corresponding allowed 
           currency */
        settings.currency = data['settings'].allowed_currencies[settings.currency];
      }

      // Generate a converted currency object
      var converted = {

        // The full meta object of the given currency
        currency: data['settings'].allowed_currencies[settings.currency.code],

        /* Perform some magic to convert the given value and format it to a
           nicely presented number */
        value: Number(
          (settings.currency.code === data['settings'].default_currency)
            ? settings.value
            : data.rates.rates[settings.currency.code] * settings.value
        ).toFixed(2).replace(/(\d)(?=(\d{3})+\.)/g, '$1,')
      };

      /* Replate the HTML of the target object with the symbol and converted
         value */
      $(this).html(converted.currency.symbol + converted.value);

    },

    // Display the currently selected currency
    display: function() {

      // Fetch fixer data
      var data = $.fn.mrFixerIO('data');

      var currency = data.currency;

      // If the supplied currency is a string convert to an object
      if (typeof currency !== 'object') {

        /* Override it with the meta object from the corresponding allowed 
           currency */
        currency = data['settings'].allowed_currencies[currency];
      }

      $(this).html(currency.code);

    }

  };

  // Container logic for the plugin
  $.fn.mrFixerIO = function(methodOrOptions) {

    // Determine if supplied is a method string with options, or just options
    if (methods[methodOrOptions]) {
      // If it's a recognised method, then call it with any options
      return methods[methodOrOptions].apply(this,Array.prototype.slice.call(arguments, 1));
    }
    else if (typeof methodOrOptions === 'object' || ! methodOrOptions) {
      // If not, try to apply the options to the init method
      return methods.init.apply(this, arguments);
    }
    else {
      // Otherwise emit an error because the called method doesn't exist
      $.error( 'Method ' +  methodOrOptions + ' does not exist for jQuery.MrFixerIO' );
    }

  }

}(jQuery));

// Initialise the magic
jQuery.fn.mrFixerIO();
