<?php
add_action( 'admin_menu', 'keypic_config_page' );

add_action('admin_init', 'keypic_admin_init');

function keypic_admin_init()
{
	global $wp_version;

	// all admin functions are disabled in old versions

	if(!function_exists('is_multisite') && version_compare($wp_version, '3.0', '<' ))
	{
		function keypic_version_warning()
		{
			echo "
            <div id='keypic-warning' class='updated fade'><p><strong>".sprintf(__('Keypic %s requires WordPress 3.0 or higher.'), KEYPIC_VERSION) . "</strong> " . sprintf(__('Please <a href="%s">upgrade WordPress</a> to a current version</a>.'), 'http://codex.wordpress.org/Upgrading_WordPress', 'http://wordpress.org/extend/plugins/keypic/download/') . "</p></div>
            ";
        }

		add_action('admin_notices', 'keypic_version_warning');
		return;
	}
}

function keypic_config_page()
{
	if(function_exists('add_submenu_page')){add_submenu_page('plugins.php', __('Keypic Configuration'), __('Keypic Configuration'), 'manage_options', 'keypic-key-config', 'keypic_conf');}
}

function keypic_plugin_action_links( $links, $file )
{
	if( $file == plugin_basename( dirname(__FILE__).'/keypic.php'))
	{
		$links[] = '<a href="plugins.php?page=keypic-key-config">'.__('Settings').'</a>';
	}

	return $links;
}

add_filter( 'plugin_action_links', 'keypic_plugin_action_links', 10, 2 );

function keypic_conf()
{
	if(isset($_POST['submit']))
	{
		//$key = preg_replace( '/[^a-h0-9]/i', '', $_POST['key'] );

		$key = $_POST['key'];

		if(!empty($key))
		{
			update_option('FormID', $key);
		}
	}

	echo '<h2>' . __('Keypic Configuration') . '</h2>';

	echo	'<form action="" method="post" style="margin: auto; width: 500px; ">';
	if(!$wpcom_api_key)
	{
		echo '<p>' . __('For many people, <a href="http://keypic.com/" target="_blank">Keypic</a> will greatly reduce or even completely eliminate the comment and trackback spam you get on your site. If one does happen to get through, simply mark it as "spam" on the moderation screen and Keypic will learn from the mistakes. If you don\'t have an API FormID yet, you can get one at <a href="http://keypic.com/modules/register/" target="_blank">keypic.com</a>.') . '</p>';
		echo '<h3><label for="key">' . __('Keypic FormID') . '</label></h3>';

		foreach ( $ms as $m ) :
			echo '<p style="padding: .5em; background-color: #' . $messages[$m]['color'] . '; color: #fff; font-weight: bold;">' . $messages[$m]['text'] . '</p>';
		endforeach;

		echo '<p><input id="key" name="key" type="text" size="32" maxlength="32" value="' . get_option('FormID') . '" style="font-family: \'Courier New\', Courier, mono; font-size: 1.5em;" /> (' . __('<a href="http://keypic.com/modules/register/">What is this?</a>') . ')</p>';

		if(isset( $invalid_key) && $invalid_key)
		{
			echo '<h3>' . __('Why might my key be invalid?') . '</h3>';
			echo '<p>' . __('This can mean one of two things, either you copied the key wrong or that the plugin is unable to reach the Keypic servers, which is most often caused by an issue with your web host around firewalls or similar.') . '</p>';
		}
	}
	echo '<p class="submit"><input type="submit" name="submit" value="' . __('Update options &raquo;') . '" /></p>';
	echo	'</form>';

}
