<?php
add_action( 'admin_menu', 'keypic_config_page', 8);

add_action('admin_init', 'keypic_admin_init');

$ms = array();

$messages = array(
	'key_empty' => array('color' => 'aa0', 'text' => sprintf(__('Your FormID is empty (<a href="%s" style="color:#fff">Get your FormID.</a>)'), 'http://keypic.com/modules/register/')),
	'key_valid' => array('color' => '4AB915', 'text' => __('This FormID is valid.')),
	'key_not_valid' => array('color' => 'aaa', 'text' => __('This FormID is NOT valid.')),
);

$plugins = array
(
		// Wordpress builtin plugins
		'register' => array('Name' => 'Registration Form details', 'builtin' => 1),
		'login' => array('Name' => 'Login Form details', 'builtin' => 1),
		'comments' => array('Name' => 'Comment Form details', 'builtin' => 1),
		'lostpassword' => array('Name' => 'Lostpassword Form details', 'builtin' => 1),

		// Custom Plugins here
		'contact_form_7' => array('Name' => 'Contact Form 7', 'builtin' => 0),
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
	if(function_exists('add_submenu_page'))
	{
		add_submenu_page('plugins.php', __('Keypic Configuration'), __('Keypic Configuration'), 'manage_options', 'keypic-key-config', 'keypic_conf');
		add_menu_page('custom menu title', 'Keypic', 'administrator', 'plugins.php?page=keypic-key-config', '',   KEYPIC_PLUGIN_URL .'/menu-icon.png');
	}
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

function keypic_report_spam_and_delete_comment()
{
	global $keypic_comments, $FormID;

	if(!(isset($_GET['id']) || (isset($_REQUEST['action']) && 'keypic_report_spam_and_delete_comment' == $_REQUEST['action']))){return;}

	$comment = $keypic_comments[$_GET['id']];

	Keypic::reportSpam($comment['token']);
	wp_delete_comment($_GET['id']);

	wp_redirect( $_SERVER['HTTP_REFERER'] );
	die();
}

add_action('admin_action_keypic_report_spam_and_delete_comment', 'keypic_report_spam_and_delete_comment');

function keypic_report_spam_and_delete_user()
{
	global $FormID;

	if(!(isset($_GET['id']) || (isset($_REQUEST['action']) && 'keypic_report_spam_and_delete_user' == $_REQUEST['action']))){return;}

	$keypic_users = get_option('keypic_users');
	$user = $keypic_users[$_GET['id']];

	Keypic::reportSpam($user['token']);
	wp_delete_user($_GET['id']);

	wp_redirect( $_SERVER['HTTP_REFERER'] );
	die();
}

add_action('admin_action_keypic_report_spam_and_delete_user', 'keypic_report_spam_and_delete_user');


function plugins_list()
{
	global $plugins;

	$output = array();

	foreach(get_plugins() as $plugin_file => $plugin_data)
	{
		foreach($plugins as $k => $v)
		{
			if($v['builtin'] == 1)
			{
				$output[$k] = $v;
			}
			else
			{
				if(is_plugin_active($plugin_file))
				{
					if($plugin_data['Name'] == $v['Name'])
					{
						$output[$k] = $v;
					}
				}
			}
		}
	}

	return $output;
}

function keypic_conf()
{
	global $keypic_details, $ms, $messages;

	$FormID = isset($keypic_details['FormID']) ? $keypic_details['FormID'] : '' ;

	$submit1 = isset($_POST['submit1']) ? $_POST['submit1'] : '';
	if($submit1 == 'submit1')
	{
		$FormID = $_POST['formid'];

		if(empty($FormID)){$ms[] = 'key_empty';}
		else{$FormID = strtolower($FormID);}

		$response = Keypic::checkFormID($FormID);

		if($response['status'] == 'response'){$ms[] = 'key_valid'; $keypic_details['FormID'] = $FormID; update_option('keypic_details', $keypic_details);}
		else if($response['status'] == 'error')
		{
			$ms[] = 'key_not_valid';
			if($FormID != ''){$FormID = '';}
			$keypic_details['FormID'] = $FormID;
			update_option('keypic_details', $keypic_details);
		}
	}
	else
	{
		if($FormID == ''){$ms[] = 'key_empty';}
	}

	$submit2 = isset($_POST['submit2']) ? $_POST['submit2'] : '';
	if($submit2 == 'submit2')
	{
		foreach(plugins_list() as $k => $v)
		{
			$RequestType = isset($_POST[$k.'_requesttype']) ? $_POST[$k.'_requesttype'] : '';
			$WeighthEight = isset($_POST[$k.'_weightheight']) ? $_POST[$k.'_weightheight'] : '';
			$enabled = isset($_POST[$k.'_enabled']) ? $_POST[$k.'_enabled'] : '';
			$keypic_details[$k] = array('RequestType' => $RequestType, 'WeighthEight' => $WeighthEight, 'enabled' => $enabled);
		}

		update_option('keypic_details', $keypic_details);
	}
	else
	{
		if($FormID == ''){$ms[] = 'key_empty';}
	}

	echo '<h2>' . __('Keypic Configuration') . ' - Version ' . KEYPIC_VERSION .'</h2>';

	// Form
	echo	'<form name="formid" action="" method="post" style="margin: auto; width: 750px; ">';

	echo '<p>' . __('For many people, <a href="http://keypic.com/" target="_blank">Keypic</a> will greatly reduce or even completely eliminate the comment and trackback spam you get on your site. If one does happen to get through, simply mark it as "spam" on the moderation screen and Keypic will learn from the mistakes. If you don\'t have an API FormID yet, you can get one at <a href="http://keypic.com/modules/register/" target="_blank">keypic.com</a>.') . '</p>';
	echo '<h3><label for="key">' . __('Keypic FormID') . '</label></h3>';

	foreach($ms as $m):
		echo '<p style="padding: .5em; background-color: #' . $messages[$m]['color'] . '; color: #fff; font-weight: bold;">' . $messages[$m]['text'] . '</p>';
	endforeach;

	echo '<p><input id="key" name="formid" type="text" size="32" maxlength="32" value="' . $FormID . '" style="font-family: \'Courier New\', Courier, mono; font-size: 1.5em;" /> (' . __('<a href="http://keypic.com/modules/register/">What is this?</a>') . ')</p>';

	if(isset( $invalid_key) && $invalid_key)
	{
		echo '<h3>' . __('Why might my key be invalid?') . '</h3>';
		echo '<p>' . __('This can mean one of two things, either you copied the key wrong or that the plugin is unable to reach the Keypic servers, which is most often caused by an issue with your web host around firewalls or similar.') . '</p>';
	}

	echo '<p class="submit"><input type="submit" name="input_submit" value="' . __('Update FormID &raquo;') . '" /></p>';
	echo '<input type="hidden" name="submit1" value="submit1" />';
	echo	'</form>';

	// Form
	echo	'<form name="formlist" action="" method="post" style="margin: auto; width: 750px; ">';

	foreach(plugins_list() as $k => $v)
	{
		if($v['builtin'] == 1)
		{
			echo '<div id="dashboard_recent_drafts" class="postbox">';
			echo '<h3 class="hndle"><span>' . $v['Name'] . '</span></h3>';
			echo '<div class="inside">';

			echo 'Enabled?: <br />';
			echo keypic_get_select_enabled($k.'_enabled', $keypic_details[$k]['enabled']) . '<br />';

			if($keypic_details[$k]['enabled'] == 1)
			{
				$WeighthEight = isset($keypic_details[$k]['WeighthEight']) ? $keypic_details[$k]['WeighthEight'] : '';
				$RequestType = isset($keypic_details[$k]['RequestType']) ? $keypic_details[$k]['RequestType'] : '';

				echo 'WeightHeight: <br />';
				echo keypic_get_select_weightheight($k.'_weightheight', $WeighthEight) . '<br />';
	
				echo 'RequestType: <br />';
				echo keypic_get_select_requesttype($k.'_requesttype', $RequestType) . '<br />';
	
				echo '<p>' . __('content preview') . '<br />';
				echo Keypic::getIt($RequestType, $WeighthEight) . '</p>';
			}

			echo '</div>';
			echo '</div>';
		}
		else
		{
			echo '<div id="dashboard_recent_drafts" class="postbox">';
			echo '<h3 class="hndle"><span>' . $v['Name'] . '</span></h3>';
			echo '<div class="inside">';

			echo 'Enabled?: <br />';
			echo keypic_get_select_enabled($k.'_enabled', $keypic_details[$k]['enabled']) . '<br />';

			if($keypic_details[$k]['enabled'] == 1)
			{
				echo $v['Name'] . ' is enabled and ready to working with Keypic <br />';
				echo '<br /><br />';
				echo '<img src="/wp-content/plugins/keypic/screenshot-contact-form-7-1.png" /> <br />';
				echo '<img src="/wp-content/plugins/keypic/screenshot-contact-form-7-2.png" /> <br />';
				echo '<img src="/wp-content/plugins/keypic/screenshot-contact-form-7-3.png" /> <br />';

			}

			echo '</div>';
			echo '</div>';
		}
	}


	echo '<p class="submit"><input type="submit" name="input_submit" value="' . __('Update options &raquo;') . '" /></p>';
	echo '<input type="hidden" name="submit2" value="submit2" />';
	echo	'</form>';
}


?>