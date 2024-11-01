<?php
/*
Plugin Name: WP Status
Description: <strong>UPDATES &bull;</strong> Manage update requirements for all of your WordPress sites from one place. <br><strong>BACKUPS &bull;</strong> Daily backups of your entire website to the cloud. <br><strong>MALWARE SCANS &bull;</strong> Daily security scans, get notified if something seems suspicious.
Tags: Updates, Upgrade, Backups, Security Scans
Version: 0.11.4
Author: WP Status
Plugin URI: http://www.wpstatus.com/
Author URI: http://www.wpstatus.com/
*/

/*  
Copyright 2012  Jordan Patterson  (email : jordan@wpstatus.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 2, as 
published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

$upload_dir = wp_upload_dir();
register_activation_hook( __FILE__, 'wpstatusActivate' );
define('WPSTATUS_DIR', dirname(__FILE__));
define('WPSTATUS_URL', plugin_dir_url(__FILE__));
define('WPSTATUS_WRITABLE_FOLDER', $upload_dir['basedir'].'/wp-status');

if(!is_dir(WPSTATUS_WRITABLE_FOLDER))
{
	@mkdir(WPSTATUS_WRITABLE_FOLDER, 0777, true);
}

// Add settings link on plugin page
function wpstatusSettingsLink($links) { 
  $settings_link = '<a href="options-general.php?page=wpstatus-admin">Settings</a>'; 
  array_unshift($links, $settings_link); 
  return $links; 
}
 
$plugin = plugin_basename(__FILE__); 
add_filter("plugin_action_links_$plugin", 'wpstatusSettingsLink' );

require_once(WPSTATUS_DIR. '/wpstatus-admin.php' );

function wpstatusActivate()
{
	if(file_exists(dirname(__FILE__).'/apikey.txt'))
	{
		$apikey = file_get_contents(dirname(__FILE__).'/apikey.txt');
		register_setting('wpstatus_options', 'wpstatus_options');
		update_option('wpstatus_options', array('ApiKey' => $apikey));
		@unlink(dirname(__FILE__).'/apikey.txt');
	}
}

function getUsersWithRole($role) 
{
	global $wp_version, $wpdb;
	if($wp_version >= '3.1')
	{
	  $users = new WP_User_Query(array('role' => $role));
	  $administrators = $users->get_results();
	  return $administrators[0]->ID;
	}
	else
	{
		$users = $wpdb->get_results("SELECT ID FROM $wpdb->users ORDER BY ID");
		foreach($users as $u)
		{
			$data = get_userdata($u->ID);
			if(array_key_exists('administrator', $data->wp_capabilities))
			{
				return $data->ID;
			}
		}
	}

	return 1;
}

function getFailedApiCallResult($message)
{
	return array('success' => 0, 'message' => $message);
}

function setupFTPAccess()
{
	$options = get_option('wpstatus_options');
	if(!empty($options['FtpUsername']) && !empty($options['FtpPassword']))
	{
		if(!defined('FTP_USER'))
			define('FTP_USER', $options['FtpUsername']);

		if(!defined('FTP_PASS'))
			define('FTP_PASS', $options['FtpPassword']);

		if(!defined('FTP_HOST'))
			define('FTP_HOST', @$options['FtpHost']);
	}
}

function parseUpgradeResult($type, $skin, $result, $data)
{
	if($skin->error)
	{
		return array('status' => 0, 'code' => 500, 'message' => $skin->upgrader->strings[$skin->error], 'type' => $type);
	}

	if(((!$result && !is_null($result)) || $data) && defined('FTP_USER'))
	{
		return array('status' => 0, 'code' => 501, 'type' => $type);
	}
	elseif((!$result && !is_null($result)) || $data)
	{
		return array('status' => 0, 'code' => 502, 'type' => $type);
	}
	elseif(is_wp_error($result))
	{
		return array('status' => 0, 'code' => 500, 'message' => $result->get_error_code(), 'type' => $type);
	}

	return array('status' => 1, 'code' => 201, 'type' => $type);
}

function wpstatusAPI() 
{
	global $wp_version;
	
	if (!empty($_GET['wpstatus_key']) && urldecode($_GET['wpstatus_key']) && isset($_GET['wpstatus_action']))
	{
		require_once(ABSPATH.'wp-admin/includes/class-wp-upgrader.php');

		error_reporting(0);
		$options = get_option('wpstatus_options');
		if(urldecode($_GET['wpstatus_key']) !== $options['ApiKey'])
		{
			echo json_encode(array('status' => 0, 'code' => 504, 'type' => 'System'));
		}
		else
		{
			require_once(WPSTATUS_DIR.'/plugins.php');
			require_once(WPSTATUS_DIR.'/core.php');
			require_once(WPSTATUS_DIR.'/themes.php');
			require_once(WPSTATUS_DIR.'/backups.php');

			$action = $_GET['wpstatus_action'];
			$admin_id = getUsersWithRole('administrator');
			wp_set_current_user($admin_id);

			setupFTPAccess();

			switch($action)
			{
				case 'check_communication':
					$result = array('status' => 1);
				break;
				case 'get_title':
					$result = array('status' => 1, 'data' => array('title' => (string)get_bloginfo('name')));
				break;
				case 'get_wp_version':
					$update = get_site_transient( 'update_core' );
					$result = array('status' => 1, 'data' => array('version' => (string)$update->version_checked, 'new_version' => (string)$update->updates[0]->current));
				break;
				case 'upgrade_core':
					$result = wpstatusUpgradeCore();
				break;
				case 'get_plugins':
					$result = wpstatusGetPlugins();
				break;
				case 'upgrade_plugin':
					$result = wpstatusUpgradePlugin((string)$_GET['plugin']);
				break;
				case 'get_themes':
					$result = wpstatusGetThemes();
				break;
				case 'upgrade_theme':
					$result = wpstatusUpgradeTheme((string)$_GET['theme']);
				break;
				case 'backup':
					$result = wpstatusBackup($_GET['filetime'], @$_GET['lastfile']);
				break;
				case 'dbbackup':
					$result = wpstatusBackupDB();
				break;
				case 'get_hashes':
					wpstatusGetHashes(ABSPATH.'/{,.}*', GLOB_BRACE);
				break;
				case 'get_file':
					wpstatusGetFile(ABSPATH.'/'.base64_decode($_GET['wpstatus_file']));
				break;
				case 'replace_file':
					$result = wpstatusReplaceFile(ABSPATH.'/'.base64_decode($_GET['wpstatus_file']));
				default:
					$result = array('success' => 0, 'code' => 507, 'type' => 'system');
				break;
			}

			if(isset($result))
				echo json_encode($result);
		}
		die;
	}
}
add_action('init', 'wpstatusAPI', 1);

if(!wp_next_scheduled('wpstatus_send_updates'))
{
	wp_schedule_event(time(), 'hourly', 'wpstatus_send_updates');
}

function wpstatusSendUpdateCheck()
{
	global $wp_version;
	require_once(ABSPATH.'wp-admin/includes/class-wp-upgrader.php');
	require_once(WPSTATUS_DIR.'/plugins.php');
	require_once(WPSTATUS_DIR.'/themes.php');
	$plugins = wpstatusGetPlugins();
	$themes = wpstatusGetThemes();

	$update = get_site_transient( 'update_core' );

	wpstatusSend('updates/wordpress', array('core' => array('version' => (string)$update->version_checked, 'new_version' => (string)$update->updates[0]->current), 'plugins' => $plugins['data'], 'themes' => $themes['data']));
}

function wpstatusSend($path, $data)
{
	$options = get_option('wpstatus_options');
	if(isset($options['ApiKey']))
	{
		$url = 'https://manage.wpstatus.com/api/'.$path.'/'.$options['ApiKey'];
		$response = wp_remote_post($url, array(
			'method' => 'POST',
			'timeout' => 45,
			'redirection' => 5,
			'httpversion' => '1.0',
			'blocking' => false,
			'body' => $data
		));
	}
}

function wpstatusOneTimeUpdate()
{
	wp_schedule_single_event(time(), 'wpstatus_send_updates');
}

function wpstatusTestRun()
{
	if(strstr($_SERVER['HTTP_HOST'], 'files.wpstatus.com'))
	{
		if(isset($_COOKIE['wpstatus_session']))
		{
	    $ci_session = unserialize(stripslashes(substr($_COOKIE['wpstatus_session'], 0, strlen($_COOKIE['wpstatus_session'])-32)));
	    if(!isset($ci_session['User']))
	    {
	      header('Location: https://manage.wpstatus.com');
	      die();
	    }
	    else
	    {
	    	wp_enqueue_script('wpstatus', 'https://manage.wpstatus.com/guineapig/get/guineapig-load.js');
	    }
		}
		else
		{
			header('Location: https://manage.wpstatus.com');
	    die();
		}
	}
}

add_action('wpstatus_send_updates', 'wpstatusSendUpdateCheck');
add_action('admin_action_install-plugin', 'wpstatusOneTimeUpdate');
add_action('admin_action_install-theme', 'wpstatusOneTimeUpdate');
add_action('after_db_upgrade', 'wpstatusOneTimeUpdate');
add_action('after_mu_upgrade', 'wpstatusOneTimeUpdate');
add_action('activate_plugin', 'wpstatusOneTimeUpdate');
add_action('deactivate_plugin', 'wpstatusOneTimeUpdate');
add_action('switch_theme', 'wpstatusOneTimeUpdate');

add_action('init', 'wpstatusTestRun');
