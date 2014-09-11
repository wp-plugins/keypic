<?php
add_action('admin_menu', 'keypic_config_page', 8);

add_action('admin_init', 'keypic_admin_init');

$ms = array();

$messages = array(
	'key_empty' => array('color' => 'aa0', 'text' => sprintf(__('Your FormID is empty, This plugin does not work without FormID, please (<a href="%s" target="_blank" style="color:#fff">Get your FormID.</a>)'), 'http://keypic.com/?action=register')),
	'key_valid' => array('color' => '4AB915', 'text' => __('This FormID is valid.')),
	'key_not_valid' => array('color' => 'aaa', 'text' => __('This FormID is NOT valid.')),
);

$plugins = array(
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

    display_notice();
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
    $keypic_comments = get_option('keypic_comments');

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
			$WidthHeight = isset($_POST[$k.'_widthheight']) ? $_POST[$k.'_widthheight'] : '';
			$enabled = isset($_POST[$k.'_enabled']) ? $_POST[$k.'_enabled'] : '';
			$keypic_details[$k] = array('RequestType' => $RequestType, 'WidthHeight' => $WidthHeight, 'enabled' => $enabled);
		}

		update_option('keypic_details', $keypic_details);
	}
	else
	{
		if($FormID == ''){$ms[] = 'key_empty';}
	}

	// Form
	echo '<h2>' . __('Keypic Configuration') . ' - Version ' . KEYPIC_VERSION .'</h2>';

	echo '<div id="dashboard_recent_drafts" class="postbox">';
	echo '<h3 class="hndle"><span>' . __('Keypic FormID Management') . '</span></h3>';
	    echo '<div class="inside">';


	echo	'<form name="formid" action="" method="post" style="margin: auto; width: 100%; ">';

	echo '<p>' . __('For many people, <a href="http://keypic.com/" target="_blank">Keypic</a> is quite possibly the best way in the world to protect your blog from comment and trackback spam. It keeps your site protected from spam even while you sleep. To get started: <br /> 1) Click the "Activate" link to the left of this description, <br /> 2) <a href="http://keypic.com/?action=register" target="_blank">Sign up for a FormID</a>, and <br /> 3) Go to your Keypic configuration page, and save FormID key.') . '</p>';
	echo '<h3><label for="key">' . __('Keypic FormID') . '</label></h3>';

	foreach($ms as $m):
		echo '<p style="padding: .5em; background-color: #' . $messages[$m]['color'] . '; color: #fff; font-weight: bold;">' . $messages[$m]['text'] . '</p>';
	endforeach;

	echo '<p><input id="key" name="formid" type="text" size="32" maxlength="32" value="' . $FormID . '" style="font-family: \'Courier New\', Courier, mono; font-size: 1.5em;" /> (<a href="http://keypic.com/?action=register" target="_blank">' . __('get registered') . '</a>) or if you are just logged in <a href="http://keypic.com/?action=forms" target="_blank">' . __('Create a new FormID') . '</a></p>';

	echo '<p class="submit"><input type="submit" name="input_submit" value="' . __('Update FormID &raquo;') . '" /></p>';
	echo '<input type="hidden" name="submit1" value="submit1" />';
	echo	'</form>';



	    echo '</div>';
	echo '</div>';


	// Form
	echo	'<form name="formlist" action="" method="post" style="margin: auto; width: 100%; ">';

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
				$WidthHeight = isset($keypic_details[$k]['WidthHeight']) ? $keypic_details[$k]['WidthHeight'] : '';
				$RequestType = isset($keypic_details[$k]['RequestType']) ? $keypic_details[$k]['RequestType'] : '';

				echo 'Width Height: <br />';
				echo keypic_get_select_widthheight($k.'_widthheight', $WidthHeight) . '<br />';
	
				echo 'RequestType: <br />';
				echo keypic_get_select_requesttype($k.'_requesttype', $RequestType) . '<br />';
	
//				echo '<p>' . __('content preview') . '<br />';
//				if($WidthHeight == '1x1'){echo 'Transparenti Pixel Active!';} // Little Hack :)
//				else{echo Keypic::getIt($RequestType, $WidthHeight) . '</p>';}
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
				echo $v['Name'] . ' is enabled and ready to work with Keypic <br />';
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

function display_notice()
{
    global $hook_suffix;

//	if( $hook_suffix == 'plugins.php' && !Keypic::getFormID() )
	if( !Keypic::getFormID() )
	{
        display_api_key_warning();
    }

}

function display_api_key_warning()
{
?><div class="updated" style="padding: 0; margin: 0; border: none; background: none;">
	<style type="text/css">
.keypic_activate{min-width:825px;border:1px solid #4F800D;padding:5px;margin:15px 0;background:#83AF24;background-image:-webkit-gradient(linear,0% 0,80% 100%,from(#83AF24),to(#4F800D));background-image:-moz-linear-gradient(80% 100% 120deg,#4F800D,#83AF24);-moz-border-radius:3px;border-radius:3px;-webkit-border-radius:3px;position:relative;overflow:hidden}.keypic_activate .aa_a{position:absolute;right:10px;font-size:80px;color:#769F33;font-family:Georgia, "Times New Roman", Times, serif;z-index:1}.keypic_activate .aa_button{font-weight:bold;border:1px solid #029DD6;border-top:1px solid #06B9FD;font-size:15px;text-align:center;padding:9px 0 8px 0;color:#FFF;background:#029DD6;background-image:-webkit-gradient(linear,0% 0,0% 100%,from(#029DD6),to(#0079B1));background-image:-moz-linear-gradient(0% 100% 90deg,#0079B1,#029DD6);-moz-border-radius:2px;border-radius:2px;-webkit-border-radius:2px}.keypic_activate .aa_button:hover{text-decoration:none !important;border:1px solid #029DD6;border-bottom:1px solid #00A8EF;font-size:15px;text-align:center;padding:9px 0 8px 0;color:#F0F8FB;background:#0079B1;background-image:-webkit-gradient(linear,0% 0,0% 100%,from(#0079B1),to(#0092BF));background-image:-moz-linear-gradient(0% 100% 90deg,#0092BF,#0079B1);-moz-border-radius:2px;border-radius:2px;-webkit-border-radius:2px}.keypic_activate .aa_button_border{border:1px solid #006699;-moz-border-radius:2px;border-radius:2px;-webkit-border-radius:2px;background:#029DD6;background-image:-webkit-gradient(linear,0% 0,0% 100%,from(#029DD6),to(#0079B1));background-image:-moz-linear-gradient(0% 100% 90deg,#0079B1,#029DD6)}.keypic_activate .aa_button_container{cursor:pointer;display:inline-block;background:#DEF1B8;padding:5px;-moz-border-radius:2px;border-radius:2px;-webkit-border-radius:2px;width:266px}.keypic_activate .aa_description{position:absolute;top:22px;left:285px;margin-left:25px;color:#E5F2B1;font-size:15px;z-index:1000}.keypic_activate .aa_description strong{color:#FFF;font-weight:normal}
	</style>
	<form name="keypic_activate" action="plugins.php?page=keypic-key-config" method="POST">
		<div class="keypic_activate">
			<div class="aa_a">Keypic</div>
			<div class="aa_button_container" onclick="document.keypic_activate.submit();">
				<div class="aa_button_border">
					<div class="aa_button"><?php esc_html_e('Activate your Keypic account', 'keypic');?></div>
				</div>
			</div>
			<div class="aa_description"><?php _e('<strong>Almost done</strong> - activate your account and say goodbye to comment spam', 'keypic');?></div>
		</div>
	</form>
</div><?php
}


?>