<?php
add_action( 'admin_menu', 'keypic_config_page', 8);

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

/*
function keypic_courtesy()
{
    if (function_exists('get_transient'))
    {
        require_once( ABSPATH . 'wp-admin/includes/plugin-install.php' );

        // Before, try to access the data, check the cache.
        if (false === ($api = get_transient('keypic_info')))
        {
            // The cache data doesn't exist or it's expired.

            $api = plugins_api('plugin_information', array('slug' => 'keypic' ));
            if ( !is_wp_error($api) )
            {
                // cache isn't up to date, write this fresh information to it now to avoid the query for xx time.
                $myexpire = 60 * 15; // Cache data for 15 minutes
                set_transient('keypic_info', $api, $myexpire);
            }
        }


        if ( !is_wp_error($api) )
        {
	        $plugins_allowedtags = array('a' => array('href' => array(), 'title' => array(), 'target' => array()),
								        'abbr' => array('title' => array()), 'acronym' => array('title' => array()),
								        'code' => array(), 'pre' => array(), 'em' => array(), 'strong' => array(),
								        'div' => array(), 'p' => array(), 'ul' => array(), 'ol' => array(), 'li' => array(),
								        'h1' => array(), 'h2' => array(), 'h3' => array(), 'h4' => array(), 'h5' => array(), 'h6' => array(),
								        'img' => array('src' => array(), 'class' => array(), 'alt' => array()));

	        //Sanitize HTML
	        foreach ( (array)$api->sections as $section_name => $content )
		        $api->sections[$section_name] = wp_kses($content, $plugins_allowedtags);

	        foreach ( array('version', 'author', 'requires', 'tested', 'homepage', 'downloaded', 'slug') as $key )
		        $api->$key = wp_kses($api->$key, $plugins_allowedtags);

            if ( ! empty($api->downloaded) )
            {
                $return .= sprintf(__('Downloaded %s times', 'keypic'),number_format_i18n($api->downloaded));
                $return .= '.';
            }
        }

		if ( ! empty($api->rating) )
		{
            $return .= <<< EOL
<style type="text/css">
div.si-star-holder { position: relative; height:19px; width:100px; font-size:19px;}
div.si-star {height: 100%; position:absolute; top:0px; left:0px; background-color: transparent; letter-spacing:1ex; border:none;}
.si-star1 {width:20%;} .si-star2 {width:40%;} .si-star3 {width:60%;} .si-star4 {width:80%;} .si-star5 {width:100%;}
.si-star.si-star-rating {background-color: #fc0;}
.si-star img{display:block; position:absolute; right:0px; border:none; text-decoration:none;}
div.si-star img {width:19px; height:19px; border-left:1px solid #fff; border-right:1px solid #fff;}
.si-notice{background-color:#ffffe0;border-color:#e6db55;border-width:1px;border-style:solid;padding:5px;margin:5px 5px 20px;-moz-border-radius:3px;-khtml-border-radius:3px;-webkit-border-radius:3px;border-radius:3px;}
.fscf_left {clear:left; float:left;}
.fscf_img {margin:0 10px 10px 0;}
.fscf_tip {text-align:left; display:none;color:#006B00;padding:5px;}
</style>
EOL;

    		$return .= "<div class=\"si-star-holder\" title=\"" . esc_attr(sprintf(__('(Average rating based on %s ratings)', 'keypic'),number_format_i18n($api->num_ratings))) . "\">";
	    	$return .= "<div class=\"si-star si-star-rating\" style=\"width: " . esc_attr($api->rating) . "px\"></div>";
		    $return .= "<div class=\"si-star si-star5\"><img src=\"" . WP_PLUGIN_URL . "/si-captcha-for-wordpress/star.png\" alt=\"5 stars\" /></div>";
    		$return .= "<div class=\"si-star si-star4\"><img src=\"" . WP_PLUGIN_URL . "/si-captcha-for-wordpress/star.png\" alt=\"4 stars\" /></div>";
	    	$return .= "<div class=\"si-star si-star3\"><img src=\"" . WP_PLUGIN_URL . "/si-captcha-for-wordpress/star.png\" alt=\"3 stars\" /></div>";
		    $return .= "<div class=\"si-star si-star2\"><img src=\"" . WP_PLUGIN_URL . "/si-captcha-for-wordpress/star.png\" alt=\"2 stars\" /></div>";
		    $return .= "<div class=\"si-star si-star1\"><img src=\"" . WP_PLUGIN_URL . "/si-captcha-for-wordpress/star.png\" alt=\"1 star\" /></div>";
		    $return .= "</div>";
		    $return .= "<small>" . sprintf(__('(Average rating based on %s ratings)', 'keypic'),number_format_i18n($api->num_ratings)) . "</small> <h2><a target=\"_blank\" href=\"http://wordpress.org/support/view/plugin-reviews/keypic\">" . __('Please support Keypic, rate it or write a review, thanks!') . "</a></h2>";
            $return .= "<br />";
		}

    }

    return $return;
}
*/

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
/*
	echo '<div id="dashboard_recent_drafts" class="postbox">';
	echo '<h3 class="hndle"><span>' . __('Guys, Social Matters :)') . '</span></h3>';
	    echo '<div class="inside">';
        echo keypic_courtesy();
	    echo '</div>';
	echo '</div>';
*/

	echo '<div id="dashboard_recent_drafts" class="postbox">';
	echo '<h3 class="hndle"><span>' . __('Keypic FormID Management') . '</span></h3>';
	    echo '<div class="inside">';


	echo	'<form name="formid" action="" method="post" style="margin: auto; width: 100%; ">';

	echo '<p>' . __('For many people, <a href="http://keypic.com/" target="_blank">Keypic</a> will greatly reduce or even completely eliminate the comment and trackback spam you get on your site. If one does happen to get through, simply mark it as "spam" on the moderation screen and Keypic will learn from the mistakes. If you don\'t have an API FormID yet, you can get one at <a href="http://keypic.com/?action=register" target="_blank">keypic.com</a>.') . '</p>';
	echo '<h3><label for="key">' . __('Keypic FormID') . '</label></h3>';

	foreach($ms as $m):
		echo '<p style="padding: .5em; background-color: #' . $messages[$m]['color'] . '; color: #fff; font-weight: bold;">' . $messages[$m]['text'] . '</p>';
	endforeach;

	echo '<p><input id="key" name="formid" type="text" size="32" maxlength="32" value="' . $FormID . '" style="font-family: \'Courier New\', Courier, mono; font-size: 1.5em;" /> (<a href="http://keypic.com/?action=register" target="_blank">' . __('get registered') . '</a>) or if you are just logged in <a href="http://keypic.com/?action=forms" target="_blank">' . __('Create a new FormID') . '</a></p>';
/*
	if(isset($invalid_key) && $invalid_key)
	{
		echo '<h3>' . __('Why might my key be invalid?') . '</h3>';
		echo '<p>' . __('This can mean one of two things, either you copied the key wrong or that the plugin is unable to reach the Keypic servers, which is most often caused by an issue with your web host around firewalls or similar.') . '</p>';
	}
*/
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
	
				echo '<p>' . __('content preview') . '<br />';
				if($WidthHeight == '1x1'){echo 'Transparenti Pixel Active!';} // Little Hack :)
				else{echo Keypic::getIt($RequestType, $WidthHeight) . '</p>';}
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


?>