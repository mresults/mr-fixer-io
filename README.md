# Fixer.io Currency Converter
Fixer.io Currency converter is a WordPress plugin which utilises the [fixer.io API](http://fixer.io) to provide currency conversion options.  These options are provided via shortcodes, and a widget.

## History
The plugin was built as a training exercise for [Marketing Results](https://www.marketingresults.com.au) staff, to introduce WordPress plugin development concepts.  The initial plugin provided
- A shortcode to render a currency selection widget
- A shortcode to provide conversion for a given value

Over time, the plugin has been extended to provide more functionality.  See the Usage section below for more information.

## Reuse
This plugin is free to use and modify.  It is covered by the [GPL-2.0+ License](http://www.gnu.org/licenses/gpl-2.0.txt).

## Usage
The base currency is Australian Dollars.  All values will be converted from this base currency.
### Shortcodes
#### `[mr_fixer_io_country_select]`
This shortcode will output a list of preferred currency links.  The options are statically limited to `AU`, `US`, `UK`, `PH`.  There are no attributes.
#### `[mr_fixer_io_convert value= country= ]`
This shortcode will convert a value to either the known preferred country, or a country defined via the country attribute.  There are two attributes:
- `value` - This is an integer or decimal value, which will be converted from AUD to the supplied or preferred country.  If the attribute is not supplied, it will default to `1.00`.
- `country` - This is a string corresponding to one of the following: `AU`, `US`, `UK`, `PH`.  If the country is supplied in the shortcode, it will override any country preference set by the viewer.

#### `[mr_fixer_io_selected_currency]`
This shortcode will display the currently selected currency.

### Widgets
- A currency selection widget is provided.  The options are statically limited to `AU`, `US`, `UK`, `PH`.
- A selected currency widget is provided.  The widget will display the ISO code of the currently selected country.

## Roadmap
The following features will be built
- [x] ~~Currency selection widget~~ (done)
- [x] ~~Shortcode to display the selected currency~~ (done)
- [x] ~~Widget to display the selected country~~ (done)
- [ ] Dashboard settings page
- [ ] 'Base currency' setting
- [ ] 'Available currencies' setting
- [ ] 'Default currency' setting
- [ ] 'Prefix country ISO code before converted values' setting
- [ ] Cache fixer.io requests to save bandwidth
- [ ] Enable currency conversion to occur via AJAX request
- [ ] Add default styling to the currency selector shortcode and widget
- [ ] Add default styling to the selected country shortcode and widget
