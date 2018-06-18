<?php 
	ini_set('display_errors', 0);
	include('includes/config.php');
	include('includes/helpers/locale.php');
	include('includes/helpers/integrations/zapier/triggers/functions.php');
	//--------------------------------------------------------------//
	function dbConnect() { //Connect to database
	//--------------------------------------------------------------//
	    // Access global variables
	    global $mysqli;
	    global $dbHost;
	    global $dbUser;
	    global $dbPass;
	    global $dbName;
	    global $dbPort;
	    
	    // Attempt to connect to database server
	    if(isset($dbPort)) $mysqli = new mysqli($dbHost, $dbUser, $dbPass, $dbName, $dbPort);
	    else $mysqli = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
	
	    // If connection failed...
	    if ($mysqli->connect_error) {
	        fail("<!DOCTYPE html><html><head><meta http-equiv=\"Content-Type\" content=\"text/html;charset=utf-8\"/><link rel=\"Shortcut Icon\" type=\"image/ico\" href=\"/img/favicon.png\"><title>"._('Can\'t connect to database')."</title></head><style type=\"text/css\">body{background: #ffffff;font-family: Helvetica, Arial;}#wrapper{background: #f2f2f2;width: 300px;height: 110px;margin: -140px 0 0 -150px;position: absolute;top: 50%;left: 50%;-webkit-border-radius: 5px;-moz-border-radius: 5px;border-radius: 5px;}p{text-align: center;line-height: 18px;font-size: 12px;padding: 0 30px;}h2{font-weight: normal;text-align: center;font-size: 20px;}a{color: #000;}a:hover{text-decoration: none;}</style><body><div id=\"wrapper\"><p><h2>"._('Can\'t connect to database')."</h2></p><p>"._('There is a problem connecting to the database. Please try again later.')."</p></div></body></html>");
	    }
	    
	    global $charset; mysqli_set_charset($mysqli, isset($charset) ? $charset : "utf8");
	    
	    return $mysqli;
	}
	//--------------------------------------------------------------//
	function fail($errorMsg) { //Database connection fails
	//--------------------------------------------------------------//
	    echo $errorMsg;
	    exit;
	}
	// connect to database
	dbConnect();
?>
<?php
	include_once('includes/helpers/short.php');
	include_once('includes/helpers/geo/geoip.inc');
	
	//vars
	$time = time();
	$join_date = round(time()/60)*60;
	$already_subscribed = false;
	$feedback = '';
	$subform = mysqli_real_escape_string($mysqli, $_POST['subform'])=='yes' ? true : false;
	$ip1 = $ip2 = $country1 = $country2 = $referrer1 = $referrer2 = $gdpr1 = $gdpr2 = $gdpr3 = '';
	$gdpr = 0;
	
	//get variable
	if(isset($_GET['i']))
	{
		$i = mysqli_real_escape_string($mysqli, $_GET['i']);
		$i_array = array();
		$i_array = explode('/', $i);
		if(count($i_array)==1)
		{
			$i_array = array();
			$i_array = explode('/', short($i, true));
			$email = short(trim($i_array[0]));
			$list_id = $i_array[1];
			if(array_key_exists(2, $i_array)) $name = $i_array[2];
			if(array_key_exists(3, $i_array)) $return_boolean = $i_array[3];
			else $return_boolean = '';
		}
		else
		{
			$email = trim($i_array[0]);
			$email = str_replace(" ", "+", $email);
	        $email = str_replace("%20", "+", $email);
			$list_id = short($i_array[1], true);
			if(array_key_exists(2, $i_array)) $name = $i_array[2];
			if(array_key_exists(3, $i_array)) $return_boolean = $i_array[3];
			else $return_boolean = '';
		}
		
		//Set language
		$q = 'SELECT login.language FROM lists, login WHERE lists.id = '.$list_id.' AND login.app = lists.app';
		$r = mysqli_query($mysqli, $q);
		if ($r && mysqli_num_rows($r) > 0) while($row = mysqli_fetch_array($r)) $language = $row['language'];
		set_locale($language);
		
		//check if email needs to be decrypted
		$validator = new EmailAddressValidator;
		if ($validator->check_email_address($email)) 
		{
			if($return_boolean=='true')
			{
				echo 'Invalid email address.';
				exit;
			}
			else $feedback = _('Email address is invalid.');
		}
		else
		{
			$email = short($email, true);
			
			//check if email is valid
			$validator = new EmailAddressValidator;
			if ($validator->check_email_address($email)) {}
			else
			{
				if($return_boolean=='true')
				{
					echo 'Invalid email address.';
					exit;
				}
				else $feedback = _('Email address is invalid.');
			}
		}
	}
	else if(isset($_POST['email']))//email posted from subscribe form or API
	{		
		//parameters
		$email = mysqli_real_escape_string($mysqli, trim($_POST['email'])); //compulsory
		$name = strip_tags(mysqli_real_escape_string($mysqli, $_POST['name'])); //optional
		$list_id = strip_tags(short(mysqli_real_escape_string($mysqli, $_POST['list']), true)); //compulsory
		$return_boolean = strip_tags(mysqli_real_escape_string($mysqli, $_POST['boolean'])); //compulsory
		$hp = strip_tags(mysqli_real_escape_string($mysqli, $_POST['hp'])); //honeypot
		$ipaddress = strip_tags(mysqli_real_escape_string($mysqli, $_POST['ipaddress'])); //optional
		$country = strip_tags(mysqli_real_escape_string($mysqli, $_POST['country'])); //optional
		$referrer = strip_tags(mysqli_real_escape_string($mysqli, $_POST['referrer'])); //optional
			
		if($ipaddress != '')
		{
			//Check if it's a valid IP
			if(filter_var($ipaddress, FILTER_VALIDATE_IP) === false)
			{
				if($return_boolean=='true')
				{
					echo 'IP address is invalid.';
					exit;
				}
				else
					$feedback = _('IP address is invalid.');
			}
			$ip1 = ', ip';
			$ip2 = ', "'.$ipaddress.'"';
		}
		if($country != '')
		{
			//Check country code is valid
			if(country_code_exist($country))
			{
				if($return_boolean=='true')
				{
					echo 'Country must be a valid 2 letter country code';
					exit;
				}
				else
					$feedback = _('Country must be a valid 2 letter country code');
			}
			$country1 = ', country';
			$country2 = ', "'.$country.'"';
		}
		if($referrer != '')
		{
			//Check if referrer is a valid URL
			if (filter_var($referrer, FILTER_VALIDATE_URL) === FALSE) 
			{
			    if($return_boolean=='true')
				{
					echo 'Referrer is not a valid URL';
					exit;
				}
				else
					$feedback = _('Referrer is not a valid URL');
			}
			$referrer1 = ', referrer';
			$referrer2 = ', "'.$referrer.'"';
		}		
		$added_via = 2; //1 = Sendy app, 2 = API, 3 = Sendy's subscribe form
		
		//Set language
		$q = 'SELECT login.language, lists.gdpr_enabled as gdpr_enabled FROM lists, login WHERE lists.id = '.$list_id.' AND login.app = lists.app';
		$r = mysqli_query($mysqli, $q);
		if ($r && mysqli_num_rows($r) > 0) 
		{
			while($row = mysqli_fetch_array($r)) 
			{
				$language = $row['language'];
				$gdpr_enabled = $row['gdpr_enabled'];
			}
		}
		set_locale($language);
		
		if(!$subform)
		{
			if($_POST['gdpr']=='true')
				$gdpr = 1;
			else if($_POST['gdpr']=='false')
				$gdpr = 0;
			else 
				$gdpr = 0;
			
			if($gdpr!='')
			{
				$gdpr1 = ', gdpr = '.$gdpr;
				$gdpr2 = ', gdpr';
				$gdpr3 = ', '.$gdpr;
			}
		}
		
		//check if no data passed
		if($email=='' || $list_id=='')
		{
			if($return_boolean=='true')
			{
				echo 'Some fields are missing.';
				exit;
			}
			else
				$feedback = _('Some fields are missing.');
		}
		else
		{
			//check if email is valid
			$validator = new EmailAddressValidator;
			if (!$validator->check_email_address($email) || $hp!='')
			{
				if($return_boolean=='true')
				{
					echo 'Invalid email address.';
					exit;
				}
				else
				    $feedback = _('Email address is invalid.');
			}
		}
		
		//Check if email is bounced anywhere in the database 
		$q = 'SELECT id FROM subscribers WHERE email = "'.$email.'" AND bounced = 1';
		$r = mysqli_query($mysqli, $q);
		if (mysqli_num_rows($r) > 0)
		{
		    //email is bounced, don't add it to the list
		    if($return_boolean=='true')
			{
				echo 'Invalid email address.';
				exit;
			}
			else
			{
			    $feedback = _('Email address is invalid.');
			}  
		}
		
	}
	else if($_GET['i']=='')
	{
		exit;
	}
	
	//If user subscribes using Sendy's subscription form or HTML code, store user's IP address and country code
	if($subform)
	{
		//IP
		$ipaddress = ipaddress();
		$ip1 = ', ip';
		$ip2 = ', "'.$ipaddress.'"';
		//Country
		$gi = geoip_open("includes/helpers/geo/GeoIP.dat",GEOIP_STANDARD);
		$country = geoip_country_code_by_addr($gi, $ipaddress); //returns eg. US
		geoip_close($gi);
		$country1 = ', country';
		$country2 = ', "'.$country.'"';
		//Referrer
		$referrer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
		$referrer1 = ', referrer';
		$referrer2 = ', "'.$referrer.'"';
		//Added via
		$added_via = 3; //1 = Sendy app, 2 = API, 3 = Sendy's subscribe form
		
		//Check the GDPR checkbox
		if($gdpr_enabled)
		{
			if(isset($_POST['gdpr']))
				$gdpr = 1;
			else
			{
				if($return_boolean=='true')
				{
					echo 'Consent not given.';
					exit;
				}
				else
					$feedback = _('Consent not given.');
			}
		}
		else $gdpr = 0;
		
		$gdpr1 = ', gdpr = '.$gdpr;
		$gdpr2 = ', gdpr';
		$gdpr3 = ', '.$gdpr;
	}
	
	if($feedback!=_('Some fields are missing.') && $feedback!=_('Email address is invalid.') && $feedback!=_('Consent not given.'))
	{		
		//check if list is double opt in
		$q = 'SELECT opt_in, subscribed_url, thankyou, thankyou_subject, thankyou_message, custom_fields FROM lists WHERE id = '.$list_id;
		$r = mysqli_query($mysqli, $q);
		if ($r && mysqli_num_rows($r) > 0)
		{
		    while($row = mysqli_fetch_array($r))
		    {
				$opt_in = $row['opt_in'];
				$subscribed_url = $row['subscribed_url'];
				$thankyou = $row['thankyou'];
				$thankyou_subject = stripslashes($row['thankyou_subject']);
				$thankyou_message = stripslashes($row['thankyou_message']);
				$custom_fields = $row['custom_fields'];
		    }
			
		    //get custom fields list and format it for db insert
		    $cf_vals = '';
			$custom_fields_array = explode('%s%', $custom_fields);
			foreach($custom_fields_array as $cf)
			{
				$cf_array = explode(':', $cf);
				foreach ($_POST as $key => $value)
				{
					//if custom field matches POST data but IS NOT name, email, list or submit
					if(str_replace(' ', '', $cf_array[0])==$key && ($key!='name' && $key!='email' && $key!='list' && $key!='submit'))
					{
						//if custom field format is Date
						if($cf_array[1]=='Date')
						{
							$date_value1 = strtotime($value);
							$date_value2 = strftime("%b %d, %Y 12am", $date_value1);
							$value = strtotime($date_value2);
							$cf_vals .= $value;
						}
						//else if custom field format is Text
						else
							$cf_vals .= addslashes($value);
					}
				}
				$cf_vals .= '%s%';
			}
		}
		
		$last_activity = '';
		$opt_in_throttle = 3600;
		
		//check if user is in this list
		$q = 'SELECT id, userID, custom_fields, unsubscribed, confirmed, bounced, complaint, timestamp FROM subscribers WHERE email = "'.$email.'" AND list = '.$list_id;
		$r = mysqli_query($mysqli, $q);
		if ($r && mysqli_num_rows($r) > 0) //if so, update subscriber
		{
			while($row = mysqli_fetch_array($r))
		    {
		    	$subscriber_id = $row['id'];
				$userID = $row['userID'];
				$custom_values = $row['custom_fields'];
				$unsubscribed = $row['unsubscribed'];
				$confirmed = $row['confirmed'];
				$bounced = $row['bounced'];
				$complaint = $row['complaint'];
				$timestamp = $row['timestamp'];
				$last_activity = $time - $timestamp;
		    } 
		    
		    //get custom fields values
		    $j = 0;
		    $cf_value = '';
		    $custom_values_array = explode('%s%', $custom_values);
		    foreach($custom_fields_array as $cf_fields)
			{
				$k = 0;
				$cf_fields_array = explode(':', $cf_fields);
				foreach ($_POST as $key => $value)
				{
					//if custom field matches POST data but IS NOT name, email, list or submit
					if(str_replace(' ', '', $cf_fields_array[0])==$key && ($key!='name' && $key!='email' && $key!='list' && $key!='submit'))
					{
						//if user left field empty
						if($value=='')
						{
							$cf_value .= '';
						}
						else
						{
							//if custom field format is Date
							if($cf_fields_array[1]=='Date')
							{
								$date_value1 = strtotime($value);
								$date_value2 = strftime("%b %d, %Y 12am", $date_value1);
								$value = strtotime($date_value2);
								$cf_value .= $value;
							}
							//else if custom field format is Text
							else
								$cf_value .= strip_tags($value);
						}
					}
					else
					{
						$k++;
					}
				}
				if(count($_POST)==$k) $cf_value .= $custom_values_array[$j];			
				$cf_value .= '%s%';
				$j++;
			}
		    
			if($opt_in) 
			{
				$confirmed = $unsubscribed && $confirmed ? 0 : $confirmed;
				
				if(!isset($_POST['name']))
					$q = 'UPDATE subscribers SET unsubscribed = 0, last_campaign = NULL, timestamp = '.$time.', confirmed = '.$confirmed.', custom_fields = "'.substr($cf_value, 0, -3).'" '.$gdpr1.' WHERE email = "'.$email.'" AND list = '.$list_id;
				else
					$q = 'UPDATE subscribers SET unsubscribed = 0, last_campaign = NULL, timestamp = '.$time.', confirmed = '.$confirmed.', name = "'.$name.'", custom_fields = "'.substr($cf_value, 0, -3).'" '.$gdpr1.' WHERE email = "'.$email.'" AND list = '.$list_id;
			}
			else
			{
				if(!isset($_POST['name']))
					$q = 'UPDATE subscribers SET unsubscribed = 0, last_campaign = NULL, timestamp = '.$time.', confirmed = 1, custom_fields = "'.substr($cf_value, 0, -3).'", gdpr = '.$gdpr.' WHERE email = "'.$email.'" AND list = '.$list_id;
				else
					$q = 'UPDATE subscribers SET unsubscribed = 0, last_campaign = NULL, timestamp = '.$time.', confirmed = 1, name = "'.$name.'", custom_fields = "'.substr($cf_value, 0, -3).'", gdpr = '.$gdpr.' WHERE email = "'.$email.'" AND list = '.$list_id;
			}
			$r = mysqli_query($mysqli, $q);
			if ($r)
			{
				if(!$unsubscribed && $confirmed) $already_subscribed = true;
				if(!$already_subscribed)
				{
					if($opt_in && $confirmed!=1)
					{
						if($last_activity > $opt_in_throttle || $unsubscribed)
							$feedback = '<span style="font-size: 20px;padding:10px;float:left;margin-top:-18px;">'._('Thank you, a confirmation email has been sent to you.').'</span>';
						else
							$feedback = '<span style="font-size: 20px;padding:10px;float:left;margin-top:-18px;">'._('A confirmation email had already been sent to you.').'</span>';
					}
					else
						$feedback = _('You\'re subscribed!');
				}
				else
				{
					if($return_boolean=='true')
					{
						echo 'Already subscribed.';
						exit;
					}
					else
					{
						if($confirmed==0)
							$feedback = '<span style="font-size: 20px;padding:10px;float:left;margin-top:-18px;">'._('A confirmation email had already been sent to you.').'</span>';
						else
						    $feedback = _('You\'re already subscribed!');
					}
				}
			}
		}
		//if user does not exist in list, insert subscriber into database
		else
		{
			$q = 'SELECT userID FROM lists WHERE id = '.$list_id;
			$r = mysqli_query($mysqli, $q);
			if ($r && mysqli_num_rows($r) > 0)
			{
			    while($row = mysqli_fetch_array($r)) $userID = stripslashes($row['userID']);
			    
			    //if not, insert user into list
			    if($opt_in) //if double opt in,
					$q = 'INSERT INTO subscribers (userID, email, name, custom_fields, list, timestamp, confirmed, method, added_via '.$ip1.' '.$country1.' '.$referrer1.' '.$gdpr2.') VALUES ('.$userID.', "'.$email.'", "'.$name.'", "'.substr($cf_vals, 0, -3).'", '.$list_id.', '.$time.', 0, 2, '.$added_via.' '.$ip2.' '.$country2.' '.$referrer2.' '.$gdpr3.')';
				else
					$q = 'INSERT INTO subscribers (userID, email, name, custom_fields, list, timestamp, join_date, method, added_via '.$ip1.' '.$country1.' '.$referrer1.' '.$gdpr2.') VALUES ('.$userID.', "'.$email.'", "'.$name.'", "'.substr($cf_vals, 0, -3).'", '.$list_id.', '.$time.', '.$join_date.', 1, '.$added_via.' '.$ip2.' '.$country2.' '.$referrer2.' '.$gdpr3.')';
				$r = mysqli_query($mysqli, $q);
				if ($r){
					
					$subscriber_id = mysqli_insert_id($mysqli);
					
					if($opt_in)
						$feedback = '<span style="font-size: 20px;padding:10px;float:left;margin-top:-18px;">'._('Thank you, a confirmation email has been sent to you.').'</span>';
					else
					{
						$feedback = _('You\'re subscribed!');
						
						//Zapier Trigger 'new_user_subscribed' event
						zapier_trigger_new_user_subscribed($name, $email, $list_id);
					}
				}
			}
			else
			{
				echo 'Invalid list ID.';
				exit;
			}
		}
		
		if(!$already_subscribed)
		{
			//get AWS creds
			$q = 'SELECT s3_key, s3_secret FROM login WHERE id = '.$userID;
			$r = mysqli_query($mysqli, $q);
			if ($r)
			{
			    while($row = mysqli_fetch_array($r))
			    {
					$s3_key = $row['s3_key'];
					$s3_secret = $row['s3_secret'];
			    }
			}
			
			//get from name and from email
			$q2 = 'SELECT app FROM lists WHERE id = '.$list_id;
			$r2 = mysqli_query($mysqli, $q2);
			if ($r2)
			{
			    while($row = mysqli_fetch_array($r2))
			    {
					$app = $row['app'];
			    }  
			    $q3 = 'SELECT from_name, from_email, reply_to, smtp_host, smtp_port, smtp_ssl, smtp_username, smtp_password, allocated_quota FROM apps WHERE id = '.$app;
				$r3 = mysqli_query($mysqli, $q3);
				if ($r3)
				{
				    while($row = mysqli_fetch_array($r3))
				    {
						$from_name = $row['from_name'];
						$from_email = $row['from_email'];
						$reply_to = $row['reply_to'];
						$smtp_host = $row['smtp_host'];
						$smtp_port = $row['smtp_port'];
						$smtp_ssl = $row['smtp_ssl'];
						$smtp_username = $row['smtp_username'];
						$smtp_password = $row['smtp_password'];
						$allocated_quota = $row['allocated_quota'];
				    }  
				}
			}
			
			//send confirmation email if list is double opt in
			if($opt_in && $confirmed!=1 && $bounced!=1 && $complaint!=1)
			{			
				$confirmation_link = APP_PATH.'/confirm?e='.short($subscriber_id).'&l='.short($list_id);
				
				$q = 'SELECT confirmation_subject, confirmation_email FROM lists WHERE id = '.$list_id;
				$r = mysqli_query($mysqli, $q);
				if ($r && mysqli_num_rows($r) > 0)
				{
				    while($row = mysqli_fetch_array($r))
				    {
						$confirmation_subject = stripslashes($row['confirmation_subject']);
						$confirmation_email = stripslashes($row['confirmation_email']);
				    }  
				}
				
				if($confirmation_subject=='')
					$confirmation_subject = _('Confirm your subscription to').' '.$from_name;
				
				if(strlen(trim(preg_replace('/\xc2\xa0/',' ', $confirmation_email))) == 0 || trim($confirmation_email)=='<p><br></p>' || $output = trim(str_replace(array("\r\n", "\r", "\n", "	"), '', $confirmation_email))=="<html><head><title></title></head><body></body></html>")
					$confirmation_email = "<html>
<head>
	<title></title>
</head>
<body>
<div style=\"margin: -10px -10px; padding:50px 30px 50px 30px; height:100%;\">
	<div style=\"margin:0 auto; max-width:660px;\">
		<div style=\"float: left; background-color: #FFFFFF; padding:10px 30px 10px 30px; border: 1px solid #DDDDDD;\">
			<div style=\"float: left; max-width:470px;\">
				<p style=\"line-height: 21px; font-family: Helvetica, Verdana, Arial, sans-serif; font-size: 12px;\">
					<strong style=\"line-height: 21px; font-family: Helvetica, Verdana, Arial, sans-serif; font-size: 18px;\">"._('Confirm your subscription')."</strong>
				</p>	
				<div style=\"line-height: 21px; min-height: 100px; font-family: Helvetica, Verdana, Arial, sans-serif; font-size: 12px;\">
					<p style=\"line-height: 21px; font-family: Helvetica, Verdana, Arial, sans-serif; font-size: 12px;\">"._('Thanks for subscribing to our email list.')."</p>
					<p style=\"line-height: 21px; font-family: Helvetica, Verdana, Arial, sans-serif; font-size: 12px;\">"._('Please confirm your subscription by clicking the link below').":</p>
					<p style=\"line-height: 21px; font-family: Helvetica, Verdana, Arial, sans-serif; font-size: 12px; margin-bottom: 25px; background-color:#EDEDED; padding: 15px;\">
						<strong>"._('Confirm').": </strong><a style=\"color:#4371AB; text-decoration:none;\" href=\"$confirmation_link\">$confirmation_link</a>
					</p>
					<p style=\"line-height: 21px; font-family: Helvetica, Verdana, Arial, sans-serif; font-size: 12px;\">"._('Thank you').",<br/>$from_name</p>
				</div>
			</div>
		</div>
	</div>
</div>
</body>
</html>
";
				else
					$confirmation_email = str_replace('[confirmation_link]', $confirmation_link, $confirmation_email);
	
				//Convert personaliztion tags
				convert_tags($confirmation_subject, $subscriber_id, 'confirm', 'subject');
				convert_tags($confirmation_email, $subscriber_id, 'confirm', 'message');
				
				//Convert email tag
				$confirmation_email = str_replace('[Email]', $email, $confirmation_email);
				$confirmation_subject = str_replace('[Email]', $email, $confirmation_subject);
				
				include('includes/helpers/PHPMailerAutoload.php');
				$mail = new PHPMailer();	
				if($s3_key!='' && $s3_secret!='')
				{
					$mail->IsAmazonSES();
					$mail->AddAmazonSESKey($s3_key, $s3_secret);
				}
				else if($smtp_host!='' && $smtp_port!='' && $smtp_ssl!='' && $smtp_username!='' && $smtp_password!='')
				{
					$mail->IsSMTP();
					$mail->SMTPDebug = 0;
					$mail->SMTPAuth = true;
					$mail->SMTPSecure = $smtp_ssl;
					$mail->Host = $smtp_host;
					$mail->Port = $smtp_port; 
					$mail->Username = $smtp_username;  
					$mail->Password = $smtp_password;
				}
				$mail->CharSet	  =	"UTF-8";
				$mail->From       = $from_email;
				$mail->FromName   = $from_name;
				$mail->Subject = $confirmation_subject;
				$mail->MsgHTML($confirmation_email);
				$mail->AddAddress($email, '');
				$mail->AddReplyTo($reply_to, $from_name);
				
				if($last_activity=='' || $last_activity > $opt_in_throttle || $unsubscribed)
					$mail->Send();
				
				//Update quota if a monthly limit was set
				if($allocated_quota!=-1)
				{
					//if so, update quota
					$q4 = 'UPDATE apps SET current_quota = current_quota+1 WHERE id = '.$app;
					mysqli_query($mysqli, $q4);
				}
			}
			else //if single opt in, check if we need to send a thank you email
			{
				if($thankyou && $confirmed!=1)
				{					
					//Convert personaliztion tags
					convert_tags($thankyou_subject, $subscriber_id, 'thankyou', 'subject');
					convert_tags($thankyou_message, $subscriber_id, 'thankyou', 'message');
					
					//Convert email tag
					$thankyou_message = str_replace('[Email]', $email, $thankyou_message);
					$thankyou_subject = str_replace('[Email]', $email, $thankyou_subject);
					
					//Unsubscribe tag
					$thankyou_message = str_replace('<unsubscribe', '<a href="'.APP_PATH.'/unsubscribe/'.short($email).'/'.short($list_id).'" ', $thankyou_message);
			    	$thankyou_message = str_replace('</unsubscribe>', '</a>', $thankyou_message);
					$thankyou_message = str_replace('[unsubscribe]', APP_PATH.'/unsubscribe/'.short($email).'/'.short($list_id), $thankyou_message);
	
					include('includes/helpers/PHPMailerAutoload.php');
					$mail = new PHPMailer();	
					if($s3_key!='' && $s3_secret!='')
					{
						$mail->IsAmazonSES();
						$mail->AddAmazonSESKey($s3_key, $s3_secret);
					}
					else if($smtp_host!='' && $smtp_port!='' && $smtp_ssl!='' && $smtp_username!='' && $smtp_password!='')
					{
						$mail->IsSMTP();
						$mail->SMTPDebug = 0;
						$mail->SMTPAuth = true;
						$mail->SMTPSecure = $smtp_ssl;
						$mail->Host = $smtp_host;
						$mail->Port = $smtp_port; 
						$mail->Username = $smtp_username;  
						$mail->Password = $smtp_password;
					}
					$mail->CharSet	  =	"UTF-8";
					$mail->From       = $from_email;
					$mail->FromName   = $from_name;
					$mail->Subject = $thankyou_subject;
					$mail->MsgHTML($thankyou_message);
					$mail->AddAddress($email, '');
					$mail->AddReplyTo($reply_to, $from_name);
					$mail->Send();
					
					//Update quota if a monthly limit was set
					if($allocated_quota!=-1)
					{
						//if so, update quota
						$q4 = 'UPDATE apps SET current_quota = current_quota+1 WHERE id = '.$app;
						mysqli_query($mysqli, $q4);
					}
				}
			}
		}
	}

if($return_boolean=='true'):
	echo true;
	exit;
else:
	//if user sets a redirection URL
	if($subscribed_url != '' && !$already_subscribed):
		$subscribed_url = str_replace('%n', $name, $subscribed_url);
		$subscribed_url = str_replace('%e', $email, $subscribed_url);
		$subscribed_url = str_replace('%l', short($list_id), $subscribed_url);
		header("Location: ".$subscribed_url);
	else:
?>
<!DOCTYPE html>
<html>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<link rel="Shortcut Icon" type="image/ico" href="<?php echo APP_PATH;?>/img/favicon.png">
		<title><?php echo strip_tags($feedback);?></title>
	</head>
	<style type="text/css">
		body{
			background: #ffffff;
			font-family: Helvetica, Arial;
		}
		#wrapper 
		{
			background: #f9f9f9;
			
			width: 300px;
			height: 70px;
			
			margin: -140px 0 0 -150px;
			position: absolute;
			top: 50%;
			left: 50%;
			-webkit-border-radius: 5px;
			-moz-border-radius: 5px;
			border-radius: 5px;
		}
		p{
			text-align: center;
		}
		h2{
			font-weight: normal;
			text-align: center;
		}
		a{
			color: #000;
		}
		a:hover{
			text-decoration: none;
		}
		#top-pattern{
			margin-top: -8px;
			height: 8px;
			background: url("<?php echo APP_PATH; ?>/img/top-pattern.gif") repeat-x 0 0;
			background-size: auto 8px;
		}
	</style>
	<body>
		<div id="top-pattern"></div>
		<div id="wrapper">
			<h2><?php echo $feedback;?></h2>
		</div>
	</body>
</html>
<?php endif;?>
<?php endif;
	//--------------------------------------------------------------//
	function convert_tags($content_to_replace, $sid, $email_type, $to_replace)
	//--------------------------------------------------------------//
	{
		global $mysqli;
		global $list_id;
		global $name;
		global $thankyou_subject;
		global $thankyou_message;
		global $confirmation_subject;
		global $confirmation_email;
		
		preg_match_all('/\[([a-zA-Z0-9!#%^&*()+=$@._\-\:|\/?<>~`"\'\s]+),\s*fallback=/i', $content_to_replace, $matches_var, PREG_PATTERN_ORDER);
		preg_match_all('/,\s*fallback=([a-zA-Z0-9!,#%^&*()+=$@._\-\:|\/?<>~`"\'\s]*)\]/i', $content_to_replace, $matches_val, PREG_PATTERN_ORDER);
		preg_match_all('/(\[[a-zA-Z0-9!#%^&*()+=$@._\-\:|\/?<>~`"\'\s]+,\s*fallback=[a-zA-Z0-9!,#%^&*()+=$@._\-\:|\/?<>~`"\'\s]*\])/i', $content_to_replace, $matches_all, PREG_PATTERN_ORDER);
		preg_match_all('/\[([^\]]+),\s*fallback=/i', $content_to_replace, $matches_var, PREG_PATTERN_ORDER);
		preg_match_all('/,\s*fallback=([^\]]*)\]/i', $content_to_replace, $matches_val, PREG_PATTERN_ORDER);
		preg_match_all('/(\[[^\]]+,\s*fallback=[^\]]*\])/i', $content_to_replace, $matches_all, PREG_PATTERN_ORDER);
		$matches_var = $matches_var[1];
		$matches_val = $matches_val[1];
		$matches_all = $matches_all[1];
		for($i=0;$i<count($matches_var);$i++)
		{   
			$field = $matches_var[$i];
			$fallback = $matches_val[$i];
			$tag = $matches_all[$i];
			
			//if tag is Name
			if($field=='Name')
			{
				if($name=='')
					$content_to_replace = str_replace($tag, $fallback, $content_to_replace);
				else
					$content_to_replace = str_replace($tag, $name, $content_to_replace);
			}
			else //if not 'Name', it's a custom field
			{
				//Get subscriber's custom field values
				$q = 'SELECT custom_fields FROM subscribers WHERE id = '.$sid;
				$r = mysqli_query($mysqli, $q);
				if ($r) while($row = mysqli_fetch_array($r)) $custom_values = $row['custom_fields'];
								
				//if subscriber has no custom fields, use fallback
				if($custom_values=='')
					$content_to_replace = str_replace($tag, $fallback, $content_to_replace);
				//otherwise, replace custom field tag
				else
				{					
					$q5 = 'SELECT custom_fields FROM lists WHERE id = '.$list_id;
					$r5 = mysqli_query($mysqli, $q5);
					if ($r5)
					{
					    while($row2 = mysqli_fetch_array($r5)) $custom_fields = $row2['custom_fields'];
					    $custom_fields_array = explode('%s%', $custom_fields);
					    $custom_values_array = explode('%s%', $custom_values);
					    $cf_count = count($custom_fields_array);
					    $k = 0;
					    
					    for($j=0;$j<$cf_count;$j++)
					    {
						    $cf_array = explode(':', $custom_fields_array[$j]);
						    $key = str_replace(' ', '', $cf_array[0]);
						    
						    //if tag matches a custom field
						    if($field==$key)
						    {
						    	//if custom field is empty, use fallback
						    	if($custom_values_array[$j]=='')
							    	$content_to_replace = str_replace($tag, $fallback, $content_to_replace);
						    	//otherwise, use the custom field value
						    	else
						    	{
						    		//if custom field is of 'Date' type, format the date
						    		if($cf_array[1]=='Date')
							    		$content_to_replace = str_replace($tag, strftime("%a, %b %d, %Y", $custom_values_array[$j]), $content_to_replace);
						    		//otherwise just replace tag with custom field value
						    		else
								    	$content_to_replace = str_replace($tag, $custom_values_array[$j], $content_to_replace);
						    	}
						    }
						    else
						    	$k++;
					    }
					    if($k==$cf_count)
					    	$content_to_replace = str_replace($tag, $fallback, $content_to_replace);
					}
				}
			}
		}
		if($email_type=='thankyou')
		{
			if($to_replace=='subject')
				$thankyou_subject = $content_to_replace;
			else if($to_replace=='message')
				$thankyou_message = $content_to_replace;
		}
		else if($email_type=='confirm')
		{
			if($to_replace=='subject')
				$confirmation_subject = $content_to_replace;
			else if($to_replace=='message')
				$confirmation_email = $content_to_replace;
		}
	}
	
	//--------------------------------------------------------------//
	function ipaddress()
	//--------------------------------------------------------------//
	{
		//get user's ip address
		if (getenv("HTTP_CLIENT_IP")) {
			$ip = getenv("HTTP_CLIENT_IP");
		} elseif (getenv("HTTP_X_FORWARDED_FOR")) {
			$ip = getenv("HTTP_X_FORWARDED_FOR");
		} else {
			$ip = getenv("REMOTE_ADDR");
		}
		return $ip;
	}
	
	//------------------------------------------------------//
	function country_code_exist($code)
	//------------------------------------------------------//
	{
	    $country_code = array('AF','AX','AL','DZ','AS','AD','AO','AI','AQ','AG','AR','AM','AW','AU','AT','AZ','BS','BH','BD','BB','BY','BE','BZ','BJ','BM','BT','BO','BA','BW','BV','BR','IO','VG','BN','BG','BF','BI','KH','CM','CA','CV','KY','CF','TD','CL','CN','CX','CC','CO','KM','CD','CG','CK','CR','CI','HR','CU','CY','CZ','DK','DJ','DM','DO','EC','EG','SV','GQ','ER','EE','ET','FO','FK','FJ','FI','FR','GF','PF','TF','GA','GM','GE','DE','GH','GI','GR','GL','GD','GP','GU','GT','GG','GN','GW','GY','HT','HM','VA','HN','HK','HU','IS','IN','ID','IR','IQ','IE','IM','IL','IT','JM','JP','JE','JO','KZ','KE','KI','KP','KR','KW','KG','LA','LV','LB','LS','LR','LY','LI','LT','LU','MO','MK','MG','MW','MY','MV','ML','MT','MH','MQ','MR','MU','YT','MX','FM','MD','MC','MN','ME','MS','MA','MZ','MM','NA','NR','NP','AN','NL','NC','NZ','NI','NE','NG','NU','NF','MP','NO','OM','PK','PW','PS','PA','PG','PY','PE','PH','PN','PL','PT','PR','QA','RE','RO','RU','RW','BL','SH','KN','LC','MF','PM','VC','WS','SM','ST','SA','SN','RS','SC','SL','SG','SK','SI','SB','SO','ZA','GS','ES','LK','SD','SR','SJ','SZ','SE','CH','SY','TW','TJ','TZ','TH','TL','TG','TK','TO','TT','TN','TR','TM','TC','TV','UG','UA','AE','GB','US','UM','VI','UY','UZ','VU','VE','VN','WF','EH','YE','ZM','ZW','EU' );
	    return in_array($code, $country_code) ? false : true;
	}
?>