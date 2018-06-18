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
	include('includes/helpers/short.php');
	
	//new encrytped string
	if(!is_numeric(short($_GET['e'], true)))
	{
		$i_array = explode('/', short($_GET['e'], true));
		$email_id = $i_array[0];
		$list_id = $i_array[1];
	}
	//old encrypted string
	else
	{
		$email_id = short(mysqli_real_escape_string($mysqli, $_GET['e']), true);
		$list_id = short(mysqli_real_escape_string($mysqli, $_GET['l']), true);
	}
	
	$time = time();
	$join_date = round($time/60)*60;
	
	//Set language
	$q = 'SELECT login.language FROM lists, login WHERE lists.id = '.$list_id.' AND login.app = lists.app';
	$r = mysqli_query($mysqli, $q);
	if ($r && mysqli_num_rows($r) > 0) while($row = mysqli_fetch_array($r)) $language = $row['language'];
	set_locale($language);
	
	$q = 'UPDATE subscribers SET confirmed = 1, timestamp = "'.$time.'", join_date = CASE WHEN join_date IS NULL THEN '.$join_date.' ELSE join_date END WHERE id = '.$email_id.' AND list = '.$list_id;
	$r = mysqli_query($mysqli, $q);
	if ($r){
		//get thank you message etc
		$q2 = 'SELECT app, userID, thankyou, thankyou_subject, thankyou_message, confirm_url FROM lists WHERE id = '.$list_id;
		$r2 = mysqli_query($mysqli, $q2);
		if ($r2)
		{
		    while($row = mysqli_fetch_array($r2))
		    {
				$userID = $row['userID'];
				$app = $row['app'];
				$thankyou = $row['thankyou'];
				$thankyou_subject = stripslashes($row['thankyou_subject']);
				$thankyou_message = stripslashes($row['thankyou_message']);
				$confirm_url = stripslashes($row['confirm_url']);
		    }  
		}
		//get email address of subscribing user
		$q3 = 'SELECT name, email FROM subscribers WHERE id = '.$email_id;
		$r3 = mysqli_query($mysqli, $q3);
		if ($r3)
		{
		    while($row = mysqli_fetch_array($r3))
		    {
				$name = $row['name'];
				$email = $row['email'];
		    }  
		}
		
		//Zapier Trigger 'new_user_subscribed' event
		zapier_trigger_new_user_subscribed($name, $email, $list_id);
	}
	
	if($thankyou)
	{
		//send confirmation email if list is double opt in
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
		
		//Convert personaliztion tags
		convert_tags($thankyou_subject, $email_id, 'subject');
		convert_tags($thankyou_message, $email_id, 'message');
		
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
	
	//if user sets a redirection URL
	if($confirm_url != ''):
		$confirm_url = str_replace('%n', $name, $confirm_url);
		$confirm_url = str_replace('%e', $email, $confirm_url);
		$confirm_url = str_replace('%l', short($list_id), $confirm_url);
		header("Location: ".$subscribed_url);
		header("Location: ".$confirm_url);
	else:
?>
<!DOCTYPE html>
<html>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<link rel="Shortcut Icon" type="image/ico" href="<?php echo APP_PATH;?>/img/favicon.png">
		<title><?php echo _('You\'re subscribed!');?></title>
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
			<h2><?php echo _('You\'re subscribed!');?></h2>
		</div>
	</body>
</html>
<?php endif;
	//--------------------------------------------------------------//
	function convert_tags($content_to_replace, $sid, $to_replace)
	//--------------------------------------------------------------//
	{
		global $mysqli;
		global $list_id;
		global $name;
		global $thankyou_subject;
		global $thankyou_message;
		
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
		if($to_replace=='subject')
			$thankyou_subject = $content_to_replace;
		else if($to_replace=='message')
			$thankyou_message = $content_to_replace;
	}
?>