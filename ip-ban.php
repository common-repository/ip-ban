<?php
/*
Plugin Name: IP Ban
Plugin URI: http://w3prodigy.com/wordpress-plugins/ip-ban/
Description: Returns 'Page Not Found' 404 error message for IP's visiting your blog specified in the IP Ban option on the Discussion Options page.
Author: Jay Fortner
Author URI: http://w3prodigy.com
Version: 0.7
Tags: anti-spam, ban, ip, plugin, privacy, security, spam
License: GPL2
*/

new IP_Ban;

/**
 * IP Ban
 * @since 0.1
 * @uses IP_Ban_Options
 * 
 * This object uses the following options:
 * 1. IP Ban
 */
class IP_Ban {
	
	/**
	 * Controller
	 *
	 * Instantiates the IP_Ban_Options object
	 * Add init action for every public request
	 * Add admin_init action for every wp-admin request
	 */
	function IP_Ban()
	{
		new IP_Ban_Options;
		add_action( 'init', array( &$this, 'init' ) );
		add_action( 'admin_init', array( &$this, 'admin_init' ) );
		#add_filter( 'get_comment', array( &$this, 'get_comment' ) );
		# wp_loaded
	} // function
	
	/**
	 * Public
	 * 1. IP Ban
	 *
	 * This filter is applied every time a Visitor requests a public url on your blog
	 */
	function init()
	{
		# 1. IP Ban
		$this->ban_check();
	} // function
	
	/**
	 * Admin
	 * 1. IP Ban
	 *
	 * This filter is applied every time a Visitor requests an admin url on your blog
	 */
	function admin_init()
	{
		# 1. IP Ban
		$this->ban_check();
	} // function
	
	/**
	 * Returns 404 Error if a provided IP is banned
	 */
	function ban_check()
	{
		$ips = $this->get_visitor_ips();
		
		if( $this->is_banned( $ips ) ):
			header("HTTP/1.0 404 Not Found");
			die();
		endif;
		
	} // function
	
	/**
	 * Check IP against the list
	 *
	 * @param (array) $ips - containing possible visitor IPs
	 * @return (bool) true if IP is banned
	 * @return (bool) false if IP is not banned
	 */
	function is_banned( $ips = null )
	{
		if( is_null( $ips ) )
			return false;
		
		# 1. IP Ban
		if( $ip_ban_list = get_site_option( 'ip_ban' ) ):
			$ip_ban_ips = $this->get_list_items( $ip_ban_list );
			foreach( $ips as $key => $ip ):
				if( in_array( $needle = $ip, $haystack = $ip_ban_ips ) )
					return true;
			endforeach;
		endif;
		
		return false;
	} // function
	
	function get_comment( $comment )
	{
		if( $comment->comment_approved == 'spam' ):
			$ips = array( $comment->comment_author_IP );
		
			if( $this->is_banned( $ips ) ):
				wp_trash_comment( $comment->comment_ID );
				return false;
			endif;
		endif;
		
		return $comment;
	} // function
	
	/**
	 * Get Possible IPs
	 *
	 * @uses HTTP_CLIENT_IP - Shared Internet IP
	 * @uses HTTP_X_FORWARDED_FOR - Proxy IP
	 * @uses REMOTE_ADDR - Public IP
	 * @return (array) Array containing possible IPs
	 */
	function get_visitor_ips()
	{
		$ips = array();
		if( !empty( $_SERVER['HTTP_CLIENT_IP'] ) )
			$ips[] = $_SERVER['HTTP_CLIENT_IP'];
			
		if( !empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) )
			$ips[] =$_SERVER['HTTP_X_FORWARDED_FOR'];
		
		$ips[] = $_SERVER['REMOTE_ADDR'];
		
		return $ips;
	} // function
	
	/**
	 * Create an array from new line separated lists
	 */
	function get_list_items( $list = null )
	{
		if( is_null( $list ) ):
			trigger_error( 'First argument can not be null', E_USER_WARNING );
			return false;
		endif;
		
		$search = array(
			"\r\n",
			"\r",
			"\n\n"
			);
		$list = str_replace( $search, $replace = "\n", $subject = $list );
		
		return explode( "\n", $list );
	} // function
	
} // class

/**
 * IP Ban Options
 * @since 0.1
 *
 * This object adds the following options to the discussions page:
 * 1. IP Ban
 */
class IP_Ban_Options extends IP_Ban {
	
	/**
	 * Controller
	 *
	 * Add admin_init action for every administrative page load
	 * Add comment_row_actions filter
	 */
	function IP_Ban_Options()
	{
		add_action( 'admin_init', array( &$this, 'admin_init' ) );
		add_filter( 'comment_row_actions', array( &$this, 'comment_row_actions' ), 10, 2 );
	} // function
	
	/**
	 * Register settings and add our fields to the Discussion settings page
	 * Handle Ban action from Comment Row Actions
	 *
	 * This action is called on every administrative page load
	 */
	function admin_init()
	{
		if( !empty( $_GET['action'] ) && !empty( $_GET['ip'] ) && !empty( $_GET['c'] ) && false !== strpos( $_GET['action'], 'ip_ban' ) ):
		
			$comment_id = absint( $_GET['c'] );
			check_admin_referer( 'ip-ban-comment_' . $comment_id );
		
			$ip = array( $_GET['ip'] );
		
			switch( $_GET['action'] ):
				case 'ip_ban':
					wp_trash_comment( $comment_id );
					
					if( false === $this->is_banned( $ip ) ):
						$ip_ban_list = get_site_option( 'ip_ban' );
						
						$ips = explode( ", ", $_GET['ip'] );
						
						$ips_to_ban = "";
						foreach( $ips as $key => $ip )
							$ips_to_ban .= "$ip\n";

						$ips_to_ban = rtrim( $ips_to_ban, "\n" );
						
						if( empty( $ip_ban_list ) )
							$ip_ban_list = $ips_to_ban;
						else
							$ip_ban_list .= "\n" . $ips_to_ban;

						update_site_option( 'ip_ban', $ip_ban_list );
					endif;
					
					break;
				case 'ip_ban_release':
					wp_untrash_comment( $comment_id );
					
					if( $this->is_banned( $ip ) ):
						$ip_ban_list = get_site_option( 'ip_ban' );

						$ip_ban_list = str_replace( $ip, '', $ip_ban_list );
						$list = $this->get_list_items( $ip_ban_list );
						
						$ip_ban_list = implode( "\n", $list );
						update_site_option( 'ip_ban', $ip_ban_list );
					endif;
					
					break;
			endswitch;
			
			if( !empty( $_SERVER['HTTP_REFERER'] ) )
				wp_safe_redirect( $_SERVER['HTTP_REFERER'] );
			
		endif;
		
		# 1. IP Ban
		add_settings_field( 
			$id = 'ip_ban',
			$title = "Banned IP's",
			$callback = array( &$this, 'ip_ban' ),
			$page = 'discussion'
			);
		register_setting( $option_group = 'discussion', $option_name = 'ip_ban' );
	} // function
	
	/**
	 * Displays action to ban an IP from the edit comments page
	 */
	function comment_row_actions( $actions, $comment )
	{
		$comment_author_ip = $comment->comment_author_IP;
		
		if( empty( $comment_author_ip ) )
			return $actions;
		
		$ip = array( $comment_author_ip );
		
		$ban_nonce = wp_create_nonce( "ip-ban-comment_$comment->comment_ID" );
		
		$ip_ban_args = array(
			'action' => 'ip_ban',
			'c' => $comment->comment_ID,
			'ip' => $comment_author_ip,
			'_wpnonce' => $ban_nonce
			);
		
		if( $this->is_banned( $ip ) ):
			$ip_ban_args['action'] = 'ip_ban_release';
			$ip_ban_url = add_query_arg( $ip_ban_args );
			
			$actions['ip_ban'] = "<a title='Remove $comment_author_ip from banned IP list' href='$ip_ban_url' class='restore'>Allow IP $comment_author_ip</a>";
			return $actions;
		endif;
		
		$ip_ban_url = add_query_arg( $ip_ban_args );
		
		$actions['ip_ban'] = "<a title='Add $comment_author_ip to banned IP list' href='$ip_ban_url' class='delete' style='color:#BC0B0B'>Ban IP $comment_author_ip</a>";
		return $actions;
	} // function
	
	/**
	 * Option Field - 1. IP Ban
	 */
	function ip_ban()
	{
		$value = get_site_option('ip_ban');
		
		$list = $this->get_list_items( $value );
		$value = implode( "\n", $list );
		
		echo "<p><label for='back_list_white'>When a Visitor comes from any of these IP's they will be presented with a <strong>404 'Page Not Found' Error Message</strong>. One IP per line.</label></p>";
		echo "<textarea name='ip_ban' rows='10' cols='50' id='ip_ban' class='large-text code'>$value</textarea>";
	} // function
	
} // class