<?php

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
