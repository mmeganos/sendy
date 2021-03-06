<?php include('../functions.php');?>
<?php include('../login/auth.php');?>
<?php require_once('../helpers/EmailAddressValidator.php');?>
<?php

/********************************/
$userID = get_app_info('main_userID');
$app = $_POST['app'];
$listID = mysqli_real_escape_string($mysqli, $_POST['list_id']);
$line = $_POST['line'];
$gdpr_tag = isset($_POST['gdpr_tag']) ? 1 : 0;
$time = time();
/********************************/

//get comma separated lists belonging to this app
$q2 = 'SELECT id FROM lists WHERE app = '.$app;
$r2 = mysqli_query($mysqli, $q2);
if ($r2)
{
	$all_lists = '';
    while($row = mysqli_fetch_array($r2)) $all_lists .= $row['id'].',';
    $all_lists = substr($all_lists, 0, -1);
}

//if user did not enter anything
if($line=='')
{
	//show error msg
	header("Location: ".get_app_info('path').'/update-list?i='.$app.'&l='.$listID.'&e=2'); 
	exit;
}

$line_array = explode("\r\n", $line);

for($i=0;$i<count($line_array);$i++)
{
	$the_line = explode(',', $line_array[$i]);
	
	if(count($the_line)==1)
	{
		$name = '';
		$email = $the_line[0];
	}
	else
	{
		$name = strip_tags($the_line[0]);
		$email = $the_line[1];
	}
	
	$email = trim($email);
	
	$q = 'SELECT custom_fields FROM subscribers WHERE list = '.$listID.' AND email = "'.trim($email).'" AND userID = '.$userID;
	$r = mysqli_query($mysqli, $q);
	if (mysqli_num_rows($r) > 0) //if so, update subscriber
	{
		while($row = mysqli_fetch_array($r))
	    {
	    	$custom_values = $row['custom_fields'];
	    } 
		
		if($columns==1)
		{
			$email = trim($linearray[0]);
		}
		else if($columns==2)
		{
			$name = strip_tags($linearray[0]);
			$email = trim($linearray[1]);
		}
		else if($columns==$custom_fields_count+2)
		{
			$name = strip_tags($linearray[0]);
			$email = trim($linearray[1]);
		}
		
		$gdpr_status = $gdpr_tag ? ', gdpr = '.$gdpr_tag : '';
	    
		if(!isset($name) || $name=='')
			$q = 'UPDATE subscribers SET timestamp = '.$time.' '.$gdpr_status.' WHERE email = "'.$email.'" AND list = '.$listID;
		else
			$q = 'UPDATE subscribers SET timestamp = '.$time.', name = "'.$name.'" '.$gdpr_status.' WHERE email = "'.$email.'" AND list = '.$listID;

		mysqli_query($mysqli, $q);
	}
	else
	{
		//Check if user set the list to unsubscribe from all lists
		$q = 'SELECT unsubscribe_all_list FROM lists WHERE id = '.$listID;
		$r = mysqli_query($mysqli, $q);
		if ($r) while($row = mysqli_fetch_array($r)) $unsubscribe_all_list = $row['unsubscribe_all_list'];
			
		//Check if this email is previously marked as bounced, if so, we shouldn't add it
		if($unsubscribe_all_list)
			$q = 'SELECT email from subscribers WHERE ( email = "'.trim($email).'" AND bounced = 1 ) OR ( email = "'.trim($email).'" AND list IN ('.$all_lists.') AND (complaint = 1 OR unsubscribed = 1) )';
		else
			$q = 'SELECT email from subscribers WHERE ( email = "'.trim($email).'" AND bounced = 1 ) OR ( email = "'.trim($email).'" AND list IN ('.$all_lists.') AND complaint = 1 )';
		$r = mysqli_query($mysqli, $q);
		if (mysqli_num_rows($r) > 0){}
		else
		{			
			$validator = new EmailAddressValidator;			
			if ($validator->check_email_address(trim($email))) 
			{
				$q = 'INSERT INTO subscribers (userID, name, email, list, timestamp, gdpr) values('.$userID.', "'.$name.'", "'.trim($email).'", '.$listID.', '.$time.', '.$gdpr_tag.')';
				$r = mysqli_query($mysqli, $q);
				if ($r){}
			}
		}
	}
}

header("Location: ".get_app_info('path').'/subscribers?i='.$app.'&l='.$listID); 

?>
