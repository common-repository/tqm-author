<?php
/*
Plugin Name: TorqueMag Author List
Plugin URI: http://reaktivstudios.com/custom-plugins/
Description: Display a feed of an authors content from TorqueMag
Author: Andrew Norcross
Version: 1.0.0
Requires at least: 3.0
Author URI: http://andrewnorcross.com
*/
/*  Copyright 2014 Andrew Norcross

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; version 2 of the License (GPL v2) only.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/


if( ! defined( 'TQM_AUTHOR_BASE ' ) ) {
	define( 'TQM_AUTHOR__BASE', plugin_basename(__FILE__) );
}

if( ! defined( 'TQM_AUTHOR_VER' ) ) {
	define( 'TQM_AUTHOR_VER', '1.0.0' );
}


class TQM_Author_Core
{

	/**
	 * Static property to hold our singleton instance
	 * @var instance
	 */
	static $instance = false;

//

	/**
	 * If an instance exists, this returns it.  If not, it creates one and
	 * retuns it.
	 *
	 * @return
	 */

	public static function getInstance() {
		if ( !self::$instance )
			self::$instance = new self;
		return self::$instance;
	}

	/**
	 * [__construct description]
	 */
	private function __construct() {
		add_action			(	'plugins_loaded',				array(	$this,	'textdomain'				)			);
		add_action			(	'widgets_init',					array(	$this,	'load_widget'				)			);

	}

	/**
	 * [textdomain description]
	 * @return [type] [description]
	 */
	public function textdomain() {

		load_plugin_textdomain( 'tqm-author', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

	/**
	 * [load_widget description]
	 * @return [type] [description]
	 */
	public function load_widget() {
		register_widget( 'TQM_Author_Widget' );
	}

	/**
	 * [feed_cache description]
	 * @return [type]          [description]
	 */
	static function feed_cache() {
		return 100;
	}

	/**
	 * [author_items description]
	 * @param  string  $author [description]
	 * @param  integer $count  [description]
	 * @param  boolean $error  [description]
	 * @return [type]          [description]
	 */
	static function author_item_data( $author = '', $count = 5, $error = true ) {

		// bail if no author is set
		if ( ! $author || empty( $author ) ) {
			return false;
		}

		// set the feed caching
		add_filter( 'wp_feed_cache_transient_lifetime', array( 'TQM_Author_Core', 'feed_cache' ) );

		// set the feed to retrieve
		$feed	= fetch_feed( 'http://torquemag.io/author/' . $author . '/feed/' );

		// remove our filter
		remove_filter( 'wp_feed_cache_transient_lifetime', array( 'TQM_Author_Core', 'feed_cache' ) );

		// return the error message if present and requested
		if ( is_wp_error( $feed ) && $error ) {
			return printf( __( '<strong>RSS Error</strong>: %s' ), $rss->get_error_message() );
		}

		// set an empty array to build a usable data feed
		$data	= array();

		// check the item count returned
		$fetch	= $feed->get_item_quantity( absint( $count ) );

		// return a basic message if no items available
		if ( $fetch == 0 ) {
			return array(
				'empty' => __( 'There are currently no items available', 'tqm-author' )
			);
		}

		// build a SimplePie object of the items
		$items	= $feed->get_items( 0, $fetch );

		// set the array loop counter
		$i = 0;

		// loop through the SimplePie object to pull relevant items
		foreach ( $items as $item ) {

			$data[$i]['author']	= $item->get_author()->name;
			$data[$i]['link']	= esc_url ( $item->get_permalink() );
			$data[$i]['date']	= $item->get_date( get_option( 'date_format' ) );
			$data[$i]['title']	= esc_html( $item->get_title() );

			// end the counter
			$i++;
		}

		// return nothing if the array is empty
		if ( empty( $data ) ) {
			return array(
				'empty' => __( 'There are currently no items available', 'tqm-author' )
			);
		}

		// return the data array
		return $data;

	}

/// end class
}

// Instantiate our class
$TQM_Author_Core = TQM_Author_Core::getInstance();



/**
 * widget for displaying the content itseld
 *
 * @since 1.0
 */
class TQM_Author_Widget extends WP_Widget {

	function __construct() {
		$widget_ops = array( 'classname' => 'tqm-author-widget', 'description' => __( 'Display a list of your content from TorqueMag' , 'tqm-author' ) );
		parent::__construct( 'tqm-author-widget', __( 'TorqueMag Author List', 'tqm-author' ), $widget_ops );
		$this->alt_option_name = 'tqm-author-widget';
	}

	function widget( $args, $instance ) {
		extract( $args, EXTR_SKIP );

		// begin display
		echo $before_widget;

		// check for author name and bail if not there
		if ( ! isset( $instance['author'] ) || isset( $instance['author'] ) && empty( $instance['author'] ) ) {
			return;
		}

		// fetch the data
		$data	= TQM_Author_Core::author_item_data( $instance['author'], absint( $instance['count'] ) );

		// if it's empty or isn't an array, bail
		if ( ! $data || empty( $data ) || ! is_array( $data ) ) {
			return;
		}

		// display widget title
		$title = empty( $instance['title'] ) ? '' : apply_filters( 'widget_title', $instance['title'] );
		if ( !empty( $title ) ) { echo $before_title . $title . $after_title; };

		// wrap the list in a ul item
		echo '<ul>';

		// loop through data array
		foreach( $data as $item ) {

			echo '<li>';
				echo '<a class="rsswidget" target="_blank" href="' . esc_url ( $item['link'] ) . '" title="Posted ' . esc_html( $item['date'] ) . '">' . esc_attr( $item['title'] ) . '</a>';
			echo '</li>';

		}

		// close list markup
		echo '</ul>';

		// close widget
		echo $after_widget;

	}

	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;

		$instance['title']	= strip_tags( $new_instance['title'] );
		$instance['author']	= strip_tags( $new_instance['author'] );
		$instance['count']	= ! empty( $new_instance['count'] ) ? absint( $new_instance['count'] ) : 5;

		return $instance;
	}

	function form( $instance ) {
		$title		= isset( $instance['title'] )	? esc_attr( $instance['title'] )	: '';
		$author		= isset( $instance['author'] )	? esc_attr( $instance['author'] )	: '';
		$count		= isset( $instance['count'] )	? absint( $instance['count'] )		: '';

	?>
		<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Widget Title:' ); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo $title; ?>" />
		</p>

		<p>
			<label for="<?php echo $this->get_field_id( 'author' ); ?>"><?php _e( 'Username:' ); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id( 'author' ); ?>" name="<?php echo $this->get_field_name( 'author' ); ?>" type="text" value="<?php echo $author; ?>" />
			<span class="description"><?php _e( 'Enter the username used on TorqueMag', 'tqm-author' ); ?></span>
		</p>

		<p>
			<label for="<?php echo $this->get_field_id( 'count' ); ?>"><?php _e( 'Post Count:' ); ?></label>
			<input class="small-text" id="<?php echo $this->get_field_id( 'count' ); ?>" name="<?php echo $this->get_field_name( 'count' ); ?>" type="text" value="<?php echo $count; ?>" />
		</p>

		<?php

	}


} // end widget class