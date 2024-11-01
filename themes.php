<?php
class WPS_Theme_Upgrader_Skin extends Theme_Installer_Skin 
{
	var $feedback;
	var $error;

	function error($error) 
	{
		$this->error = $error;
	}

	function feedback($feedback) 
	{
		$this->feedback = $feedback;
	}

	function before() {}
	function after() {}
	function header() {}
	function footer() {}
}

function wpstatusGetThemes() 
{
	global $wp_version;
	require_once(ABSPATH.'/wp-admin/includes/theme.php');
	if (!wpstatusSupportsThemeUpgrade())
	{
		return array('status' => 0, 'code' => 503);
	}
	
	// Get all themes
	$allthemes = get_themes();
	// Get the list of active themes
	if($wp_version >= '3.4')
	{
		$active = wp_get_theme();
		$active = $active['Name'];
	}
	else
	{
		$active = get_current_theme();
	}
	// Force a theme update check
	wp_update_themes();
	// Different versions of wp store the updates in different places
	// TODO can we depreciate
	if(function_exists('get_site_transient') && $transient = get_site_transient('update_themes'))
	{
		$current = $transient;
	}
	elseif($transient = get_transient('update_themes'))
	{
		$current = $transient;
	}
	else
	{
		$current = get_option('update_themes');
	}	
	$themes = array();
	foreach((array)$allthemes as $theme) 
	{
		if($wp_version >= '3.4')
		{
			$stylesheet = $theme->get_stylesheet();
		}
		else
		{
			$stylesheet = $theme['Stylesheet'];
		}
		$new_version = isset($current->response[$theme['Template']]) ? $current->response[$theme['Template']]['new_version'] : null;
    if($active == $theme['Name'])
    {
    	$themes[$theme['Name']]['active'] = true;
    }
    else
    {
    	$themes[$theme['Name']]['active'] = false;
    }
    $themes[$theme['Name']]['current_version'] = $theme['Version'];
    $themes[$theme['Name']]['path'] = $stylesheet;
    if($new_version) 
    {
    	$themes[$theme['Name']]['latest_version'] = $new_version;
    	$themes[$theme['Name']]['latest_package'] = $current->response[$theme['Template']]['package'];
    } 
    else 
    {
    	$themes[$theme['Name']]['latest_version'] = $theme['Version'];
    }
	}
	return array('status' => 1, 'data' => $themes);
}

function wpstatusUpgradeTheme($theme) 
{
	include_once(ABSPATH.'wp-admin/includes/admin.php');
	if (!wpstatusSupportsThemeUpgrade())
	{
		return array('status' => 0, 'code' => 503);
	}
	$skin = new WPS_Theme_Upgrader_Skin();
	$upgrader = new Theme_Upgrader($skin);
	// Do the upgrade
	ob_start();
	$result = $upgrader->upgrade($theme);
	$data = ob_get_contents();
	ob_clean();
	wp_update_themes();
	return parseUpgradeResult('Theme', $skin, $result, $data);
}

function wpstatusSupportsThemeUpgrade() 
{
	include_once(ABSPATH.'wp-admin/includes/admin.php');
	return class_exists('Theme_Upgrader');
}