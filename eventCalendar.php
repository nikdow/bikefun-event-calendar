<?php
/**
 * Plugin Name: Event Calendar
 * Plugin URI: http://www.cbdweb.net
 * Description: Modified from WP standard calendar widget to show events.
 * Version: 1.0
 * Author: Nik Dow, CBDWeb
 * Author URI: http://www.cbdweb.net
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */
/**
 * Display calendar with days that have posts as links.
 *
 * The calendar is cached, which will be retrieved, if it exists. If there are
 * no posts for the month, then it will not be displayed.
 *
 * @since 1.0.0
 * @uses calendar_week_mod()
 *
 * @param bool $initial Optional, default is true. Use initial calendar names.
 * @param bool $echo Optional, default is true. Set to false for return.
 * @return string|null String when retrieving, null when displaying.
 */

defined('ABSPATH') or die("No script kiddies please!");

function get_event_calendar($initial = true, $echo = true) {
	global $wpdb, $m, $monthnum, $year, $wp_locale, $posts;

	$cache = array();
	$key = md5( $m . $monthnum . $year );
	if ( $cache = wp_cache_get( 'get_calendar', 'calendar' ) ) {
		if ( is_array($cache) && isset( $cache[ $key ] ) ) {
			if ( $echo ) {
				/** This filter is documented in wp-includes/general-template.php */
				echo apply_filters( 'get_calendar', $cache[$key] );
				return;
			} else {
				/** This filter is documented in wp-includes/general-template.php */
				return apply_filters( 'get_calendar', $cache[$key] );
			}
		}
	}

	if ( !is_array($cache) )
		$cache = array();

	// Quick check. If we have no posts at all, abort!
	if ( !$posts ) {
		$gotsome = $wpdb->get_var("SELECT 1 as test FROM $wpdb->posts WHERE post_type = 'bf_events' AND post_status = 'publish' LIMIT 1");
		if ( !$gotsome ) {
			$cache[ $key ] = '';
			wp_cache_set( 'get_calendar', $cache, 'calendar' );
			return;
		}
	}

	if ( isset($_GET['w']) )
		$w = ''.intval($_GET['w']);
        
        if ( isset($_GET['calmonth']) )
                $monthnum = ''.intval($_GET['calmonth']);
        if ( isset($_GET['calyear']) )
                $year = ''.intval($_GET['calyear']);

	// week_begins = 0 stands for Sunday
	$week_begins = intval(get_option('start_of_week'));

	// Let's figure out when we are
	if ( !empty($monthnum) && !empty($year) ) {
		$thismonth = ''.zeroise(intval($monthnum), 2);
		$thisyear = ''.intval($year);
	} elseif ( !empty($w) ) {
		// We need to get the month from MySQL
		$thisyear = ''.intval(substr($m, 0, 4));
		$d = (($w - 1) * 7) + 6; //it seems MySQL's weeks disagree with PHP's
		$thismonth = $wpdb->get_var("SELECT DATE_FORMAT((DATE_ADD('{$thisyear}0101', INTERVAL $d DAY) ), '%m')");
	} elseif ( !empty($m) ) {
		$thisyear = ''.intval(substr($m, 0, 4));
		if ( strlen($m) < 6 )
				$thismonth = '01';
		else
				$thismonth = ''.zeroise(intval(substr($m, 4, 2)), 2);
	} else {
		$thisyear = gmdate('Y', current_time('timestamp'));
		$thismonth = gmdate('m', current_time('timestamp'));
	}

	$unixmonth = mktime(0, 0 , 0, $thismonth, 1, $thisyear);
	$last_day = date('t', $unixmonth);

	// Get the next and previous month and year with at least one post
	$previous = $wpdb->get_row("SELECT (FROM_UNIXTIME(`wp_postmeta`.`meta_value`,'%m')) AS month, 
            (FROM_UNIXTIME(`wp_postmeta`.`meta_value`-(" . get_option( 'gmt_offset' ) * 3600 . "),'%y')) AS year
		FROM $wpdb->postmeta wp_postmeta
		LEFT JOIN  $wpdb->posts wp_posts ON  `wp_postmeta`.`post_id` =  `wp_posts`.`ID` 
                WHERE  `wp_postmeta`.`meta_key` =  'bf_events_startdate'
		AND `wp_postmeta`.`meta_value` < UNIX_TIMESTAMP(  '{$thisyear}-{$thismonth}-01 00:00:00' ) + (" . get_option( 'gmt_offset' ) * 3600 . ")
		AND wp_posts.post_status = 'publish'
			ORDER BY wp_postmeta.meta_value DESC
			LIMIT 1");
	$next = $wpdb->get_row("SELECT (FROM_UNIXTIME(`wp_postmeta`.`meta_value`,'%m')) AS month, 
            (FROM_UNIXTIME(`wp_postmeta`.`meta_value`-(" . get_option( 'gmt_offset' ) * 3600 . "),'%y')) AS year
		FROM $wpdb->postmeta wp_postmeta
		LEFT JOIN  $wpdb->posts wp_posts ON  `wp_postmeta`.`post_id` =  `wp_posts`.`ID` 
                WHERE  `wp_postmeta`.`meta_key` =  'bf_events_startdate'
                AND `wp_postmeta`.`meta_value` > UNIX_TIMESTAMP(  '{$thisyear}-{$thismonth}-{$last_day} 23:59:59' ) + (" . get_option( 'gmt_offset' ) * 3600 . ")
		AND wp_posts.post_status = 'publish'
			ORDER BY wp_postmeta.meta_value ASC
			LIMIT 1");
                
/*                
        $wpdb->get_results("SELECT (FROM_UNIXTIME(`wp_postmeta`.`meta_value`-(" . get_option( 'gmt_offset' ) * 3600 . "),'%d')) as dom , 
                `wp_postmeta`.`post_id` , `wp_posts`.`ID` , `wp_posts`.`post_title` 
            FROM $wpdb->postmeta wp_postmeta
            LEFT JOIN  $wpdb->posts wp_posts ON  `wp_postmeta`.`post_id` =  `wp_posts`.`ID` 
            WHERE  `wp_postmeta`.`meta_key` =  'bf_events_startdate'
            AND  `wp_posts`.`post_status` =  'publish'
            AND  `wp_postmeta`.`meta_value` >= UNIX_TIMESTAMP(  '{$thisyear}-{$thismonth}-01 00:00:00' ) + (" . get_option( 'gmt_offset' ) * 3600 . ")
            AND  `wp_postmeta`.`meta_value` <= UNIX_TIMESTAMP(  '{$thisyear}-{$thismonth}-{$last_day} 23:59:59' ) + (" . get_option( 'gmt_offset' ) * 3600 . ")", OBJECT);
*/
	/* translators: Calendar caption: 1: month name, 2: 4-digit year */
	$calendar_caption = _x('%1$s %2$s', 'calendar caption');
        
	$calendar_output = '<table id="wp-calendar">
	<caption>' . sprintf($calendar_caption, $wp_locale->get_month($thismonth), date('Y', $unixmonth)) . '</caption>
	<thead>
	<tr>';

	$myweek = array();

	for ( $wdcount=0; $wdcount<=6; $wdcount++ ) {
		$myweek[] = $wp_locale->get_weekday(($wdcount+$week_begins)%7);
	}

	foreach ( $myweek as $wd ) {
		$day_name = (true == $initial) ? $wp_locale->get_weekday_initial($wd) : $wp_locale->get_weekday_abbrev($wd);
		$wd = esc_attr($wd);
		$calendar_output .= "\n\t\t<th scope=\"col\" title=\"$wd\">$day_name</th>";
	}

	$calendar_output .= '
	</tr>
	</thead>

	<tfoot>
	<tr>';

	if ( $previous ) {
		$calendar_output .= "\n\t\t".'<td colspan="3" id="prev"><a href="' . add_query_arg( array('calyear'=>$previous->year, 'calmonth'=>$previous->month ) ) . '" title="' . esc_attr( sprintf(__('View events for %1$s %2$s'), $wp_locale->get_month($previous->month), date('Y', mktime(0, 0 , 0, $previous->month, 1, $previous->year)))) . '">&laquo; ' . $wp_locale->get_month_abbrev($wp_locale->get_month($previous->month)) . '</a></td>';
	} else {
		$calendar_output .= "\n\t\t".'<td colspan="3" id="prev" class="pad">&nbsp;</td>';
	}

	$calendar_output .= "\n\t\t".'<td class="pad">&nbsp;</td>';

	if ( $next ) {
		$calendar_output .= "\n\t\t".'<td colspan="3" id="next"><a href="' . add_query_arg( array('calyear'=>$next->year, 'calmonth'=>$next->month ) ) . '" title="' . esc_attr( sprintf(__('View events for %1$s %2$s'), $wp_locale->get_month($next->month), date('Y', mktime(0, 0 , 0, $next->month, 1, $next->year))) ) . '">' . $wp_locale->get_month_abbrev($wp_locale->get_month($next->month)) . ' &raquo;</a></td>';
	} else {
		$calendar_output .= "\n\t\t".'<td colspan="3" id="next" class="pad">&nbsp;</td>';
	}

	$calendar_output .= '
	</tr>
	</tfoot>

	<tbody>
	<tr>';
	$ak_titles_for_day = array();
        
        $dayswithposts = $wpdb->get_results("SELECT (FROM_UNIXTIME(`wp_postmeta`.`meta_value`,'%e')) as dom , 
                    `wp_postmeta`.`post_id` , `wp_posts`.`ID` , `wp_posts`.`post_title` 
		FROM $wpdb->postmeta wp_postmeta
		LEFT JOIN  $wpdb->posts wp_posts ON  `wp_postmeta`.`post_id` =  `wp_posts`.`ID` 
		WHERE  `wp_postmeta`.`meta_key` =  'bf_events_startdate'
		AND  `wp_posts`.`post_status` =  'publish'
		AND  `wp_postmeta`.`meta_value` >= UNIX_TIMESTAMP(  '{$thisyear}-{$thismonth}-01 00:00:00' ) + (" . get_option( 'gmt_offset' ) * 3600 . ")
		AND  `wp_postmeta`.`meta_value` <= UNIX_TIMESTAMP(  '{$thisyear}-{$thismonth}-{$last_day} 23:59:59' ) + (" . get_option( 'gmt_offset' ) * 3600 . ")", OBJECT);
        if ( $dayswithposts ) {
            foreach ( $dayswithposts as $daywith ) {

                    $daywithpost[] = $daywith->dom;

                    $post_title = esc_attr( apply_filters( 'the_title', $daywith->post_title, $daywith->post_id ) );

                    if(empty($ak_titles_for_day[$daywith->dom])){
                            $ak_titles_for_day[$daywith->dom]= array ();
                            $ak_titles_for_day[$daywith->dom][] = array('title'=>$post_title,'url'=>get_permalink( $daywith->ID ) );
                    } else {
                            $ak_titles_for_day[$daywith->dom][] = array('title'=>$post_title,'url'=>get_permalink( $daywith->ID ) );
                    }
            }
        } else {
            $daywithpost = array();
        }
//        echo print_r($ak_titles_for_day);

	// See how much we should pad in the beginning
	$pad = calendar_week_mod(date('w', $unixmonth)-$week_begins);
	if ( 0 != $pad )
		$calendar_output .= "\n\t\t".'<td colspan="'. esc_attr($pad) .'" class="pad">&nbsp;</td>';

	$daysinmonth = intval(date('t', $unixmonth));
	for ( $day = 1; $day <= $daysinmonth; ++$day ) {
		if ( isset($newrow) && $newrow )
			$calendar_output .= "\n\t</tr>\n\t<tr>\n\t\t";
		$newrow = false;

		if ( $day == gmdate('j', current_time('timestamp')) && $thismonth == gmdate('m', current_time('timestamp')) && $thisyear == gmdate('Y', current_time('timestamp')) )
			$calendar_output .= '<td id="today">';
		else
			$calendar_output .= '<td>';

		if ( in_array($day, $daywithpost) ){ // any posts today?
                        $title_div = "<div>";
                            if(isset($ak_titles_for_day[$day])) { // this test should not be necessary but prevents some errors
					foreach($ak_titles_for_day[$day] as $ak_title){
						$title_div .= "<a href='".$ak_title['url']."' title='".$ak_title['title']."'>".$ak_title['title']."</a>";
						}
					$title_div .= "</div>";
                                    $calendar_output .= "<span>$day</span>".$title_div;
                            }
                }
		else {
			$calendar_output .= $day;
                }
		$calendar_output .= '</td>';

		if ( 6 == calendar_week_mod(date('w', mktime(0, 0 , 0, $thismonth, $day, $thisyear))-$week_begins) )
			$newrow = true;
	}

	$pad = 7 - calendar_week_mod(date('w', mktime(0, 0 , 0, $thismonth, $day, $thisyear))-$week_begins);
	if ( $pad != 0 && $pad != 7 )
		$calendar_output .= "\n\t\t".'<td class="pad" colspan="'. esc_attr($pad) .'">&nbsp;</td>';

	$calendar_output .= "\n\t</tr>\n\t</tbody>\n\t</table>";

	$cache[ $key ] = $calendar_output;
	wp_cache_set( 'get_calendar', $cache, 'calendar' );

	if ( $echo ) {
		/**
		 * Filter the HTML calendar output.
		 *
		 * @since 3.0.0
		 *
		 * @param string $calendar_output HTML output of the calendar.
		 */
		echo apply_filters( 'get_calendar', $calendar_output );
	} else {
		/** This filter is documented in wp-includes/general-template.php */
		return apply_filters( 'get_calendar', $calendar_output );
	}

}

/**
 * Add function to widgets_init that'll load our widget.
 */
add_action( 'widgets_init', 'load_event_calendar_widget' );

/**
 * Register widget.
 */
function load_event_calendar_widget() {
	register_widget( 'Event_Calendar' );
}

class Event_Calendar extends WP_Widget {

        function __construct() {
		$widget_ops = array('classname' => 'widget_event_calendar', 'description' => __( 'calendar of events.') );
		parent::__construct('calendar', __('Calendar'), $widget_ops);
	}

	function widget( $args, $instance ) {
		extract($args);

                global $post;
                if ( $post && ! isset( $_GET['calyear'] ) ) { // show calendar for month of this event
                    $custom = get_post_custom();
                    $startd = $custom["bf_events_startdate"][0] + get_option( 'gmt_offset' ) * 3600;
                    if( $custom["bf_events_startdate"][0] ) {
                        $startyear = date("Y", $startd );
                        $startmonth = date("m", $startd );
                        $_GET['calmonth'] = $startmonth;
                        $_GET['calyear'] = $startyear;
                    }
                }

                
		/** This filter is documented in wp-includes/default-widgets.php */
		$title = apply_filters( 'widget_title', empty( $instance['title'] ) ? '' : $instance['title'], $instance, $this->id_base );

		echo $before_widget;
		if ( $title )
			echo $before_title . $title . $after_title;
		echo '<div id="calendar_wrap">';
		get_event_calendar();
		echo '</div>';
		echo $after_widget;
	}

	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;
		$instance['title'] = strip_tags($new_instance['title']);

		return $instance;
	}

	function form( $instance ) {
		$instance = wp_parse_args( (array) $instance, array( 'title' => '' ) );
		$title = strip_tags($instance['title']);
?>
		<p><label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:'); ?></label>
		<input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo esc_attr($title); ?>" /></p>
<?php
	}
}

function calendar_styles() {
    wp_enqueue_style('calendar-style', plugins_url( 'style.css', __FILE__ ) );
}
add_action( 'wp_enqueue_scripts', 'calendar_styles' );
