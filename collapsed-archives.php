<?php
/*
Plugin Name: Collapsed Archives
Plugin URI: https://github.com/mpetroff/collapsed-archives-wp
Description: Adds a widget to display archive links using purely CSS-based collapsing.
Version: 1.5
Author: Matthew Petroff
Author URI: https://mpetroff.net/
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

/**
 * Display archive links grouped by year using CSS-based collapsing (based on wp_get_archives).
 *
 * @param string|array $args {
 *     Default archive links arguments. Optional.
 *
 *     @type bool       $show_post_count Whether to display the post count alongside the link. Default false.
 *     @type bool       $use_triangles   Whether to use triangles to indicate expansion instead of +/-. Default false.
 *     @type bool       $never_expand    Whether to never expand for date (normally expands for current post). Default false.
 *     @type string     $order           Whether to use ascending or descending order. Accepts 'ASC', or 'DESC'.
 *                                       Default 'DESC'.
 * }
 * @return string
 */
function collapsed_archives_get_collapsed_archives( $args = '' ) {
    global $wpdb, $wp_locale, $wp_query;

    $defaults = array(
        'show_post_count' => false,
        'use_triangles' => false,
        'never_expand' => false,
        'order' => 'DESC',
    );

    $r = wp_parse_args( $args, $defaults );

    $order = strtoupper( $r['order'] );
    if ( $order !== 'ASC' ) {
        $order = 'DESC';
    }

    $where = apply_filters( 'getarchives_where', "WHERE post_type = 'post' AND post_status = 'publish'", $r );
    $join = apply_filters( 'getarchives_join', '', $r );

    $output = '';

    $last_changed = wp_cache_get( 'last_changed', 'posts' );
    if ( ! $last_changed ) {
        $last_changed = microtime();
        wp_cache_set( 'last_changed', $last_changed, 'posts' );
    }

    $query = "SELECT YEAR(post_date) AS `year`, MONTH(post_date) AS `month`, count(ID) as posts FROM $wpdb->posts $join $where GROUP BY YEAR(post_date), MONTH(post_date) ORDER BY post_date $order";
    $key = md5( $query );
    $key = "wp_get_archives:$key:$last_changed";
    if ( ! $results = wp_cache_get( $key, 'posts' ) ) {
        $results = $wpdb->get_results( $query );
        wp_cache_set( $key, $results, 'posts' );
    }
    if ( $results ) {
        $output .= '<div class="collapsed-archives';
        if ( $r['use_triangles'] ) {
            $output .= ' collapsed-archives-triangles';
        }
        $output .= '"><ul>';
        
        $prev_year = false;
        foreach ( (array) $results as $result ) {
            if ( $prev_year != $result->year ) {
                if ( $prev_year !== false ) {
                    $output .= '</ul></li>';
                }
                
                $year_id = 'archive-year-' . $result->year;
                
                $output .= '<li>';
                $output .= '<input type="checkbox" id="' . $year_id . '"';
                if ( isset( $wp_query->post->ID ) ) {
                    if ( !$r['never_expand'] && ( ( $result->year == get_the_date('Y', $wp_query->post->ID) && !is_page() ) || ( is_page() && $result->year == date('Y') ) ) ) {
                        $output .= ' checked';
                    }
                }
                $output .= '>';
                $output .= '<label for="' . $year_id . '"></label>';
                
                $url = get_year_link( $result->year );
                $after = '';
                if ( $r['show_post_count'] ) {
                    $year_query = new WP_Query( 'year=' . $result->year );
                    $after = '&nbsp;(' . $year_query->found_posts . ')';
                }
                $output .= get_archives_link( $url, $result->year, '', '', $after );
                
                $output .= '<ul>';
                
                $prev_year = $result->year;
            }
            
            $url = get_month_link( $result->year, $result->month );
            /* translators: 1: month name */
            $text = sprintf( __( '%1$s' ), $wp_locale->get_month( $result->month ) );
            $after = '';
            if ( $r['show_post_count'] ) {
                $after = '&nbsp;(' . $result->posts . ')';
            }
            $output .= get_archives_link( $url, $text, 'html', '', $after );
        }
        $output .= '</ul></li></ul></div>';
    }
    
    return $output;
} // function get_collapsed_archives

/**
 * Adds Collapsed_Archives widget.
 */
class Collapsed_Archives_Widget extends WP_Widget {

    function __construct() {
        parent::__construct(
            'collapsed_archives_widget', // Base ID
            __( 'Collapsed Archives', 'text_domain' ), // Name
            array( 'description' => __( 'Displays archive links grouped by year using CSS-based collapsing', 'text_domain' ), ) // Args
        );
    }

    public function widget( $args, $instance ) {
    
        $title = apply_filters( 'widget_title', empty( $instance['title'] ) ? __( 'Archives' ) : $instance['title'], $instance, $this->id_base );
        $count = ! empty( $instance['count'] ) ? '1' : '0';
        $use_triangles = ! empty( $instance['use_triangles'] ) ? '1' : '0';
        $never_expand = ! empty( $instance['never_expand'] ) ? '1' : '0';
        $order = ! empty( $instance['asc_order'] ) ? 'ASC' : 'DESC';
        
        echo $args['before_widget'];
        if ( $title ) {
            echo $args['before_title'] . $title . $args['after_title'];
        }
        echo collapsed_archives_get_collapsed_archives( array( 'show_post_count' => $count, 'use_triangles' => $use_triangles, 'never_expand' => $never_expand, 'order' => $order ) );
        echo $args['after_widget'];
    }

    public function form( $instance ) {
        $instance = wp_parse_args( (array) $instance, array( 'title' => '', 'count' => 0, 'use_triangles' => 0, 'never_expand' => 0, 'asc_order' => 0 ) );
        $title = strip_tags($instance['title']);
        $count = $instance['count'] ? 'checked="checked"' : '';
        $use_triangles = $instance['use_triangles'] ? 'checked="checked"' : '';
        $never_expand = $instance['never_expand'] ? 'checked="checked"' : '';
        $asc_order = $instance['asc_order'] ? 'checked="checked"' : '';
        ?>
        <p>
        <label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:'); ?></label> <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo esc_attr($title); ?>" />
        </p>
        <p>
        <input class="checkbox" type="checkbox" <?php echo $count; ?> id="<?php echo $this->get_field_id('count'); ?>" name="<?php echo $this->get_field_name('count'); ?>" /> <label for="<?php echo $this->get_field_id('count'); ?>"><?php _e('Show post counts'); ?></label>
        <br/>
        <input class="checkbox" type="checkbox" <?php echo $use_triangles; ?> id="<?php echo $this->get_field_id('use_triangles'); ?>" name="<?php echo $this->get_field_name('use_triangles'); ?>" /> <label for="<?php echo $this->get_field_id('use_triangles'); ?>"><?php _e('Use triangles to indicate expansion instead of +/-.'); ?></label>
        <br/>
        <input class="checkbox" type="checkbox" <?php echo $never_expand; ?> id="<?php echo $this->get_field_id('never_expand'); ?>" name="<?php echo $this->get_field_name('never_expand'); ?>" /> <label for="<?php echo $this->get_field_id('never_expand'); ?>"><?php _e('Don\'t expand list for current post / year.'); ?></label>
        <br/>
        <input class="checkbox" type="checkbox" <?php echo $asc_order; ?> id="<?php echo $this->get_field_id('asc_order'); ?>" name="<?php echo $this->get_field_name('asc_order'); ?>" /> <label for="<?php echo $this->get_field_id('asc_order'); ?>"><?php _e('Display archives in chronological order instead of reverse chronological order.'); ?></label>
        </p>
        <?php 
    }

    public function update( $new_instance, $old_instance ) {
        $instance = $old_instance;
        $new_instance = wp_parse_args( (array) $new_instance, array( 'title' => '', 'count' => 0, 'use_triangles' => 0, 'never_expand' => 0, 'asc_order' => 0 ) );
        $instance['title'] = strip_tags($new_instance['title']);
        $instance['count'] = $new_instance['count'] ? 1 : 0;
        $instance['use_triangles'] = $new_instance['use_triangles'] ? 1 : 0;
        $instance['never_expand'] = $new_instance['never_expand'] ? 1 : 0;
        $instance['asc_order'] = $new_instance['asc_order'] ? 1 : 0;
        
        return $instance;
    }

} // class Collapsed_Archives_Widget

/**
 * Register widget
 */
function collapsed_archives_register_widgets() {
    register_widget( 'Collapsed_Archives_Widget' );
}
add_action( 'widgets_init', 'collapsed_archives_register_widgets' );

/**
 * Enqueue widget CSS
 */
function collapsed_archives_stylesheet() {
    wp_register_style( 'collapsed-archives-style', plugins_url( 'style.css', __FILE__ ) );
    wp_enqueue_style( 'collapsed-archives-style' );
}
add_action( 'wp_enqueue_scripts', 'collapsed_archives_stylesheet' );

?>
