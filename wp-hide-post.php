<?php
/*
Plugin Name: WP Hide Post
Plugin URI: http://anappleaday.konceptus.net/posts/wp-hide-post/
Description: Enables a user to control the visibility of items on the blog by making posts and pages selectively hidden in different views throughout the blog, such as on the front page, category pages, search results, etc... The hidden item remains otherwise accessible directly using permalinks, and also visible to search engines as part of the sitemap (at least). This plugin enables new SEO possibilities for authors since it enables them to create new posts and pages without being forced to display them on their front and in feeds.
Version: 1.0.3
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
function wphp_init() {
	global $table_prefix;
    if( !defined('WPHP_TABLE_NAME') )
        define('WPHP_TABLE_NAME', "${table_prefix}postmeta");
	if( !defined('WPHP_DEBUG') ) {
        define('WPHP_DEBUG', defined('WP_DEBUG') && WP_DEBUG ? 1 : 1);
	}
}
wphp_init();

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
 * Migrate to the new database schema and clean up old schema...
 * Should run only once in the lifetime of the plugin...
 * @return unknown_type
 */
function wphp_migrate_db() {
    wphp_log("called: wphp_migrate_db");
	/* When I first released this plugin, I was young and crazy and didn't know about the postmeta table. 
     * With time I became wiser and wiser and decided to migrate the implementation to rely on postmeta.
     * I hope it was not a bad idea...
     */
	global $wpdb;
    global $table_prefix;
	$dbname = $wpdb->get_var("SELECT database()");
    if( !$dbname )
        return;
    $legacy_table_name = "${table_prefix}lowprofiler_posts";
    $legacy_table_exists = $wpdb->get_var("SELECT COUNT(*) AS count FROM information_schema.tables WHERE table_schema = '$dbname' AND table_name = '$legacy_table_name';");
    if( $legacy_table_exists ) {
        wphp_log("Migrating legacy table...");
    	// move everything to the postmeta table
        $existing = $wpdb->get_results("SELECT wplp_post_id, wplp_flag, wplp_value from $legacy_table_name", ARRAY_N);
		// scan them one by one and insert the corresponding fields in the postmeta table
        $count = 0;
        foreach($existing as $existing_array) {
        	$wplp_post_id = $existing_array[0];
            $wplp_flag = $existing_array[1];
            $wplp_value = $existing_array[2];
            if( $wplp_flag == 'home' )
                $wplp_flag = 'front';
            if( $wplp_value == 'home' )
                $wplp_value = 'front';
            if( $wplp_flag != 'page' ) {
            	$wpdb->query("INSERT INTO ".WPHP_TABLE_NAME."(post_id, meta_key, meta_value) VALUES($wplp_post_id, '_wplp_post_$wplp_flag', '1')");
            } else {
                $wpdb->query("INSERT INTO ".WPHP_TABLE_NAME."(post_id, meta_key, meta_value) VALUES($wplp_post_id, '_wplp_page_flags', $wplp_value)");
            }
            ++$count;
        }
        wphp_log("$count entries migrated from legacy table.");
        // delete the old table
        $wpdb->query("TRUNCATE TABLE $legacy_table_name");
        $wpdb->query("DROP TABLE $legacy_table_name");
        wphp_log("Legacy table deleted.");
    }
}


/**
 * 
 * @return unknown_type
 */
function wphp_remove_wp_low_profiler() {
    wphp_log("called: wphp_remove_wp_low_profiler");
    $plugin_list = get_plugins('/wp-low-profiler');
    if( isset($plugin_list['wp-low-profiler.php']) ) {
        wphp_log("The 'WP low Profiler' plugin is present. Cleaning it up...");
        $plugins = array('wp-low-profiler/wp-low-profiler.php');
        if( is_plugin_active('wp-low-profiler/wp-low-profiler.php') ) {
            wphp_log("The 'WP low Profiler' plugin is active. Deactivating...");
            deactivate_plugins($plugins, true); // silent deactivate
        }
        wphp_log("Deleting plugin 'WP low Profiler'...");
        delete_plugins($plugins, '');
	} else
	   wphp_log("The 'WP low Profiler' plugin does not exist.");
	
}


/**
 * Hook called when activating the plugin
 * @return unknown_type
 */
function wphp_activate() {
    wphp_init();
	wphp_log("called: wphp_activate");
	wphp_migrate_db();
	wphp_remove_wp_low_profiler();
}
add_action('activate_wp-hide-post/wp-hide-post.php', 'wphp_activate' );
//register_activation_hook( __FILE__, 'wphp_activate' );



/**
 * Hook to watch for the activation of 'WP low Profiler', and forbid it...
 * @return unknown_type
 */
function wphp_activate_lowprofiler() {
    wphp_log("called: wphp_activate_lowprofiler");
    wphp_migrate_db();  // in case any tables were created, clean them up
    wphp_remove_wp_low_profiler();  // remove the files of the plugin

    // get an authoritative admin URL
    if ( defined( 'WP_SITEURL' ) && '' != WP_SITEURL )
        $admin_dir = WP_SITEURL . '/wp-admin/';
    elseif ( function_exists( 'get_bloginfo' ) && '' != get_bloginfo( 'wpurl' ) )
        $admin_dir = get_bloginfo( 'wpurl' ) . '/wp-admin/';
    elseif ( strpos( $_SERVER['PHP_SELF'], 'wp-admin' ) !== false )
        $admin_dir = '';
    else
        $admin_dir = 'wp-admin/';
    
    $msgbox = __("'WP low Profiler' has been deprecated and replaced by 'WP Hide Post' which you already have active! Activation failed and plugin files cleaned up.", 'wp-hide-post');
    $err1_sorry = __("Cannot install 'WP low Profiler' because of a conflict. Sorry for this inconvenience.", 'wp-hide-post');
    $err2_cleanup = __("The downloaded files were cleaned-up and no further action is required.", 'wp-hide-post');
    $err3_return = __("Return to plugins page...", 'wp-hide-post');

    $html = <<<HTML
${err1_sorry}<br />${err2_cleanup}<br /><a href="${admin_dir}plugins.php">${err3_return}</a>
<script language="javascript">window.alert("${msgbox}");</script>
HTML;
    // show the error page with the message...   
    wp_die($html, 'WP low Profiler Activation Not Allowed', array( 'response' => '200') );
}
add_action('activate_wp-low-profiler/wp-low-profiler.php', 'wphp_activate_lowprofiler' );


/**
 * 
 * @param $id
 * @param $lp_flag
 * @param $lp_value
 * @return unknown_type
 */
function wphp_update_low_profile($id, $lp_flag, $lp_value) {
    wphp_log("called: wphp_update_low_profile");
	global $wpdb;
	$item_type = get_post_type($id);
	if( ($item_type == 'post' && !$lp_value) || ($item_type == 'page' && ( ($lp_flag == '_wplp_page_flags' && $lp_value == 'none') || ($lp_flag == '_wplp_page_search' && !$lp_value) ) ) ) {
		wphp_unset_low_profile($item_type, $id, $lp_flag);
	} else {
		wphp_set_low_profile($item_type, $id, $lp_flag, $lp_value);
	}
}

/**
 * 
 * @param $item_type
 * @param $id
 * @param $lp_flag
 * @return unknown_type
 */
function wphp_unset_low_profile($item_type, $id, $lp_flag) {
    wphp_log("called: wphp_unset_low_profile");
	global $wpdb;
	// Delete the flag from the database table
	$wpdb->query("DELETE FROM ".WPHP_TABLE_NAME." WHERE post_id = $id AND meta_key = '$lp_flag'");
}

/**
 * 
 * @param $item_type
 * @param $id
 * @param $lp_flag
 * @param $lp_value
 * @return unknown_type
 */
function wphp_set_low_profile($item_type, $id, $lp_flag, $lp_value) {
    wphp_log("called: wphp_set_low_profile");
	global $wpdb;	
	// Ensure No Duplicates!
	$check = $wpdb->get_var("SELECT count(*) FROM ".WPHP_TABLE_NAME." WHERE post_id = $id AND meta_key='$lp_flag'");
	error_log("Check: $check");
	if(!$check) {
		$wpdb->query("INSERT INTO ".WPHP_TABLE_NAME."(post_id, meta_key, meta_value) VALUES($id, '$lp_flag', '$lp_value')");
	} elseif( $item_type == 'page' && $lp_flag == "_wplp_page_flags" ) {
		$wpdb->query("UPDATE ".WPHP_TABLE_NAME." set meta_value = '$lp_value' WHERE post_id = $id and meta_key = '$lp_flag'");
	}
}

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
			foreach($posts as $post) {
				$check = strval($wpdb->get_var("SELECT meta_value FROM ".WPHP_TABLE_NAME." WHERE post_id = $post->ID and meta_key = '_wplp_page_flags'"));
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
 * @return unknown_type
 */
function wphp_add_post_edit_meta_box() {
    wphp_log("called: wphp_add_post_edit_meta_box");
	add_meta_box('hidepostdivpost', __('Post Visibility', 'wp-hide-post'), 'wphp_metabox_post_edit', 'post', 'side');
	add_meta_box('hidepostdivpage', __('Page Visibility', 'wp-hide-post'), 'wphp_metabox_page_edit', 'page', 'side');
}
add_action('admin_menu', 'wphp_add_post_edit_meta_box');

/**
 * 
 * @return unknown_type
 */
function wphp_metabox_post_edit() {
    wphp_log("called: wphp_metabox_post_edit");
	global $wpdb;
	
	$id = isset($_GET['post']) ? intval($_GET['post']) : 0;

	$wplp_post_front = 0;
	$wplp_post_category = 0;
	$wplp_post_tag = 0;
	$wplp_post_author = 0;
	$wplp_post_archive = 0;
	$wplp_post_search = 0;
	$wplp_post_feed = 0;
	
	if($id > 0) {
		$flags = $wpdb->get_results("SELECT meta_key from ".WPHP_TABLE_NAME." where post_id = $id and meta_key like '_wplp_%'", ARRAY_N);
		if( $flags ) {
			foreach($flags as $flag_array) {
				$flag = $flag_array[0];
				// remove the leading _
				$flag = substr($flag, 1, strlen($flag)-1);
				${$flag} = 1;
			} 
		}
	}
?>
	<label for="wplp_post_front" class="selectit"><input type="checkbox" id="wplp_post_front" name="wplp_post_front" value="1"<?php checked($wplp_post_front, 1); ?>/>&nbsp;<?php _e('Hide on the front page.', 'wp-hide-post'); ?></label>
	<br />
	<label for="wplp_post_category" class="selectit"><input type="checkbox" id="wplp_post_category" name="wplp_post_category" value="1"<?php checked($wplp_post_category, 1); ?>/>&nbsp;<?php _e('Hide on category pages.', 'wp-hide-post'); ?></label>
	<br />
	<label for="wplp_post_tag" class="selectit"><input type="checkbox" id="wplp_post_tag" name="wplp_post_tag" value="1"<?php checked($wplp_post_tag, 1); ?>/>&nbsp;<?php _e('Hide on tag page(s).', 'wp-hide-post'); ?></label>
	<br />
	<label for="wplp_post_author" class="selectit"><input type="checkbox" id="wplp_post_author" name="wplp_post_author" value="1"<?php checked($wplp_post_author, 1); ?>/>&nbsp;<?php _e('Hide on author pages.', 'wp-hide-post'); ?></label>
	<br />
	<label for="wplp_post_archive" class="selectit"><input type="checkbox" id="wplp_post_archive" name="wplp_post_archive" value="1"<?php checked($wplp_post_archive, 1); ?>/>&nbsp;<?php _e('Hide in date archives (month, day, year, etc...)', 'wp-hide-post'); ?></label>
	<br />
	<label for="wplp_post_search" class="selectit"><input type="checkbox" id="wplp_post_search" name="wplp_post_search" value="1"<?php checked($wplp_post_search, 1); ?>/>&nbsp;<?php _e('Hide in search results.', 'wp-hide-post'); ?></label>
	<br />
	<label for="wplp_post_feed" class="selectit"><input type="checkbox" id="wplp_post_feed" name="wplp_post_feed" value="1"<?php checked($wplp_post_feed, 1); ?>/>&nbsp;<?php _e('Hide in feed(s).', 'wp-hide-post'); ?></label>
    <br />
    <div style="float:right;font-size: xx-small;"><a href="http://anappleaday.konceptus.net/posts/wp-hide-post/#comments"><?php _e("Leave feedback and report bugs...", 'wp-hide-post'); ?></a></div>
    <br />
    <div style="float:right;font-size: xx-small;"><a href="http://wordpress.org/extend/plugins/wp-hide-post/"><?php _e("Give 'WP Hide Post' a good rating...", 'wp-hide-post'); ?></a></div>
    <br />
<?php
}

/**
 * 
 * @return unknown_type
 */
function wphp_metabox_page_edit() {
    wphp_log("called: wphp_metabox_page_edit");
	global $wpdb;
	
	$id = isset($_GET['post']) ? intval($_GET['post']) : 0;

	$wplp_page = 'none';
	$wplp_page_search_show = 1;
	
	if($id > 0) {
		$flags = $wpdb->get_results("SELECT meta_value from ".WPHP_TABLE_NAME." where post_id = $id and meta_key = '_wplp_page_flags'", ARRAY_N);
		if( $flags )
			$wplp_page = $flags[0][0];
        $search = $wpdb->get_results("SELECT meta_value from ".WPHP_TABLE_NAME." where post_id = $id and meta_key = '_wplp_page_search'", ARRAY_N);
        if( $search )
            $wplp_page_search_show = ! $search[0][0];
	}
?>
	<label class="selectit"><input type="radio" id="wplp_page_none" name="wplp_page" value="none"<?php checked($wplp_page, 'none'); ?>/>&nbsp;<?php _e('Show normally everywhere.', 'wp-hide-post'); ?></label>
	<br />
	<br />
	<label class="selectit"><input type="radio" id="wplp_page_front" name="wplp_page" value="front"<?php checked($wplp_page, 'front'); ?>/>&nbsp;<?php _e('Hide when listing pages on the front page.', 'wp-hide-post'); ?></label>
	<br />
    <br />
    <label class="selectit"><input type="radio" id="wplp_page_all" name="wplp_page" value="all"<?php checked($wplp_page, 'all'); ?>/>&nbsp;<?php _e('Hide everywhere pages are listed.', 'wp-hide-post'); ?><sup>*</sup></label>
    <div style="height:18px;margin-left:20px">
        <div id="wplp_page_search_show_div"><label class="selectit"><input type="checkbox" id="wplp_page_search_show" name="wplp_page_search_show" value="1"<?php checked($wplp_page_search_show, 1); ?>/>&nbsp;<?php _e('Keep in search results.', 'wp-hide-post'); ?></label></div>
    </div>
    <br />
    <div style="float:right;clear:both;font-size:x-small;">* Will still show up in sitemap.xml if you generate one automatically. See <a href="http://anappleaday.konceptus.net/posts/wp-low-profiler/">details</a>.</div>
    <br />
    <br />
    <br />
    <div style="float:right;font-size: xx-small;"><a href="http://anappleaday.konceptus.net/posts/wp-hide-post/#comments"><?php _e("Leave feedback and report bugs...", 'wp-hide-post'); ?></a></div>
    <br />
	<div style="float:right;clear:both;font-size:xx-small;"><a href="http://wordpress.org/extend/plugins/wp-hide-post/"><?php _e("Give 'WP Hide Post' a good rating...", 'wp-hide-post'); ?></a></div>
	<br />
    <script type="text/javascript">
    <!--
		// toggle the wplp_page_search_show checkbox
        var wplp_page_search_show_callback = function () {
            if(jQuery("#wplp_page_all").is(":checked"))
                jQuery("#wplp_page_search_show_div").show();
            else
                jQuery("#wplp_page_search_show_div").hide();
        };
        jQuery("#wplp_page_all").change(wplp_page_search_show_callback);
        jQuery("#wplp_page_front").change(wplp_page_search_show_callback);
        jQuery("#wplp_page_none").change(wplp_page_search_show_callback);
        jQuery(document).ready( wplp_page_search_show_callback );
    //-->
    </script>
<?php
}

/**
 * 
 * @param $id
 * @return unknown_type
 */
function wphp_save_post($id) {
    wphp_log("called: wphp_save_post");
	$item_type = get_post_type($id);
	if( $item_type == 'post' ) {
		$wplp_post_front = isset($_POST['wplp_post_front']) ? $_POST['wplp_post_front'] : 0;
		$wplp_post_category = isset($_POST['wplp_post_category']) ? $_POST['wplp_post_category'] : 0;
		$wplp_post_tag = isset($_POST['wplp_post_tag']) ? $_POST['wplp_post_tag'] : 0;
		$wplp_post_author = isset($_POST['wplp_post_author']) ? $_POST['wplp_post_author'] : 0;
		$wplp_post_archive = isset($_POST['wplp_post_archive']) ? $_POST['wplp_post_archive'] : 0;
		$wplp_post_search = isset($_POST['wplp_post_search']) ? $_POST['wplp_post_search'] : 0;
		$wplp_post_feed = isset($_POST['wplp_post_feed']) ? $_POST['wplp_post_feed'] : 0;
		
		wphp_update_low_profile($id, '_wplp_post_front', $wplp_post_front);
		wphp_update_low_profile($id, '_wplp_post_category', $wplp_post_category);
		wphp_update_low_profile($id, '_wplp_post_tag', $wplp_post_tag);
		wphp_update_low_profile($id, '_wplp_post_author', $wplp_post_author);
		wphp_update_low_profile($id, '_wplp_post_archive', $wplp_post_archive);
		wphp_update_low_profile($id, '_wplp_post_search', $wplp_post_search);
		wphp_update_low_profile($id, '_wplp_post_feed', $wplp_post_feed);
	} elseif( $item_type == 'page' ) {
		$wplp_page = isset($_POST['wplp_page']) ? $_POST['wplp_page'] : 'none';
		wphp_update_low_profile($id, "_wplp_page_flags", $wplp_page);
		if( $wplp_page == 'all' ) {
            $wplp_page_search_show = isset($_POST['wplp_page_search_show']) ? $_POST['wplp_page_search_show'] : 0;
            wphp_update_low_profile($id, "_wplp_page_search", ! $wplp_page_search_show);
		} else
            wphp_update_low_profile($id, "_wplp_page_search", 0);
	}	
}
add_action('save_post', 'wphp_save_post');

/**
 * 
 * @param $post_id
 * @return unknown_type
 */
function wphp_delete_post($post_id) {
    wphp_log("called: wphp_delete_post");
	global $wpdb;
	// Delete all post flags from the database table
	$wpdb->query("DELETE FROM ".WPHP_TABLE_NAME." WHERE post_id = $post_id and meta_key like '_wplp_%'");
}
add_action('delete_post', 'wphp_delete_post');

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
		$join .= ' LEFT JOIN '.WPHP_TABLE_NAME.' wphptbl ON wp_posts.ID = wphptbl.post_id and wphptbl.meta_key like \'_wplp_%\'';
        // filter posts 
		$join .= ' AND ((wp_posts.post_type = \'post\' ';
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
        $join .= ' OR (wp_posts.post_type = \'page\' AND wphptbl.meta_key <> \'_wplp_page_flags\'';
        if( wphp_is_search())
            $join .= ' AND wphptbl.meta_key = \'_wplp_page_search\' ';
        else
            $join .= ' AND wphptbl.meta_key not like \'_wplp_%\' ';
        $join .= '))';   
	}
    //echo "\n<!-- WPHP: ".$join." -->\n";
    return $join;
}
add_filter('posts_where_paged', 'wphp_query_posts_where');
add_filter('posts_join_paged', 'wphp_query_posts_join');

?>