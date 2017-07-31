<?php

class WPP_Widget extends WP_Widget {

    /**
     * Administrative settings.
     *
     * @since	2.3.3
     * @var		array
     */
    private $admin_options = array();

    public function __construct(){

        // Create the widget
        parent::__construct(
            'wpp',
            'WordPress Popular Posts',
            array(
                'classname'		=>	'popular-posts',
                'description'	=>	__( 'The most Popular Posts on your blog.', 'wordpress-popular-posts' )
            )
        );

        $this->admin_options = WPP_Settings::get( 'admin_options' );

        // Widget's AJAX hook
        if ( $this->admin_options['tools']['ajax'] ) {
            add_action( 'wp_ajax_wpp_get_popular', array( $this, 'get_popular') );
            add_action( 'wp_ajax_nopriv_wpp_get_popular', array( $this, 'get_popular') );
        }

    }

    /**
     * Outputs the content of the widget.
     *
     * @since	1.0.0
     * @param	array	args		The array of form elements
     * @param	array	instance	The current instance of the widget
     */
    public function widget( $args, $instance ){
        /**
         * @var string $name
         * @var string $id
         * @var string $description
         * @var string $class
         * @var string $before_widget
         * @var string $after_widget
         * @var string $before_title
         * @var string $after_title
         * @var string $widget_id
         * @var string $widget_name
         */
        extract( $args, EXTR_SKIP );

        $markup = ( $instance['markup']['custom_html'] || has_filter('wpp_custom_html') || has_filter('wpp_post') )
              ? 'custom'
              : 'regular';

        echo "\n". "<!-- WordPress Popular Posts Plugin [W] [{$instance['range']}] [{$instance['order_by']}] [{$markup}]" . ( !empty($instance['pid']) ? " [PID]" : "" ) . ( !empty($instance['cat']) ? " [CAT]" : "" ) . ( !empty($instance['author']) ? " [UID]" : "" ) . " -->" . "\n";

        echo "\n" . $before_widget . "\n";

        // Has user set a title?
        if ( '' != $instance['title'] ) {

            $title = apply_filters( 'widget_title', $instance['title'] );

            if (
                $instance['markup']['custom_html']
                && $instance['markup']['title-start'] != ""
                && $instance['markup']['title-end'] != ""
            ) {
                echo htmlspecialchars_decode( $instance['markup']['title-start'], ENT_QUOTES) . $title . htmlspecialchars_decode($instance['markup']['title-end'], ENT_QUOTES );
            } else {
                echo $before_title . $title . $after_title;
            }
        }

        // Expose Widget ID for customization
        $instance['widget_id'] = $widget_id;

        // Get posts
        if ( $this->admin_options['tools']['ajax'] ) {

            if ( empty( $before_widget ) || !preg_match( '/id="[^"]*"/', $before_widget ) ) {
            ?>
            <p><?php printf( __('Error: cannot ajaxify WordPress Popular Posts on this theme. It\'s missing the <em>id</em> attribute on before_widget (see <a href="%s" target="_blank" rel="nofollow">register_sidebar</a> for more)', 'wordpress-popular-posts'), 'https://codex.wordpress.org/Function_Reference/register_sidebar' ); ?>.</p>
            <?php
            } else {
            ?>
            <script type="text/javascript">
                /* jQuery is available, so proceed */
                if ( window.jQuery ) {

                    jQuery(document).ready(function($){

                        var widget_container = $('#<?php echo $widget_id; ?>');
                        widget_container.append('<p class="wpp-loader"><span><?php _e( "Loading...", "wordpress-popular-posts" ); ?></span></p>');

                        $.get(
                            '<?php echo admin_url('admin-ajax.php'); ?>',
                            {
                                action: 'wpp_get_popular',
                                id: '<?php echo $this->number; ?>'
                            }, function( response ){
                                widget_container.children("p.wpp-loader").remove();
                                widget_container.append(response);
                            }
                        );

                    });

                } /* jQuery is not defined */
                else {
                    if ( window.console && window.console.log ) {
                        window.console.log( 'WordPress Popular Posts: jQuery is not defined!' );
                    }
                }
            </script>
            <?php
            }
        } else {
            $this->get_popular( $instance );
        }

        echo "\n" . $after_widget . "\n";

    }

    /**
     * Generates the administration form for the widget.
     *
     * @since	1.0.0
     * @param	array	instance	The array of keys and values for the widget.
     */
    public function form( $instance ){

        $instance = WPP_Helper::merge_array_r(
            WPP_Settings::$defaults[ 'widget_options' ],
            (array) $instance
        );
        $wpp_image = WPP_Image::get_instance();

        include( plugin_dir_path( __FILE__ ) . '/widget-form.php' );

    }

    /**
     * Processes the widget's options to be saved.
     *
     * @since	1.0.0
     * @param	array	new_instance	The previous instance of values before the update.
     * @param	array	old_instance	The new instance of values to be generated via the update.
     * @return	array	instance		Updated instance.
     */
    public function update( $new_instance, $old_instance ){

        $wpp_image = WPP_Image::get_instance();

        $instance = $old_instance;

        $instance['title'] = htmlspecialchars( stripslashes_deep(strip_tags( $new_instance['title'] )), ENT_QUOTES );
        $instance['limit'] = ( WPP_Helper::is_number($new_instance['limit']) && $new_instance['limit'] > 0 )
          ? $new_instance['limit']
          : 10;
        $instance['range'] = $new_instance['range'];
        $instance['time_quantity'] = ( WPP_Helper::is_number($new_instance['time_quantity']) && $new_instance['time_quantity'] > 0 )
          ? $new_instance['time_quantity']
          : 24;
        $instance['time_unit'] = $new_instance['time_unit'];
        $instance['order_by'] = $new_instance['order_by'];

        // FILTERS
        // user did not set a post type name, so we fall back to default
        $instance['post_type'] = ( '' == $new_instance['post_type'] )
          ? 'post,page'
          : $new_instance['post_type'];

        $instance['freshness'] = isset( $new_instance['freshness'] );

        // Post / Page / CTP filter
        $ids = array_filter( explode( ",", rtrim(preg_replace( '|[^0-9,]|', '', $new_instance['pid'] ), ",") ), 'is_numeric' );
        // Got no valid IDs, clear
        if ( empty( $ids ) ) {
            $instance['pid'] = '';
        }
        else {
            $instance['pid'] = implode( ",", $ids );
        }

        // Category filter
        $ids = array_filter( explode( ",", rtrim(preg_replace( '|[^0-9,-]|', '', $new_instance['cat'] ), ",") ), 'is_numeric' );
        // Got no valid IDs, clear
        if ( empty( $ids ) ) {
            $instance['cat'] = '';
        }
        else {
            $instance['cat'] = implode( ",", $ids );
        }

        // Author filter
        $ids = array_filter( explode( ",", rtrim(preg_replace( '|[^0-9,]|', '', $new_instance['uid'] ), ",") ), 'is_numeric' );
        // Got no valid IDs, clear
        if ( empty( $ids ) ) {
            $instance['uid'] = '';
        }
        else {
            $instance['uid'] = implode( ",", $ids );
        }

        $instance['shorten_title']['words'] = $new_instance['shorten_title-words'];
        $instance['shorten_title']['active'] = isset( $new_instance['shorten_title-active'] );
        $instance['shorten_title']['length'] = ( WPP_Helper::is_number($new_instance['shorten_title-length']) && $new_instance['shorten_title-length'] > 0 )
          ? $new_instance['shorten_title-length']
          : 25;

        $instance['post-excerpt']['keep_format'] = isset( $new_instance['post-excerpt-format'] );
        $instance['post-excerpt']['words'] = $new_instance['post-excerpt-words'];
        $instance['post-excerpt']['active'] = isset( $new_instance['post-excerpt-active'] );
        $instance['post-excerpt']['length'] = ( WPP_Helper::is_number($new_instance['post-excerpt-length']) && $new_instance['post-excerpt-length'] > 0 )
          ? $new_instance['post-excerpt-length']
          : 55;

        $instance['thumbnail']['active'] = false;
        $instance['thumbnail']['width'] = 15;
        $instance['thumbnail']['height'] = 15;

        // can create thumbnails
        if ( $wpp_image->can_create_thumbnails() ) {

            $instance['thumbnail']['active'] = isset( $new_instance['thumbnail-active'] );
            $instance['thumbnail']['build'] = $new_instance['thumbnail-size-source'];

            // Use predefined thumbnail sizes
            if ( 'predefined' == $new_instance['thumbnail-size-source'] ) {

                $default_thumbnail_sizes = $wpp_image->get_image_sizes();
                $size = $default_thumbnail_sizes[ $new_instance['thumbnail-size'] ];

                $instance['thumbnail']['width'] = $size['width'];
                $instance['thumbnail']['height'] = $size['height'];
                $instance['thumbnail']['crop'] = $size['crop'];

            } // Set thumbnail size manually
            else {
                if ( WPP_Helper::is_number($new_instance['thumbnail-width']) && WPP_Helper::is_number($new_instance['thumbnail-height']) ) {
                    $instance['thumbnail']['width'] = $new_instance['thumbnail-width'];
                    $instance['thumbnail']['height'] = $new_instance['thumbnail-height'];
                    $instance['thumbnail']['crop'] = true;
                }
            }

        }

        $instance['rating'] = isset( $new_instance['rating'] );
        $instance['stats_tag']['comment_count'] = isset( $new_instance['comment_count'] );
        $instance['stats_tag']['views'] = isset( $new_instance['views'] );
        $instance['stats_tag']['author'] = isset( $new_instance['author'] );
        $instance['stats_tag']['date']['active'] = isset( $new_instance['date'] );
        $instance['stats_tag']['date']['format'] = empty($new_instance['date_format'])
          ? 'F j, Y'
          : $new_instance['date_format'];

        $instance['stats_tag']['category'] = isset( $new_instance['category'] );
        $instance['markup']['custom_html'] = isset( $new_instance['custom_html'] );
        $instance['markup']['wpp-start'] = empty($new_instance['wpp-start'])
          ? htmlspecialchars( '<ul class="wpp-list">', ENT_QUOTES )
          : htmlspecialchars( $new_instance['wpp-start'], ENT_QUOTES );

        $instance['markup']['wpp-end'] = empty($new_instance['wpp-end'])
          ? htmlspecialchars( '</ul>', ENT_QUOTES )
          : htmlspecialchars( $new_instance['wpp-end'], ENT_QUOTES );

        $instance['markup']['post-html'] = empty($new_instance['post-html'])
          ? htmlspecialchars( '<li>{thumb} {title} {stats}</li>', ENT_QUOTES )
          : htmlspecialchars( $new_instance['post-html'], ENT_QUOTES );

        $instance['markup']['title-start'] = empty($new_instance['title-start'])
          ? ''
          : htmlspecialchars( $new_instance['title-start'], ENT_QUOTES );

        $instance['markup']['title-end'] = empty($new_instance['title-end'])
          ? '' :
          htmlspecialchars( $new_instance['title-end'], ENT_QUOTES );

        return $instance;

    }

    /**
     * Returns HTML list via AJAX
     *
     * @since	2.3.3
     */
    public function get_popular( $instance = null ) {

        if ( defined('DOING_AJAX') && DOING_AJAX ) {

            if ( isset( $_GET['id'] ) && WPP_helper::is_number( $_GET['id'] ) ) {

                $id = $_GET['id'];
                $widget_instances = $this->get_settings();

                if ( isset( $widget_instances[$id] ) ) {
                    $instance = $widget_instances[$id];
                }

            }

        }

        if ( is_array( $instance ) && !empty( $instance ) ) {

            $popular_posts = new WPP_Query( $instance );
            $output = new WPP_Output( $popular_posts->get_posts(), $instance );

            $output->output();

        }

        if ( defined('DOING_AJAX') && DOING_AJAX )
            wp_die();

    }

} // end class WPP_Widget
