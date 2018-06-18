<?php include('../functions.php');?>
<?php include('../login/auth.php');?>
<?php 

/********************************/
$userID = get_app_info('main_userID');
$campaign_id = isset($_GET['c']) ? mysqli_real_escape_string($mysqli, $_GET['c']) : '';
$link_id = isset($_GET['l']) ? mysqli_real_escape_string($mysqli, $_GET['l']) : '';
$action = isset($_GET['a']) ? $_GET['a'] : '';
$additional_query = '';
/********************************/

if($action == 'clicks')
{
	//file name
	$filename = 'clicked.csv';
	$additional_query = 'AND subscribers.unsubscribed = 0 AND subscribers.bounced = 0 AND subscribers.complaint = 0';
	
	//get
	$clicks_join = '';
	$clicks_array = array();
	$clicks_unique = 0;
	
	$q = 'SELECT id, clicks FROM links WHERE campaign_id = '.$campaign_id;
	$r = mysqli_query($mysqli, $q);
	if ($r && mysqli_num_rows($r) > 0)
	{
	    while($row = mysqli_fetch_array($r))
	    {
	    	$id = stripslashes($row['id']);
			$clicks = stripslashes($row['clicks']);
			if($clicks!='')
				$clicks_join .= $clicks.',';				
	    }  
	}
	
	$clicks_array = explode(',', $clicks_join);
	$clicks_unique = array_unique($clicks_array);
	$subscribers = substr(implode(',', $clicks_unique), 0, -1);
}
else if($action == 'opens')
{
	//file name
	$filename = 'opened.csv';
	$additional_query = 'AND subscribers.unsubscribed = 0 AND subscribers.bounced = 0 AND subscribers.complaint = 0';
	
	$q = 'SELECT opens FROM campaigns WHERE id = '.$campaign_id;
	$r = mysqli_query($mysqli, $q);
	if ($r && mysqli_num_rows($r) > 0)
	{
	    while($row = mysqli_fetch_array($r))
	    {
  			$opens = stripslashes($row['opens']);
  			
  			$opens_array = explode(',', $opens);
  			$opens_array_no_country = array();
  			foreach($opens_array as $opens_array_nc)
  			{
  				$e = explode(':', $opens_array_nc);
	  			array_push($opens_array_no_country, $e[0]);
  			}
  			
  			$opens_unique = array_unique($opens_array_no_country);
	  		$subscribers = implode(',', $opens_unique);
	    }  
	}
}
else if($action == 'unopens')
{
	//file name
	$filename = 'unopened.csv';
	$additional_query = 'AND subscribers.unsubscribed = 0 AND subscribers.bounced = 0 AND subscribers.complaint = 0 AND subscribers.confirmed = 1';
	
	$q = 'SELECT opens FROM campaigns WHERE id = '.$campaign_id;
	$r = mysqli_query($mysqli, $q);
	if ($r && mysqli_num_rows($r) > 0)
	{
	    while($row = mysqli_fetch_array($r))
	    {
  			$opens = stripslashes($row['opens']);
  			
  			$opens_array = explode(',', $opens);
  			$opens_array_no_country = array();
  			foreach($opens_array as $opens_array_nc)
  			{
  				$e = explode(':', $opens_array_nc);
	  			array_push($opens_array_no_country, $e[0]);
  			}
  			
  			$opens_unique_ini = array_unique($opens_array_no_country);
  			$opens_unique = array();
  			foreach($opens_unique_ini as $ou2)
  			{
	  			$opens_unique[$ou2] = $ou2;
  			}
	    }  
	}
	
	//Get lists the campaign was sent to
	$q = 'SELECT to_send_lists FROM campaigns WHERE id = '.$campaign_id;
	$r = mysqli_query($mysqli, $q);
	if ($r) while($row = mysqli_fetch_array($r)) $to_send_lists = $row['to_send_lists'];
	
	$q = 'SELECT id FROM subscribers WHERE list IN ('.$to_send_lists.')';
	$r = mysqli_query($mysqli, $q);
	if ($r && mysqli_num_rows($r) > 0)
	{
		$sid_not_opened = array();
	    while($row = mysqli_fetch_array($r))
	    {
			$sid = $row['id'];
			if(!isset($opens_unique[$sid])) array_push($sid_not_opened, $sid);
	    }  
	    
	    $sid_not_opened_unique = array_unique($sid_not_opened);
	    $subscribers = implode(',', $sid_not_opened_unique);
	}		
}
else if($action == 'unsubscribes')
{
	//file name
	$filename = 'unsubscribed.csv';
	
	$q = 'SELECT id FROM subscribers WHERE last_campaign = '.$campaign_id.' AND unsubscribed = 1';
	$r = mysqli_query($mysqli, $q);
	if ($r && mysqli_num_rows($r) > 0)
	{
		$unsubscribes_array = array();
	    while($row = mysqli_fetch_array($r))
	    {
  			$unsubscriber_id = $row['id'];
  			array_push($unsubscribes_array, $unsubscriber_id);
	    }  
	    
	    $subscribers = implode(',', $unsubscribes_array);
	}
}
else if($action == 'bounces')
{
	//file name
	$filename = 'bounced.csv';
	
	$q = 'SELECT id FROM subscribers WHERE last_campaign = '.$campaign_id.' AND bounced = 1';
	$r = mysqli_query($mysqli, $q);
	if ($r && mysqli_num_rows($r) > 0)
	{
		$unsubscribes_array = array();
	    while($row = mysqli_fetch_array($r))
	    {
  			$unsubscriber_id = $row['id'];
  			array_push($unsubscribes_array, $unsubscriber_id);
	    }  
	    
	    $subscribers = implode(',', $unsubscribes_array);
	}
}
else if($action == 'complaints')
{
	//file name
	$filename = 'marked-as-spam.csv';
	
	$q = 'SELECT id FROM subscribers WHERE last_campaign = '.$campaign_id.' AND complaint = 1';
	$r = mysqli_query($mysqli, $q);
	if ($r && mysqli_num_rows($r) > 0)
	{
		$unsubscribes_array = array();
	    while($row = mysqli_fetch_array($r))
	    {
  			$unsubscriber_id = $row['id'];
  			array_push($unsubscribes_array, $unsubscriber_id);
	    }  
	    
	    $subscribers = implode(',', $unsubscribes_array);
	}
}
else if($action == 'recipient_clicks')
{
	//file name
	$filename = 'recipients-who-clicked.csv';
	
	//get strings of click ids
	$q = 'SELECT clicks, link FROM links WHERE id = '.$link_id;
	$r = mysqli_query($mysqli, $q);
	if ($r) 
	{	
		while($row = mysqli_fetch_array($r)) 
		{
			$subscribers = $row['clicks'];
			$the_link = $row['link'];
		}
	}
	
	//Get only unique subscriber ids
	$sid_array = explode(',', $subscribers);
	$sid_array_unique = array_unique($sid_array);
	$subscribers = implode(',', $sid_array_unique);
}
else
{
	//file name
	$filename = $action.'.csv';
	
	$q = 'SELECT opens FROM campaigns WHERE id = '.$campaign_id;
	$r = mysqli_query($mysqli, $q);
	if ($r && mysqli_num_rows($r) > 0)
	{
	    while($row = mysqli_fetch_array($r))
	    {
  			$opens = stripslashes($row['opens']);
  			
  			$opens_array = explode(',', $opens);
  			$opens_array_ids = array();
  			$opens_array_country_match = array();
  			
  			foreach($opens_array as $opens_array_nc)
  			{
  				$e = explode(':', $opens_array_nc);
	  			array_push($opens_array_ids, $e[0]);
  			}
  			
  			foreach($opens_array as $opens_array_cts)
  			{
	  			$f = explode(':', $opens_array_cts);
	  			if(array_key_exists(1, $f)) $ff = $f[1];
	  			else $ff = '';
	  			
	  			if($ff==$action)
	  				array_push($opens_array_country_match, $f[0]);
  			}
  			
  			$opens_unique = array_unique($opens_array_country_match);
	  		$subscribers = implode(',', $opens_unique);
	    }  
	}
}

//Export
$select = 'SELECT subscribers.name, subscribers.email, subscribers.join_date, subscribers.timestamp, subscribers.list, subscribers.ip, subscribers.country, subscribers.referrer, subscribers.method, subscribers.added_via, subscribers.gdpr, lists.name as list_name  
			FROM subscribers 
			LEFT JOIN lists
			ON (subscribers.list = lists.id)
			where subscribers.id IN ('.$subscribers.') '.$additional_query;
$export = mysqli_query($mysqli, $select);
if($export)
{
	while($row = mysqli_fetch_array($export))
    {
		$name = '"'.$row['name'].'"';
		$email = '"'.$row['email'].'"';
		$list_name = '"'.$row['list_name'].'"';
		
		//Join date, IP, Country and Referrer
		$join_date = $row['join_date'];
		$last_activity = $row['timestamp'];
		$ip = $row['ip'];
		$signedup_country_code = $row['country'];
		$signedup_country = country_code_to_country($signedup_country_code);
		$referrer = $row['referrer'];
		
		//Opt-in method
		$optin_method = $row['method'];
		if($optin_method==1) $optin_method = 'Single opt-in';
		else if($optin_method==2) $optin_method = 'Double opt-in';
		
		//Added via
		$added_via = $row['added_via'];	
		if($added_via=='')
		{	
			if($join_date=='') $added_via = 'App interface';
			else $added_via = 'API';
		}
		else
		{
			if($added_via==1 || $join_date=='')
				$added_via = 'App interface';
			else if($added_via==2 || ($join_date!='' && $ip=='No data' && $signedup_country=='No data'))
				$added_via = 'API';
			else if($added_via==3)
				$added_via = 'Standard subscribe form';
		}
		
		//GDPR
		$gdpr = $row['gdpr'];
		$gdpr_status = $gdpr ? 'Yes' : 'No';
		
		//Parse join_date & last activity date
		$join_date = $join_date=='' ? '' : parse_date($join_date, 'long', false);
		$last_activity = $last_activity=='' ? '' : parse_date($last_activity, 'long', false);
		
		$data .= $name.','.$email.','.$list_name.',"'.$join_date.'","'.$last_activity.'","'.$added_via.'","'.$optin_method.'","'.$ip.'","'.$signedup_country.'","'.$signedup_country_code.'","'.$referrer.'","'.$gdpr_status.'"'."\n";
    } 
    $data = substr($data, 0, -1);
    
    $first_line = '"'._('Name').'","'._('Email').'","'._('List').'","'._('Joined').'","'._('Last activity').'","'._('Added via').'","'._('Opt-in method').'","'._('IP address').'","'._('Country').'","'._('Country code').'","'._('Signed up from').'","'._('GDPR').'"'."\n";
    
    $data = $first_line.str_replace("\r" , "" , $data);
    
	if($data == "") $data = "\n(0) Records Found!\n";
	
	header("Content-type: application/octet-stream");
	header("Content-Disposition: attachment; filename=$filename");
	header("Pragma: no-cache");
	header("Expires: 0");
	print "$data";
}
else echo _('Can\'t export CSV. There is either nothing to export, or the number of records may be too large. If it\'s the latter, try increasing MySQL\'s max_allowed_packet.'); 
?>