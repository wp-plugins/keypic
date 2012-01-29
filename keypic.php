<?php
/*
Plugin Name: Keypic
Plugin URI: http://keypic.com/
Description: Keypic is quite possibly the best way in the world to <strong>protect your blog from comment and trackback spam</strong>.
Version: 0.6.0
Author: Keypic
Author URI: http://keypic.com
License: GPLv2 or later
*/
/*  Copyright 2010-2011  Keypic LLC  (email : info@keypic.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

define('KEYPIC_PLUGIN_NAME', 'Keypic for Wordpress');
define('KEYPIC_VERSION', '0.6.0');
define('KEYPIC_PLUGIN_URL', plugin_dir_url( __FILE__ ));
define('KEYPIC_SPAM_PERCENTAGE', 70);
define('KEYPIC_HOST', 'ws.keypic.com'); // ws.keypic.com
define('KEYPIC_WEIGHTHEIGHT', ''); // default '88x31'

// Make sure we don't expose any info if called directly
if(!function_exists('add_action')){echo "Hi there!  I'm just a plugin, not much I can do when called directly."; exit;}

if(is_admin()){require_once dirname( __FILE__ ) . '/admin.php';}

function getToken($ClientEmailAddress = '', $ClientUsername = '', $ClientMessage = '', $ClientFingerprint = '', $Quantity = 1)
{
	global $Token, $FormID;

	if($Token)
	{
		return $Token;
	}
	else
	{
		$fields['FormID'] = $FormID;
		$fields['RequestType'] = 'RequestNewToken';
		$fields['ResponseType'] = '2';
		$fields['ServerName'] = $_SERVER['SERVER_NAME'];
		$fields['Quantity'] = $Quantity;
		$fields['ClientIP'] = $_SERVER['REMOTE_ADDR'];
		$fields['ClientUserAgent'] = $_SERVER['HTTP_USER_AGENT'];
		$fields['ClientAccept'] = $_SERVER['HTTP_ACCEPT'];
		$fields['ClientAcceptEncoding'] = $_SERVER['HTTP_ACCEPT_ENCODING'];
		$fields['ClientAcceptLanguage'] = $_SERVER['HTTP_ACCEPT_LANGUAGE'];
		$fields['ClientAcceptCharset'] = $_SERVER['HTTP_ACCEPT_CHARSET'];
		$fields['ClientHttpReferer'] = $_SERVER['HTTP_REFERER'];
		$fields['ClientUsername'] = $ClientUsername;
		$fields['ClientEmailAddress'] = $ClientEmailAddress;
		$fields['ClientMessage'] = $ClientMessage;
		$fields['ClientFingerprint'] = $ClientFingerprint;

		$request = sendRequest($fields, KEYPIC_HOST);
		$response = json_decode($request, true);

		if($response['status'] == 'new_token')
		{
			$Token = $response['Token'];
			return  $response['Token'];
		}
	}
}

function isSpam($ClientEmailAddress = '', $ClientUsername = '', $ClientMessage = '', $ClientFingerprint = '')
{
	global $Token, $FormID, $spam;

	// TODO: manage it
	//if($Token == ''){return 'error';}

	$Token = $_POST['Token'];
	$fields['Token'] = $Token;
	$fields['FormID'] = $FormID;
	$fields['RequestType'] = 'RequestValidation';
	$fields['ResponseType'] = '2';
	$fields['ServerName'] = $_SERVER['SERVER_NAME'];
	$fields['ClientIP'] = $_SERVER['REMOTE_ADDR'];
	$fields['ClientUserAgent'] = $_SERVER['HTTP_USER_AGENT'];
	$fields['ClientAccept'] = $_SERVER['HTTP_ACCEPT'];
	$fields['ClientAcceptEncoding'] = $_SERVER['HTTP_ACCEPT_ENCODING'];
	$fields['ClientAcceptLanguage'] = $_SERVER['HTTP_ACCEPT_LANGUAGE'];
	$fields['ClientAcceptCharset'] = $_SERVER['HTTP_ACCEPT_CHARSET'];
	$fields['ClientHttpReferer'] = $_SERVER['HTTP_REFERER'];
	$fields['ClientUsername'] = $ClientUsername;
	$fields['ClientEmailAddress'] = $ClientEmailAddress;
	$fields['ClientMessage'] = $ClientMessage;
	$fields['ClientFingerprint'] = $ClientFingerprint;

	$request = sendRequest($fields, KEYPIC_HOST);
	$response = json_decode($request, true);

	if($response['status'] == 'response'){$spam = $response['spam']; return $response['spam'];}
	else if($response['status'] == 'error'){return $response['error'];}
}

function reportSpam($FormID, $Token)
{
	if($Token == ''){return 'error';}
	if($FormID == ''){return 'error';}

	$fields['Token'] = $Token;
	$fields['FormID'] = $FormID;
	$fields['RequestType'] = 'ReportSpam';
	$fields['ResponseType'] = '2';

	$request = sendRequest($fields, KEYPIC_HOST);
	$response = json_decode($request, true);
	return $response;
}

// makes a request to the Keypic Web Service
function sendRequest($fileds, $host, $path = '/', $port = 80)
{
	global $wp_version;

	// boundary generation
	srand((double)microtime()*1000000);
	$boundary = "---------------------".substr(md5(rand(0,32000)),0,10);

	// Build the header
	$header = "POST " . $path . " HTTP/1.0\r\n";
	$header .= "Host: " . $host . "\r\n";
	$header .= "Content-type: multipart/form-data, boundary=$boundary\r\n";
	$header .= "User-Agent: WordPress/{$wp_version} | Keypic/" . constant( 'KEYPIC_VERSION' ) . "\r\n";

	$data = '';
	// attach post vars
	foreach($fileds AS $index => $value)
	{
		$data .="--$boundary\r\n";
		$data .= "Content-Disposition: form-data; name=\"$index\"\r\n";
		$data .= "\r\n$value\r\n";
		$data .="--$boundary\r\n";
	}

	$header .= "Content-length: " . strlen($data) . "\r\n\r\n";

	$socket = new Socket($host, $port, $header.$data);
	$socket->send();
	$return = explode("\r\n\r\n", $socket->getResponse(), 2);
	return $return[1];
}

//*********************************************************************************************
//* init
//*********************************************************************************************

function keypic_init()
{
	global $keypic_details, $FormID, $Token;

	$FormID = get_option('FormID');
	if($FormID)
	{
		$keypic_details = array('FormID' => $FormID, 'KEYPIC_VERSION' => KEYPIC_VERSION);
		update_option('keypic_details', $keypic_details);
		delete_option('FormID');
	}

	$keypic_details = get_option('keypic_details');
	$FormID = $keypic_details['FormID'];
	$Token = '';
}
add_action('init', 'keypic_init');

//*********************************************************************************************
//* Register form
//*********************************************************************************************

function keypic_register_form()
{
	global $Token, $keypic_details;
	$Token = getToken();

	$keypic_details_register = $keypic_details['register'];

	$response = '
<p>
 <label style="display: block; margin-bottom: 5px;">
  <input type="hidden" name="Token" value="'.$Token.'" />
  '.keypic_get_it($keypic_details_register['RequestType'], $keypic_details_register['WeighthEight']).'
 </label>
</p>
';

	echo $response;
}
add_action('register_form','keypic_register_form');

function keypic_register_post($login, $email, $errors)
{
	global $Token, $spam;

	$spam = isSpam($_POST['user_email'], $_POST['user_login'], $ClientMessage = '', $ClientFingerprint = '');

	if(!is_numeric($spam) || $spam > KEYPIC_SPAM_PERCENTAGE)
	{
		$errors->add('empty_token', "<strong>ERROR</strong>: We are sorry, your Keypic token is not valid");
	}

}
add_action('register_post','keypic_register_post', 10, 3);


function keypic_user_register($user_id)
{
	global $Token, $spam;

	$keypic_users = get_option('keypic_users');
	$keypic_users = keypic_remove_old_tokens($keypic_users);
	$keypic_users[$user_id] = array('token' => $Token, 'ts' => time(), 'spam' => $spam);
	update_option('keypic_users', $keypic_users);
}
add_action( 'user_register', 'keypic_user_register', 10, 1);


function keypic_remove_old_tokens($old_tokens)
{
	$new_tokens = array();
	$three_days_ago = (time() - (60*60*24*3));
	foreach($old_tokens as $k => $v)
	{
		if($v['ts'] > $three_days_ago){$new_tokens[$k] = $v;}
	}
	return $new_tokens;
}

//*********************************************************************************************
//* Login form
//*********************************************************************************************

function keypic_login_form()
{
	global $Token, $keypic_details;
	$Token = getToken();

	$keypic_details_login = $keypic_details['login'];

	$response = '
<p>
 <label style="display: block; margin-bottom: 5px;">
  <input type="hidden" name="Token" value="'.$Token.'" />
  '.keypic_get_it($keypic_details_login['RequestType'], $keypic_details_login['WeighthEight']).'
 </label>
</p>
';
	echo $response;
}
add_action('login_form','keypic_login_form');

function keypic_login_post()
{
	global $Token, $FormID, $spam;

	if($_SERVER['REQUEST_METHOD'] == 'POST')
	{
		$spam = isSpam($_POST['user_email'], $_POST['user_login'], $ClientMessage = '', $ClientFingerprint = '');

		// if spam % is more than KEYPIC_SPAM_PERCENTAGE don't accept it and give back an error
		if(!is_numeric($spam) || $spam > KEYPIC_SPAM_PERCENTAGE)
		{
			remove_action('authenticate', 'wp_authenticate_username_password', 20);
echo "spam: $spam";
			if(is_numeric($spam)){$error = sprintf(__('This request has %s&#37; of spam'), $spam);}
			else{$error = __('We are sorry, your Keypic token is not valid');}

			add_filter('shake_error_codes', 'keypic_login_error_shake');
			return new WP_Error('denied', '<strong>SPAM</strong>: ' . $error);
		}
	}
}
add_filter( 'authenticate', 'keypic_login_post', 10);


function keypic_login_error_shake($shake_codes)
{
	$shake_codes[] = 'denied';
	return $shake_codes;
}

//*********************************************************************************************
//* lost password form
//*********************************************************************************************

function keypic_lostpassword_form()
{
	global $Token, $keypic_details;
	$Token = getToken();

	$keypic_details_lostpassword = $keypic_details['lostpassword'];

	$response = '
<p>
 <label style="display: block; margin-bottom: 5px;">
  <input type="hidden" name="Token" value="'.$Token.'" />
  '.keypic_get_it($keypic_details_lostpassword['RequestType'], $keypic_details_lostpassword['WeighthEight']).'
 </label>
</p>
';
	echo $response;
}
add_action('lostpassword_form', 'keypic_lostpassword_form');

function keypic_lostpassword_post()
{
	global $Token, $keypic_details;

}
add_action('lostpassword_post','keypic_lostpassword_post');

//*********************************************************************************************
//* Comment form
//*********************************************************************************************

function keypic_comment_form()
{
	global $Token, $keypic_details;
	$Token = getToken();

	$keypic_details_comments = $keypic_details['comments'];

	$response = '
<p>
 <label style="display: block; margin-bottom: 5px;">
  <input type="hidden" name="Token" value="'.$Token.'" />
  '.keypic_get_it($keypic_details_comments['RequestType'], $keypic_details_comments['WeighthEight']).'
 </label>
</p>
';
	echo $response;
}
add_action('comment_form','keypic_comment_form');

function keypic_comment_post($id, $comment)
{
	global $Token, $FormID, $spam;
	$spam = isSpam($comment->comment_author_email, $comment->comment_author, $comment->comment_content, $ClientFingerprint = '');

	$keypic_comments = get_option('keypic_comments');
	$keypic_comments = keypic_remove_old_tokens($keypic_comments);
	$keypic_comments[$comment->comment_ID] = array('token' => $Token, 'ts' => time(), 'spam' => $spam);
	update_option('keypic_comments', $keypic_comments);

	// if spam % is more than KEYPIC_SPAM_PERCENTAGE don't accept it and put it in spam status
	if(!is_numeric($spam) || $spam > KEYPIC_SPAM_PERCENTAGE)
	{
		wp_spam_comment($comment->comment_ID);
	}
}
add_action( 'wp_insert_comment', 'keypic_comment_post', 10, 2 );

function keypic_manage_edit_comments_columns($columns)
{
	global $keypic_comments;
	$keypic_comments = get_option('keypic_comments');
	$columns['spam_status'] = __('Keypic SPAM Status');
	return $columns;
}
add_filter('manage_edit-comments_columns', 'keypic_manage_edit_comments_columns');

function keypic_manage_comments_custom_column($column, $id)
{
	global $FormID, $keypic_comments;
	$comments = $keypic_comments[$id];

	if('spam_status' == $column)
	{
		//if($FormID == ''){}

		if($comments['spam'])
		{
			if($comments['spam'] > 70){echo "<b><a href=\"admin.php?action=keypic_report_spam_and_delete_comment&id=$id\" style=\"color:red;\" onclick=\"return confirm('" . __('Are you sure you want to delete this user?') . "')\">" . sprintf(__('Report SPAM and Delete (spam %s&#37;)'), $comments['spam']) . "</a></b>";}
			else{echo "<a href=\"admin.php?action=keypic_report_spam_and_delete_comment&id=$id\" onclick=\"return confirm('" . __('Are you sure you want to delete this user?') . "')\">" . sprintf(__('Report SPAM and Delete (spam %s&#37;)'), $comments['spam']) . "</a>";}
		}
	}
}
add_filter('manage_comments_custom_column', 'keypic_manage_comments_custom_column', 10, 2);

function keypic_manage_users_columns($columns)
{
	global $keypic_users;
	$keypic_users = get_option('keypic_users');
	$columns['spam_status'] = __('Keypic SPAM Status');
 	return $columns;

}
add_filter('manage_users_columns', 'keypic_manage_users_columns');

function keypic_manage_users_custom_column($empty='', $column_name, $id)
{
	global $keypic_users;
	$users = $keypic_users[$id];


	if($users['spam'])
	{
		if($users['spam'] > 70){$return .= "<b><a href=\"admin.php?action=keypic_report_spam_and_delete_user&id=$id\" style=\"color:red;\" onclick=\"return confirm('" . __('Are you sure you want to delete this user?') . "')\">" . sprintf(__('Report SPAM and Delete (spam %s&#37;)'), $users['spam']) . "</a></b>";}
		else{$return .= "<a href=\"admin.php?action=keypic_report_spam_and_delete_user&id=$id\" onclick=\"return confirm('" . __('Are you sure you want to delete this user?') . "')\">" . sprintf(__('Report SPAM and Delete (spam %s&#37;)'), $users['spam']) . "</a>";}

	}

	return $return;
}
add_filter('manage_users_custom_column', 'keypic_manage_users_custom_column', 10, 3);


function keypic_get_it($RequestType = 'getImage', $WeightHeight = '88x31')
{
	global $Token, $spam, $keypic_details;

	if($RequestType == 'getiFrame')
	{
		if($WeightHeight)
		{
			$xy = explode('x', $WeightHeight);
			$x = (int)$xy[0];
			$y = (int)$xy[1];
		}
		else{$x=88; $y=31;}

		$url = 'http://'.KEYPIC_HOST.'/?RequestType=getiFrame&amp;WeightHeight='.$WeightHeight.'&amp;Token=' . $Token;

		return <<<EOT
	<iframe
	src="$url"
	width="$x"
	height="$y"
	frameborder="0"
	style="border: 0px solid #ffffff; background-color: #ffffff;"
	marginwidth="0"
	marginheight="0"
	vspace="0"
	hspace="0"
	allowtransparency="true"
	scrolling="no"><p>Your browser does not support iframes.</p></iframe>
EOT;

	}
	else // by default RequestType=getImage
	{
		return '<a target="_blank" href="http://'.KEYPIC_HOST.'/?RequestType=getClick&amp;Token='.$Token.'"><img src="http://'.KEYPIC_HOST.'/?RequestType=getImage&amp;Token='.$Token.'&amp;WeightHeight='.$WeightHeight.'" alt="Protected by Keypic" style="border:0;" /></a>';
	}
}

function keypic_get_select_weightheight($select_name='', $select_value = '')
{

	$options = array(
	'' => '',
	'1x1' => 'Lead square transparent 1x1 pixel',
	'336x280' => 'Large rectangle (336 x 280)',
	'300x250' => 'Medium Rectangle (300 x 250)',
	'250x250' => 'Square Pop-Up (250 x 250)',
	'240x400' => 'Vertical Rectangle (240 x 400)',
	'180x150' => 'Rectangle (180 x 150)',
	'300x100' => '3:1 Rectangle (300 x 100)',
	'720x300' => 'Pop-under (720 x 300)',
	'392x72' => 'Banner w/Naw Bar (392 x 72)',
	'468x60' => 'Full Banner (468 x 60)',
	'234x60' => 'Half Banner (234 x 60)',
	'80x15' => 'Micro Button (80 x 15)',
	'88x31' => 'Micro Bar (88 x 31)',
	'120x90' => 'Button 1 (120 x 90)',
	'120x60' => 'Button 2 (120 x 60)',
	'120x240' => 'Vertical Banner (120 x 240)',
	'125x125' => 'Square Button (125 x 125)',
	'728x90' => 'Leaderboard (728 x 90)',
	'120x600' => 'Skyscraper (120 x 600)',
	'160x600' => 'Wide Skyscraper (160 x 600)',
	'300x600' => 'Half Page Ad (300 x 600)'
	);

	$return .= '<select name="'.$select_name.'">';
	foreach($options as $k => $v)
	{
		if($select_value == $k){$return .= '<option value="'.$k.'" selected="selected">'.$v.'</option>';}
		else{$return .= '<option value="'.$k.'">'.$v.'</option>';}
	}

	$return .= '</select>';

	return $return;
}

function keypic_get_select_requesttype($select_name='', $select_value = '')
{
	$options = array(
	'getImage' => 'getImage',
	'getiFrame' => 'getiFrame'
	);

	$return .= '<select name="'.$select_name.'">';
	foreach($options as $k => $v)
	{
		if($select_value == $k){$return .= '<option value="'.$k.'" selected="selected">'.$v.'</option>';}
		else{$return .= '<option value="'.$k.'">'.$v.'</option>';}
	}

	$return .= '</select>';

	return $return;
}

//*********************************************************************************************
//* Socket class
//*********************************************************************************************
class Socket
{
	private $host;
	private $port;
	private $request;
	private $response;
	private $responseLength;
	private $errorNumber;
	private $errorString;
	private $timeout;
	private $retry;

	public function __construct($host, $port, $request, $responseLength = 1024, $timeout = 3, $retry = 3)
	{
		$this->host = $host;
		$this->port = $port;
		$this->request = $request;
		$this->responseLength = $responseLength;
		$this->errorNumber = 0;
		$this->errorString = '';
		$this->timeout = $timeout;
		$this->retry = $retry;
	}

	public function Send()
	{
		$this->response = '';
		$r = 0;

		do
		{
			if($r >= $this->retry){return;}

			$fs = fsockopen($this->host, $this->port, $this->errorNumber, $this->errorString, $this->timeout);
			++$r;
		}
		while(!$fs);

		if($this->errorNumber != 0){throw new Exception('Error connecting to host: ' . $this->host . ' Error number: ' . $this->errorNumber . ' Error message: ' . $this->errorString);}

		if($fs !== false)
		{
			@fwrite($fs, $this->request);

			while(!feof($fs))
			{
				$this->response .= fgets($fs, $this->responseLength);
			}

			fclose($fs);
			
		}
	}

	public function getResponse(){return $this->response;}

	public function getErrorNumner(){return $this->errorNumber;}

	public function getErrorString(){return $this->errorString;}
}


?>