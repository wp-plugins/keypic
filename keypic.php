<?php
/*
Plugin Name: Keypic
Plugin URI: http://keypic.com/
Description: Keypic is quite possibly the best way in the world to <strong>protect your blog from comment and trackback spam</strong>.
Version: 0.2.1
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
define('KEYPIC_VERSION', '0.2.1');
define('KEYPIC_PLUGIN_URL', plugin_dir_url( __FILE__ ));
define('KEYPIC_SPAM_PERCENTAGE', 70);
define('KEYPIC_HOST', 'ws.keypic.com');
define('KEYPIC_WEIGHTHEIGHT', '88x31');


// Make sure we don't expose any info if called directly
if(!function_exists('add_action')){echo "Hi there!  I'm just a plugin, not much I can do when called directly."; exit;}

if(is_admin()){require_once dirname( __FILE__ ) . '/admin.php';}



/*
function getiFrame($WeightHeight = null)
{
	if($WeightHeight)
	{
		$xy = explode('x', $WeightHeight);
		$x = (int)$xy[0];
		$y = (int)$xy[1];
	}
	else{$x=88; $y=31;}

	$url = 'http://' . self::$host . '/?iFrame=true&Token=' . self::$Token . '&Adv=' . self::$Token;


	return <<<EOT
<iframe
src="$url"
width="$x"
height="$y"
frameborder="0"
style="border: 1px solid #000000; background-color: #ffffff;"
marginwidth="0"
marginheight="0"
vspace="0"
hspace="0"
allowtransparency="true"
scrolling="no"><p>Your browser does not support iframes.</p></iframe>
EOT;
}
*/

function getToken($ClientEmailAddress = '', $ClientUsername = '', $ClientMessage = '', $ClientFingerprint = '', $Quantity = 1)
{
	global $Token, $FormID, $ResponseType;

	if($Token)
	{
		return $Token;
	}
	else
	{
		$fields['FormID'] = $FormID;
		$fields['RequestType'] = 'RequestNewToken'; // 001
		$fields['ResponseType'] = '2';
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
	global $Token, $FormID, $ResponseType, $spam;
	$Token = $_POST['Token'];
	$fields['Token'] = $Token;
	$fields['FormID'] = $FormID;
	$fields['RequestType'] = 'RequestValidation'; // 002
	$fields['ResponseType'] = '2';
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
add_action('init', 'keypic_init');

function keypic_init()
{
	global $FormID, $Token, $ResponseType;

	$FormID = get_option('FormID');
	$Token = '';

	if(!function_exists('json_decode')){$ResponseType = '2';}
	else{$ResponseType = '4';}
}



//*********************************************************************************************
//* Register form
//*********************************************************************************************
add_action('register_form','keypic_register_form');
add_action('register_post','keypic_register_post');


function keypic_register_form()
{
	global $Token;
	$Token = getToken();

	$response = '
<p>
 <label style="display: block; margin-bottom: 5px;">
  <input type="hidden" name="Token" value="'.$Token.'" />
  <a target="_blank" href="http://'.KEYPIC_HOST.'/?Click='.$Token.'"><img src="http://'.KEYPIC_HOST.'/?Image=true&amp;Token='.$Token.'&amp;WeightHeight='.KEYPIC_WEIGHTHEIGHT.'" alt="" style="border:0;" /></a>
 </label>
</p>
';
	echo $response;
}

function keypic_register_post()
{
	global $Token, $FormID, $spam;
	$spam = isSpam($_POST['user_email'], $_POST['user_login'], $ClientMessage = '', $ClientFingerprint = '');

	// if spam % is more than x% don't accept it and give back an error
	if($spam >= KEYPIC_SPAM_PERCENTAGE)
	add_filter('registration_errors', 'keypic_registration_errors', 10, 3);
}

function keypic_registration_errors($errors)
{
	global $spam;
	$errors->add('keypic_error', __('This request has ') . $spam . __('% of spam'));
	return $errors;
}


//*********************************************************************************************
//* Login form
//*********************************************************************************************

add_action('login_form','keypic_login_form');
add_filter( 'authenticate', 'keypic_login_post', 10);

function keypic_login_form()
{
	global $Token;
	$Token = getToken();

	$response = '
<p>
 <label style="display: block; margin-bottom: 5px;">
  <input type="hidden" name="Token" value="'.$Token.'" />
  <a target="_blank" href="http://'.KEYPIC_HOST.'/?Click='.$Token.'"><img src="http://'.KEYPIC_HOST.'/?Image=true&amp;Token='.$Token.'&amp;WeightHeight='.KEYPIC_WEIGHTHEIGHT.'" alt="" style="border:0;" /></a>
 </label>
</p>
';
	echo $response;
}

function keypic_login_post()
{
	global $Token, $FormID, $spam;
	$spam = isSpam($_POST['user_email'], $_POST['user_login'], $ClientMessage = '', $ClientFingerprint = '');

	// if spam % is more than KEYPIC_SPAM_PERCENTAGE don't accept it and give back an error
	if($spam >= KEYPIC_SPAM_PERCENTAGE)
	{
		remove_action('authenticate', 'wp_authenticate_username_password', 20);
		add_filter('shake_error_codes', 'keypic_login_error_shake');
		return new WP_Error('denied', '<strong>SPAM</strong>: ' . __('This request has ') . $spam . __('% of spam'));
	}
}

function keypic_login_error_shake($shake_codes)
{
	$shake_codes[] = 'denied';
	return $shake_codes;
}

//*********************************************************************************************
//* lost password form
//*********************************************************************************************

add_action('lostpassword_form','keypic_lostpassword_form');
//add_action('lostpassword_post','');

function keypic_lostpassword_form()
{
	global $Token;
	$Token = getToken();

	$response = '
<p>
 <label style="display: block; margin-bottom: 5px;">
  <input type="hidden" name="Token" value="'.$Token.'" />
  <a target="_blank" href="http://'.KEYPIC_HOST.'/?Click='.$Token.'"><img src="http://'.KEYPIC_HOST.'/?Image=true&amp;Token='.$Token.'&amp;WeightHeight='.KEYPIC_WEIGHTHEIGHT.'" alt="" style="border:0;" /></a>
 </label>
</p>
';
	echo $response;
}

//*********************************************************************************************
//* Comment form
//*********************************************************************************************

add_action('comment_form','keypic_comment_form');
add_action( 'wp_insert_comment', 'keypic_comment_post', 10, 2 );

function keypic_comment_form()
{
	global $Token;
	$Token = getToken();

	$response = '
<p>
 <label style="display: block; margin-bottom: 5px;">
  <input type="hidden" name="Token" value="'.$Token.'" />
  <a target="_blank" href="http://'.KEYPIC_HOST.'/?Click='.$Token.'"><img src="http://'.KEYPIC_HOST.'/?Image=true&amp;Token='.$Token.'&amp;WeightHeight='.KEYPIC_WEIGHTHEIGHT.'" alt="" style="border:0;" /></a>
 </label>
</p>
';
	echo $response;
}

function keypic_comment_post($id, $comment)
{
	global $Token, $FormID, $spam;
	$spam = isSpam($comment->comment_author_email, $comment->comment_author, $comment->comment_content, $ClientFingerprint = '');

	// if spam % is more than KEYPIC_SPAM_PERCENTAGE don't accept it and give back an error
	if($spam >= KEYPIC_SPAM_PERCENTAGE)
	{
		wp_spam_comment($comment->comment_ID);
	}
}

class Socket
{
	private $host;
	private $port;
	private $request;
	private $response;
	private $responseLength;
	private $errorNumber;
	private $errorString;

	public function __construct($host, $port, $request, $responseLength = 1024)
	{
		$this->host = $host;
		$this->port = $port;
		$this->request = $request;
		$this->responseLength = $responseLength;
		$this->errorNumber = 0;
		$this->errorString = '';
	}

	public function Send()
	{
		$this->response = '';

		$fs = fsockopen($this->host, $this->port, $this->errorNumber, $this->errorString, 3);

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
