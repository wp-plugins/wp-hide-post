<?php
/*
Plugin Name: WP Hide Post
Plugin URI: http://anappleaday.konceptus.net/posts/wp-hide-post/
Description: Enables a user to control the visibility of items on the blog by making posts and pages selectively hidden in different views throughout the blog, such as on the front page, category pages, search results, etc... The hidden item remains otherwise accessible directly using permalinks, and also visible to search engines as part of the sitemap (at least). This plugin enables new SEO possibilities for authors since it enables them to create new posts and pages without being forced to display them on their front and in feeds.
Version: 1.1.1
Author: Robert Mahfoud
Author URI: http://anappleaday.konceptus.net
Text Domain: wp_hide_post
*/

/*  Copyright 2009  Robert Mahfoud  (email : robert.mahfoud@gmail.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/**
 * 
 * @return unknown_type
 */
function wphp_init() {
    global $table_prefix;
    if( !defined('WPHP_TABLE_NAME') )
        define('WPHP_TABLE_NAME', "${table_prefix}postmeta");
    if( !defined('WP_POSTS_TABLE_NAME') )
        define('WP_POSTS_TABLE_NAME', "${table_prefix}posts");
    if( !defined('WPHP_DEBUG') ) {
        define('WPHP_DEBUG', defined('WP_DEBUG') && WP_DEBUG ? 1 : 0);
    }
}
wphp_init();

/**
 * 
 * @param $msg
 * @return unknown_type
 */
function wphp_log($msg) {
	if( defined('WPHP_DEBUG') && WPHP_DEBUG )
	   error_log("WPHP-> $msg");
}

/**
 * 
 * @return unknown_type
 */
function wphp_is_front_page() {
	return is_front_page();
}

/**
 * 
 * @return unknown_type
 */
function wphp_is_feed() {
	return is_feed();
}

/**
 * 
 * @return unknown_type
 */
function wphp_is_category() {
	return !wphp_is_front_page() && !wphp_is_feed() && is_category();
}

/**
 * 
 * @return unknown_type
 */
function wphp_is_tag() {
	return !wphp_is_front_page() && !wphp_is_feed() && is_tag();
}

/**
 * 
 * @return unknown_type
 */
function wphp_is_author() {
	return !wphp_is_front_page() && !wphp_is_feed() && is_author();
}

/**
 * 
 * @return unknown_type
 */
function wphp_is_archive() {
    return !wphp_is_front_page() && !wphp_is_feed() && is_date();
}

/**
 * 
 * @return unknown_type
 */
function wphp_is_search() {
    return is_search();
}

/**
 * 
 * @param $item_type
 * @return unknown_type
 */
function wphp_is_applicable($item_type) {
	return !is_admin() && (($item_type == 'post' && !is_single()) || $item_type == 'page') ;
}


/**
 * Creates Text Domain For Translations
 * @return unknown_type
 */
function wphp_textdomain() {
	$plugin_dir = basename(dirname(__FILE__));
	load_plugin_textdomain('wp-hide-post', ABSPATH."/$plugin_dir", $plugin_dir);
}
add_action('init', 'wphp_textdomain');

/**
 * Hook called when activating the plugin
 * @return unknown_type
 */
function wphp_activate() {
    wphp_init();
	wphp_log("called: wphp_activate");
	
	require_once(dirname(__FILE__).'/upgrade.php');
	wphp_migrate_db();
	wphp_remove_wp_low_profiler();
}
add_action('activate_wp-hide-post/wp-hide-post.php', 'wphp_activate' );
//register_activation_hook( __FILE__, 'wphp_activate' );

/**
 * 
 * @param $item_type
 * @param $posts
 * @return unknown_type
 */
function wphp_exclude_low_profile_items($item_type, $posts) {
    wphp_log("called: wphp_exclude_low_profile_items");
	if( $item_type != 'page' )
		return $posts;   // regular posts & search results are filtered in wphp_query_posts_join
	else {
        if( wphp_is_applicable('page') ) {
			global $wpdb;
			// now loop over the pages, and exclude the ones with low profile in this context
			$result = array();
            $page_flags = $wpdb->get_results("SELECT post_id, meta_value FROM ".WPHP_TABLE_NAME." WHERE meta_key = '_wplp_page_flags'", OBJECT_K);
			foreach($posts as $post) {
				$check = isset($page_flags[ $post->ID ]) ? $page_flags[ $post->ID ]->meta_value : null;
				if( ($check == 'front' && wphp_is_front_page()) || $check == 'all') {
					// exclude page
				} else
					$result[] = $post;
			}
	        return $result;
        } else
            return $posts;
    }
}
 
/**
 * Hook function to filter out hidden pages (get_pages)
 * @param $posts
 * @return unknown_type
 */
function wphp_exclude_low_profile_pages($posts) {
    wphp_log("called: wphp_exclude_low_profile_pages");
	return wphp_exclude_low_profile_items('page', $posts);
}
add_filter('get_pages', 'wphp_exclude_low_profile_pages');

/**
 * 
 * @param $where
 * @return unknown_type
 */
function wphp_query_posts_where($where) {
    wphp_log("called: wphp_query_posts_where");
	// filter posts on one of the three kinds of contexts: front, category, feed
	if( wphp_is_applicable('post') && wphp_is_applicable('page') ) {
		$where .= ' AND wphptbl.post_id IS NULL ';
	}
	//echo "\n<!-- WPHP: ".$where." -->\n";
	return $where;
}
add_filter('posts_where_paged', 'wphp_query_posts_where');

/**
 * 
 * @param $join
 * @return unknown_type
 */
function wphp_query_posts_join($join) {
    wphp_log("called: wphp_query_posts_join");
	if( wphp_is_applicable('post') && wphp_is_applicable('page')) {
		if( !$join )
			$join = '';
        $join .= ' LEFT JOIN '.WPHP_TABLE_NAME.' wphptbl ON '.WP_POSTS_TABLE_NAME.'.ID = wphptbl.post_id and wphptbl.meta_key like \'_wplp_%\'';
		// filter posts 
		$join .= ' AND (('.WP_POSTS_TABLE_NAME.'.post_type = \'post\' ';
		if( wphp_is_front_page() )
			$join .= ' AND wphptbl.meta_key = \'_wplp_post_front\' ';
		elseif( wphp_is_category())
			$join .= ' AND wphptbl.meta_key = \'_wplp_post_category\' ';
		elseif( wphp_is_tag() )
			$join .= ' AND wphptbl.meta_key = \'_wplp_post_tag\' ';
		elseif( wphp_is_author() )
			$join .= ' AND wphptbl.meta_key = \'_wplp_post_author\' ';
		elseif( wphp_is_archive() )
			$join .= ' AND wphptbl.meta_key = \'_wplp_post_archive\' ';
        elseif( wphp_is_feed())
            $join .= ' AND wphptbl.meta_key = \'_wplp_post_feed\' ';
		elseif( wphp_is_search())
			$join .= ' AND wphptbl.meta_key = \'_wplp_post_search\' ';
		else
            $join .= ' AND wphptbl.meta_key not like  \'_wplp_%\' ';
		$join .= ')';	
		// pages
        $join .= ' OR ('.WP_POSTS_TABLE_NAME.'.post_type = \'page\' AND wphptbl.meta_key <> \'_wplp_page_flags\'';
        if( wphp_is_search())
            $join .= ' AND wphptbl.meta_key = \'_wplp_page_search\' ';
        else
            $join .= ' AND wphptbl.meta_key not like \'_wplp_%\' ';
        $join .= '))';   
	}
    //echo "\n<!-- WPHP: ".$join." -->\n";
    return $join;
}
add_filter('posts_join_paged', 'wphp_query_posts_join');


if( is_admin() ) {
	require_once(dirname(__FILE__).'/admin.php');
}

?>