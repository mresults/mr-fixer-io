# Fixer.io Currency Converter
Fixer.io Currency converter is a WordPress plugin which utilises the [fixer.io API](http://fixer.io) to provide currency conversion options.  These options are provided via shortcodes, and a widget.

## Usage
The plugin's available currencies, default selected currency, and base currency, may be selected via the plugin's settings page.
### Shortcodes
#### `[mr_fixer_io_currency_select]`
This shortcode will output a list of preferred currency links. The available currencies can be configured via the plugin settings.  There are no attributes.
#### `[mr_fixer_io_convert value= currency= ]`
This shortcode will convert a value to either the known preferred currency, or a currency defined via the currency attribute.  There are two attributes:
- `value` - This is an integer or decimal value, which will be converted from the configured base currency to the supplied or preferred currency.  If the attribute is not supplied, the value will default to `1.00`.
- `currency` - This is a string corresponding to the currency code of one of the configured available currencies.  If the currency is supplied in the shortcode, it will override any currency preference set by the viewer.

#### `[mr_fixer_io_selected_currency]`
This shortcode will display the currently selected currency.

### Widgets
- A currency selection widget is provided.  The options are defined by the available currencies selected in the plugin's settings.
- A selected currency widget is provided.  The widget will display the ISO code of the currently selected currency.

## Features
- Retains selected currency via local storage, with session fallback
- 'Instant' conversion via Javascript DOM manipulation with server request fallback
- Option to prefix converted value with currency code

## Libraries
- Uses a modified version of [Rational Option Pages](https://github.com/jeremyHixon/RationalOptionPages) to simplify settings page generation
- Uses the [WP Session Manager](https://wordpress.org/plugins/wp-session-manager/) plugin to handle fallback of currency selection storage

## History
The plugin was built as a training exercise for [Marketing Results](https://www.marketingresults.com.au) staff, to introduce WordPress plugin development concepts.  The initial plugin provided
- A shortcode to render a currency selection widget
- A shortcode to provide conversion for a given value

Over time, the plugin has been extended to provide more functionality.  See the Usage section below for more information.

## Reuse
This plugin is free to use and modify.  It is covered by the [GPL-2.0+ License](http://www.gnu.org/licenses/gpl-2.0.txt).

## Roadmap
The following features will be built
- [x] ~~Currency selection widget~~ (done)
- [x] ~~Shortcode to display the selected currency~~ (done)
- [x] ~~Widget to display the selected currency~~ (done)
- [x] ~~Dashboard settings page~~ (done)
- [x] ~~'Base currency' setting~~ (done)
- [x] ~~'Available currencies' setting~~ (done)
- [x] ~~'Default currency' setting~~ (done)
- [x] ~~'Prefix currency ISO code before converted values' setting~~
- [x] ~~Cache fixer.io requests to save bandwidth~~ (done)
- [x] ~~Enable currency conversion to occur via AJAX request~~ (done)
- [ ] Add default styling to the currency selector shortcode and widget
- [ ] Add default styling to the selected currency shortcode and widget
