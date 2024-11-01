<?php
class WPS_Core_Upgrader_Skin extends WP_Upgrader_Skin 
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

function wpstatusUpgradeCore() 
{
	global $wp_version;
	include_once (ABSPATH.'wp-admin/includes/admin.php');
	if (!wpstatusSupportsCoreUpgrade())
	{
		return array('status' => 0, 'message' => 'Wordpress version too old for core upgrades');
	}
	
	$skin = new WPS_Core_Upgrader_Skin();
	$upgrader = new Core_Upgrader($skin);
	$updates = get_core_updates();
	$update = find_core_update($updates[0]->current, $updates[0]->locale);
	// Do the upgrade
	ob_start();
	$result = $upgrader->upgrade($update);
	$data = ob_get_contents();
	ob_clean();
	return parseUpgradeResult('Wordpress', $skin, $result, $data);
}

function wpstatusSupportsCoreUpgrade() 
{
	include_once (ABSPATH . 'wp-admin/includes/admin.php');
	return class_exists('Core_Upgrader');
}