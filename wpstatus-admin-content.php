<div class="wrap">
	<div id="icon-tools" class="icon32"></div>
	<h2 style="margin: 1em 0 1.2em 0;">WP Status <span style="font-size: 15px; color: #777;">Manage Many WordPress Sites</span></h2>

	<div style="float: left; width: 25%; background: #d1eefc; padding: 1.5%; -webkit-border-radius: 4px; border-radius: 4px; -moz-background-clip: padding; -webkit-background-clip: padding-box; background-clip: padding-box; border-bottom: 1px solid #999; margin-bottom: 1em; margin-right: 1%; min-height: 110px;">
		<h3><span style="background-image: url(<?php echo WPSTATUS_URL; ?>img/monitor-window.png); width: 16px; height: 16px; display: block; overflow: hidden; float: left; margin-right: 4px;"></span>Managed Updates</h3>		
		<p>Manage updates more efficiently for WordPress core, plugins, and themes for <em>all</em> of your sites at <a href="http://wpstatus.com">wpstatus.com</a>. </p>
	</div>

	<div style="float: left; width: 25%; background: #d1eefc; padding: 1.5%; -webkit-border-radius: 4px; border-radius: 4px; -moz-background-clip: padding; -webkit-background-clip: padding-box; background-clip: padding-box; border-bottom: 1px solid #999; margin-bottom: 1em; margin-right: 1%; min-height: 110px;">
		<h3><span style="background-image: url(<?php echo WPSTATUS_URL; ?>img/paper-plane-return.png); width: 16px; height: 16px; display: block; overflow: hidden; float: left; margin-right: 4px;"></span>Backups</h3>
		<p>Your database, website files and all content sent to the Amazon cloud and Rackspace cloud, every day.</p>
	</div>

	<div style="float: left; width: 25%; background: #d1eefc; padding: 1.5%; -webkit-border-radius: 4px; border-radius: 4px; -moz-background-clip: padding; -webkit-background-clip: padding-box; background-clip: padding-box; border-bottom: 1px solid #999; margin-bottom: 1em; min-height: 110px;">
		<h3><span style="background-image: url(<?php echo WPSTATUS_URL; ?>img/plus-shield.png); width: 16px; height: 16px; display: block; overflow: hidden; float: left; margin-right: 4px;"></span>Security Scans (coming soon)</h3>
		<p>Daily code scans for malware and suspicious code.  If something looks fishy, we'll notify you asap.</p>
	</div>
	<p style="clear: both; text-align: right; width: 86%; ">More info at <a href="http://wpstatus.com">wpstatus.com</a>. </p>

	<form action="options.php" method="post">
		<?php settings_fields('wpstatus_options'); ?>
		<?php do_settings_sections('wpstatus_apikey'); ?>
		
		<input class="button-primary" type="submit" name="SaveFtp" value="<?php _e('Save Settings'); ?>" />
	</form>
</div>