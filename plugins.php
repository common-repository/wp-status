<?php
class WPS_Plugin_Upgrader_Skin extends Plugin_Installer_Skin 
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

function wpstatusGetPlugins() 
{
	require_once(ABSPATH.'/wp-admin/includes/plugin.php');
	if (!wpstatusSupportsPluginUpgrade())
	{
		return array('status' => 0, 'code' => 504);
	}
	// Get all plugins
	$plugins = get_plugins();
	// Get the list of active plugins
	$active = get_option('active_plugins', array());
	
	// Delete the transient so wp_update_plugins can get fresh data
	if(function_exists('get_site_transient'))
	{
		delete_site_transient('update_plugins');
	}
	else
	{
		delete_transient('update_plugins');
	}
	// Force a plugin update check
	wp_update_plugins();
	
	if(function_exists('get_site_transient') && $transient = get_site_transient('update_plugins'))
	{
		$current = $transient;
	}
	elseif($transient = get_transient('update_plugins'))
	{
		$current = $transient;
	}
	else
	{
		$current = get_option('update_plugins');
	}

	foreach((array)$plugins as $plugin_file => $plugin) 
	{
    $new_version = isset($current->response[$plugin_file]) ? $current->response[$plugin_file]->new_version : null;
    if(is_plugin_active($plugin_file))
    {
    	$plugins[$plugin_file]['active'] = true;
    }
    else
    {
    	$plugins[$plugin_file]['active'] = false;
    }
    if($new_version)
    {
    	$plugins[$plugin_file]['latest_version'] = $new_version;
    	$plugins[$plugin_file]['latest_package'] = $current->response[$plugin_file]->package;
    	$plugins[$plugin_file]['slug'] = $current->response[$plugin_file]->slug;
    } 
    else 
    {
    	$plugins[$plugin_file]['latest_version'] = $plugin['Version'];
    }
	}
	return array('status' => 1, 'data' => $plugins);
}

function wpstatusUpgradePlugin($plugin) 
{
	include_once(ABSPATH.'wp-admin/includes/admin.php');
	if(!wpstatusSupportsPluginUpgrade())
	{
		return array('status' => 0, 'code' => 504);
	}
	$skin = new WPS_Plugin_Upgrader_Skin();
	$upgrader = new Plugin_Upgrader($skin);
	$is_active = is_plugin_active($plugin);
	// Do the upgrade
	ob_start();
	$result = $upgrader->upgrade($plugin);
	$data = ob_get_contents();
	ob_clean();
	wp_update_plugins();
	$response = parseUpgradeResult('Plugin', $skin, $result, $data);
	if(!($response['code'] < 300))
	{
		return $response;
	}
	// If the plugin was activited, we have to re-activate it
	if($is_active) 
	{	
		// we do a remote request to activate, as we don't want to kill any installs 
		$url = add_query_arg('wpstatus_key', $_GET['wpstatus_key'], get_bloginfo( 'url' ));
		$url = add_query_arg('actions', 'activate_plugin', $url);
		$url = add_query_arg('plugin', $plugin, $url);
		
		$request = wp_remote_get($url);
		if(is_wp_error($request)) 
		{
			return array('status' => 1, 'code' => 500, 'message' => $request->get_error_code(), 'type' => 'Plugin');
		}
		
		$body = wp_remote_retrieve_body($request);
		
		if(!$json = @json_decode($body))
		{	
			if(!wpstatusActivatePlugin($plugin, true))
			{
				return array('status' => 1, 'code' => 505, 'type' => 'Plugin');
			}
			else
			{
				return array('status' => 1, 'code' => 201, 'type' => 'Plugin');
			}
		}
		$json = $json->activate_plugin;
		
		if(empty($json->status))
		{
			return array('status' => 1, 'code' => 506, 'type' => 'Plugin');
		}
		if($json->status != 'success')
		{
			return array('status' => 1, 'code' => 500, 'message' => 'The plugin was updated, but failed to re-activate. The activation request returned response: '.$json->status, 'type' => 'Plugin');
		}
	}
	return array('status' => 1, 'code' => 201, 'type' => 'Plugin');
}

function wpstatusActivatePlugin($plugin, $internal = false) 
{	
	include_once(ABSPATH.'wp-admin/includes/plugin.php');
	$result = activate_plugin($plugin);
	if(!$internal)
	{
		if(is_wp_error($result))
		{
			return array('status' => 0, 'code' => 500, 'message' => $result->get_error_code(), 'type' => 'Plugin');
		}
		return array('status' => 1, 'code' => 202, 'type' => 'Plugin');
	}
	else
	{
		if(is_wp_error($result))
		{
			return 0;
		}
		return 1;
	}
}

function wpstatusSupportsPluginUpgrade() 
{
	include_once(ABSPATH.'wp-admin/includes/admin.php');
	return class_exists('Plugin_Upgrader');
}