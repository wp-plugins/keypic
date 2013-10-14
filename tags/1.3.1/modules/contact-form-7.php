<?php
/**
** A base module for [keypic]
**/

// Keypic Filter
add_filter( 'wpcf7_spam', 'keypic_wpcf7_spam' );

function keypic_wpcf7_spam($spam)
{

	$Token = isset($_POST['Token']) ? $_POST['Token'] : '';

	$email = isset($_POST['your-email']) ? $_POST['your-email'] : '';
	$name = isset($_POST['your-name']) ? $_POST['your-name'] : '';
	$message = isset($_POST['your-message']) ? $_POST['your-message'] : '';

	$spam = Keypic::isSpam($Token, $email, $name, $message, $ClientFingerprint = '');

	if(!is_numeric($spam) || $spam > Keypic::getSpamPercentage())
	{
		remove_action('authenticate', 'wp_authenticate_username_password', 20); // TODO: make it better...

		return true; // SMAP
	}

	return false; // NOT SMAP
}

// Shortcode handler
if(function_exists('wpcf7_add_shortcode'))
	wpcf7_add_shortcode( 'keypic', 'keypic_wpcf7_shortcode_handler', true );

function keypic_wpcf7_shortcode_handler( $tag )
{
	if ( ! is_array( $tag ) )
		return '';

	$options = (array) $tag['options'];

	$WeightHeight = '';
	$RequestType = '';

	foreach ( $options as $option )
	{
		$t = explode(':', $option);
		if($t[0] == 'WeightHeight'){$WeightHeight = $t[1];}
		if($t[0] == 'RequestType'){$RequestType = $t[1];}
	}

	$Token = isset($_POST['Token']) ? $_POST['Token'] : '';

	$html = '<input type="hidden" name="Token" value="' . Keypic::getToken($Token) . '">';
//	if($RequestType == 'getiFrame'){$html .= Keypic::getiFrame($WeightHeight);}
//	else{$html .= Keypic::getImage($WeightHeight);}
	$html .= Keypic::getIt($RequestType, $WeightHeight);

	return $html;

}

// Tag generator

add_action( 'admin_init', 'keypic_add_tag_generator', 45 );

function keypic_add_tag_generator()
{
	if(function_exists('wpcf7_add_tag_generator'))
	wpcf7_add_tag_generator( 'keypic', __( 'Keypic', 'wpcf7' ),
		'wpcf7-tg-pane-keypic', 'keypic_tg_pane_captcha' );
}


function keypic_tg_pane_captcha( &$contact_form )
{
?>
<div id="wpcf7-tg-pane-keypic" class="hidden">
<form action="">
<table>

<?php if ( ! class_exists( 'Keypic' ) ) : ?>
<tr><td colspan="2"><strong style="color: #e6255b"><?php echo esc_html( __( "Note: To use Keypic, you need Keypic plugin installed.", 'wpcf7' ) ); ?></strong><br /><a href="http://wordpress.org/extend/plugins/keypic/">http://wordpress.org/extend/plugins/keypic/</a></td></tr>
<?php endif; ?>

</table>

<table class="scope keypic">
<caption><?php echo esc_html( __( "Image settings", 'wpcf7' ) ); ?></caption>


<tr>
<td>

<p><?php echo esc_html( __( "If you see this page it means that you have Keypic plugin installed", 'wpcf7' ) ); ?></p><br />

<p><?php echo esc_html( __( "This is a preliminary version, in this version we support only the following field names: your-email, your-name , your-message", 'wpcf7' ) ); ?></p><br />

<p><?php echo esc_html( __( "Copy this code and paste it into the form left.", 'wpcf7' ) ); ?></p><br />

Get Image<br />
<b>[keypic keypic WeightHeight:336x280 RequestType:getImage]</b><br />
OR getScript<br />
<b>[keypic keypic WeightHeight:336x280 RequestType:getScript]</b><br />

</td>
</tr>
<tr>
<td>
Get Image<br />
<b>[keypic keypic WeightHeight:300x250 RequestType:getImage]</b><br />
OR getScript<br />
<b>[keypic keypic WeightHeight:300x250 RequestType:getScript]</b><br />

</td>
</tr>
<tr>
<td>
Get Image<br />
<b>[keypic keypic WeightHeight:250x250 RequestType:getImage]</b><br />
OR getScript<br />
<b>[keypic keypic WeightHeight:250x250 RequestType:getScript]</b><br />

</td>
</tr>
<tr>
<td>
Get Image<br />
<b>[keypic keypic WeightHeight:720x300 RequestType:getImage]</b><br />
OR getScript<br />
<b>[keypic keypic WeightHeight:720x300 RequestType:getScript]</b><br />

</td>
</tr>
<tr>
<td>
Get Image<br />
<b>[keypic keypic WeightHeight:468x60 RequestType:getImage]</b><br />
OR getScript<br />
<b>[keypic keypic WeightHeight:468x60 RequestType:getScript]</b><br />

</td>
</tr>
<tr>
<td>
Get Image<br />
<b>[keypic keypic WeightHeight:234x60 RequestType:getImage]</b><br />
OR getScript<br />
<b>[keypic keypic WeightHeight:234x60 RequestType:getScript]</b><br />

</td>
</tr>
<tr>
<td>
Get Image<br />
<b>[keypic keypic WeightHeight:125x125 RequestType:getImage]</b><br />
OR getScript<br />
<b>[keypic keypic WeightHeight:125x125 RequestType:getScript]</b><br />

</td>
</tr>
<tr>
<td>
Get Image<br />
<b>[keypic keypic WeightHeight:728x90 RequestType:getImage]</b><br />
OR getScript<br />
<b>[keypic keypic WeightHeight:728x90 RequestType:getScript]</b><br />

</td>
</tr>
<tr>
<td>
Get Image<br />
<b>[keypic keypic WeightHeight:120x600 RequestType:getImage]</b><br />
OR getScript<br />
<b>[keypic keypic WeightHeight:120x600 RequestType:getScript]</b><br />

</td>
</tr>
<tr>
<td>
Get Image<br />
<b>[keypic keypic WeightHeight:160x600 RequestType:getImage]</b><br />
OR getScript<br />
<b>[keypic keypic WeightHeight:160x600 RequestType:getScript]</b><br />

</td>
</tr>
<tr>
<td>
Get Image<br />
<b>[keypic keypic WeightHeight:300x600 RequestType:getImage]</b><br />
OR getScript<br />
<b>[keypic keypic WeightHeight:300x600 RequestType:getScript]</b><br />

</td>
</tr>

</table>

</form>
</div>
<?php
}

// Messages

add_filter( 'wpcf7_messages', 'keypic_wpcf7_messages' );

function keypic_wpcf7_messages( $messages )
{
	return array_merge( $messages, array( 'keypic_says_spam' => array(
		'description' => __( "Keypic judged the sending activity as spamming", 'wpcf7' ),
		'default' => __( 'Failed to send your message. Please try later or contact the administrator by another method.', 'wpcf7' )
	) ) );
}

add_filter( 'wpcf7_display_message', 'keypic_wpcf7_display_message', 10, 2 );

function keypic_wpcf7_display_message( $message, $status )
{
	if ( 'spam' == $status && empty( $message ) )
		$message = wpcf7_get_message( 'keypic_says_spam' );

	return $message;
}



?>