<?php
add_action( 'admin_menu', 'keypic_config_page' );

add_action('admin_init', 'keypic_admin_init');

$ms = array();

$messages = array(
	'key_empty' => array('color' => 'aa0', 'text' => sprintf(__('Your FormID is empty (<a href="%s" style="color:#fff">Get your FormID.</a>)'), 'http://keypic.com/modules/register/')),
	'key_valid' => array('color' => '4AB915', 'text' => __('This FormID is valid.')),
	'key_not_valid' => array('color' => 'aaa', 'text' => __('This FormID is NOT valid.')),
);

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
	global $ms, $messages;

	if(isset($_POST['submit']))
	{

		$FormID = $_POST['formid'];

		if(empty($FormID)){$ms[] = 'key_empty';}
		else{$FormID = strtolower($FormID);}

		$fields['RequestType'] = 'checkFormID';
		$fields['ResponseType'] = '2';
		$fields['FormID'] = $FormID;
		$request = sendRequest($fields, KEYPIC_HOST);

		$response = json_decode($request, true);

		if($response['status'] == 'response'){$ms[] = 'key_valid'; update_option('FormID', $FormID);}
		else if($response['status'] == 'error'){$ms[] = 'key_not_valid'; update_option('FormID', $FormID);}

	}
	else
	{
		if(get_option('FormID') == ''){$ms[] = 'key_empty';}
	}

	echo '<h2>' . __('Keypic Configuration') . '</h2>';

	echo	'<form action="" method="post" style="margin: auto; width: 500px; ">';

	echo '<p>' . __('For many people, <a href="http://keypic.com/" target="_blank">Keypic</a> will greatly reduce or even completely eliminate the comment and trackback spam you get on your site. If one does happen to get through, simply mark it as "spam" on the moderation screen and Keypic will learn from the mistakes. If you don\'t have an API FormID yet, you can get one at <a href="http://keypic.com/modules/register/" target="_blank">keypic.com</a>.') . '</p>';
	echo '<h3><label for="key">' . __('Keypic FormID') . '</label></h3>';

	foreach($ms as $m):
		echo '<p style="padding: .5em; background-color: #' . $messages[$m]['color'] . '; color: #fff; font-weight: bold;">' . $messages[$m]['text'] . '</p>';
	endforeach;

	echo '<p><input id="key" name="formid" type="text" size="32" maxlength="32" value="' . get_option('FormID') . '" style="font-family: \'Courier New\', Courier, mono; font-size: 1.5em;" /> (' . __('<a href="http://keypic.com/modules/register/">What is this?</a>') . ')</p>';

	if(isset( $invalid_key) && $invalid_key)
	{
		echo '<h3>' . __('Why might my key be invalid?') . '</h3>';
		echo '<p>' . __('This can mean one of two things, either you copied the key wrong or that the plugin is unable to reach the Keypic servers, which is most often caused by an issue with your web host around firewalls or similar.') . '</p>';
	}

	echo '<p class="submit"><input type="submit" name="submit" value="' . __('Update options &raquo;') . '" /></p>';
	echo	'</form>';

}
