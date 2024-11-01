<?php
class WPStatusAdmin 
{
	var $options;
	var $files_to_check;

	function __construct() 
	{
		add_action('admin_init', array(&$this, 'registerSettings'));
		add_action('admin_menu', array(&$this, 'admin_menu'));
		$this->options = get_option('wpstatus_options');
		if(!isset($this->options['ApiKey']))
		{
			add_action('admin_notices', array(&$this, 'adminNotice'));
		}

		$show_folder_notification = false;
		if(!is_dir(WPSTATUS_WRITABLE_FOLDER) && !mkdir(WPSTATUS_WRITABLE_FOLDER, 0777, true))
		{
			$show_folder_notification = true;
		}
		else
		{
			@file_put_contents(WPSTATUS_WRITABLE_FOLDER.'/.test', 'testing');
			if(!file_exists(WPSTATUS_WRITABLE_FOLDER.'/.test'))
			{
				$show_folder_notification = true;
			}
			else
			{
				unlink(WPSTATUS_WRITABLE_FOLDER.'/.test');
				$show_folder_notification = false;
			}
		}

		if($show_folder_notification)
		{
			add_action('admin_notices', array(&$this, 'needWritableFolder'));
		}
	}

	function registerSettings()
	{
		register_setting('wpstatus_options', 'wpstatus_options');
		add_settings_section('wpstatus_apikey', 'API Key', array($this, 'apiKeySectionHeading'), 'wpstatus_apikey');
		add_settings_field('ApiKey', 'API Key', array($this, 'apiKeyInput'), 'wpstatus_apikey', 'wpstatus_apikey');
	}

	function adminNotice()
	{
		?>
		<div id="wpstatus-message" class="updated">
				<p>
					Go to the <a href="/wp-admin/options-general.php?page=wpstatus-admin">WP Status settings page</a> to complete setup.
				</p>
				<style>#message { display : none; }</style>
		</div>
		<?php
	}

	function needWritableFolder()
	{
		?>
		<div id="wpstatus-message" class="error">
				<p>
					The <strong><?php echo WPSTATUS_WRITABLE_FOLDER; ?></strong> folder needs to be writable.
				</p>
				<style>#message { display : none; }</style>
		</div>
		<?php
	}

	function apiKeySectionHeading(){}

	function apiKeyInput()
	{
		?>
		<input id="ApiKey" name="wpstatus_options[ApiKey]" size="40" type="text" value="<?php echo $this->options['ApiKey'] ?>" />
		<?php
	}

	function admin_menu () 
	{
		$page = add_options_page('<img src="'.WPSTATUS_URL.'img/wp_menu_icon.gif"/> WP Status','<img src="'.WPSTATUS_URL.'img/wp_menu_icon.gif"/> WP Status','manage_options','wpstatus-admin',array($this, 'settings_page'));
		add_action('load-'.$page, array(&$this, 'updateOnSave'));
	}

	function updateOnSave()
	{
		if(isset($_GET['settings-updated']) && $_GET['settings-updated'])
		{
		  wpstatusOneTimeUpdate();
		}
	}

	function  settings_page () 
	{
		require('wpstatus-admin-content.php');
	}
}
new WPStatusAdmin();
