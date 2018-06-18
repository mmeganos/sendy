<?php ini_set('display_errors', 0);?>
<?php 
	include('includes/config.php');
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
	        fail();
	    }
	    
	    global $charset; mysqli_set_charset($mysqli, isset($charset) ? $charset : "utf8");
	    
	    return $mysqli;
	}
	//--------------------------------------------------------------//
	function fail() { //Database connection fails
	//--------------------------------------------------------------//
	    print 'Database error';
	    exit;
	}
	// connect to database
	dbConnect();
	
	include('includes/helpers/short.php');
	include('includes/helpers/locale.php');
?>
<?php 
	if(isset($_GET['f']))
	{
		$f = mysqli_real_escape_string($mysqli, short($_GET['f'], true));
		$data = json_decode(stripslashes($f));
		$brand = $data->{'brand'};
		$lid = $data->{'list'};
	}
	else
	{
		$brand = isset($_GET['i']) && is_numeric($_GET['i']) ? mysqli_real_escape_string($mysqli, $_GET['i']) : exit;
		$lid = isset($_GET['l']) ? mysqli_real_escape_string($mysqli, str_replace(' ', '', trim($_GET['l']))) : exit;
	}
	
	//Check if brand id and list id is valid and matching
	$q = 'SELECT * FROM lists WHERE app = '.$brand.' AND id = '.short($lid, true);
	$r = mysqli_query($mysqli, $q);
	if (mysqli_num_rows($r) == 0)
	{
	     echo 'Subscription form does not exist.';
	     exit;
	}
	
	//Get brand logo
	$q = "SELECT app_name, from_email, brand_logo_filename FROM apps WHERE id = '$brand'";
	$r = mysqli_query($mysqli, $q);
	if ($r && mysqli_num_rows($r) > 0)
	{
	    while($row = mysqli_fetch_array($r))
	    {
		    $app_name = $row['app_name'];
		    $from_email_full = $row['from_email'];
	    	$from_email = explode('@', $from_email_full);
			$get_domain = $from_email[1];
			$brand_logo_filename = $row['brand_logo_filename'];
	
			//Brand logo
			if($brand_logo_filename=='') $logo_image = 'https://www.google.com/s2/favicons?domain='.$get_domain;
			else $logo_image = APP_PATH.'/uploads/logos/'.$brand_logo_filename;
	    }  
	}
	
	//Set language
	$q_l = 'SELECT login.language FROM lists, login WHERE lists.id = '.short($lid, true).' AND login.app = lists.app';
	$r_l = mysqli_query($mysqli, $q_l);
	if ($r_l && mysqli_num_rows($r_l) > 0) while($row = mysqli_fetch_array($r_l)) $language = $row['language'];
	set_locale($language);
	?>
<!DOCTYPE html>
<html>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<link rel="Shortcut Icon" type="image/ico" href="<?php echo APP_PATH;?>/img/favicon.png">
		<link rel="stylesheet" type="text/css" href="<?php echo APP_PATH;?>/css/subscription.css?4" />
		<script type="text/javascript" src="<?php echo APP_PATH;?>/js/jquery-1.9.1.min.js"></script>
		<script type="text/javascript" src="<?php echo APP_PATH;?>/js/jquery-migrate-1.1.0.min.js"></script>
		<title><?php echo _('Join our mailing list');?></title>
		<script type="text/javascript">
			$(document).ready(function() {
				$("#name").focus();
			});
		</script>
	</head>
	<body>
		<div class="separator"></div>
		<div id="wrapper">
			<h2><img src="<?php echo $logo_image;?>" title=""/> <?php echo _('Join our mailing list');?></h2>
			<p>
				<form action="<?php echo APP_PATH;?>/subscribe" method="POST" accept-charset="utf-8" id="subscribe-form">
					
					<div>
						<label for="name"><?php echo _('Name');?></label>
						<input type="text" name="name" id="name"/>
					</div>
					
					<div>
						<label for="email"><?php echo _('Email');?></label>
						<input type="email" name="email" id="email"/>
					</div>
					
					<div id="hp">
						<label for="email">HP</label>
						<input type="text" name="hp" id="hp"/>
					</div>
					
					<?php 
						$q = 'SELECT custom_fields, gdpr_enabled, marketing_permission, what_to_expect FROM lists WHERE id = '.short($lid,true);
						$r = mysqli_query($mysqli, $q);
						if ($r)
						{
						    while($row = mysqli_fetch_array($r))
						    {
								$custom_fields = $row['custom_fields'];
								$gdpr_enabled = $row['gdpr_enabled'];
								$marketing_permission = $row['marketing_permission'];
								$what_to_expect = $row['what_to_expect'];
						    } 
						    if($custom_fields!='')
						    {
						    	$custom_fields_array = explode('%s%', $custom_fields);
						    	foreach($custom_fields_array as $cf)
						    	{
						    		$cf_array = explode(':', $cf);
								    echo '
								    	<div>
											<label for="'.str_replace(' ', '', $cf_array[0]).'">'.$cf_array[0].'</label>
											<input type="text" name="'.str_replace(' ', '', $cf_array[0]).'" id="'.str_replace(' ', '', $cf_array[0]).'"/>
										</div>
									';
								}
						    } 
						}
					?>
					
					<input type="hidden" name="list" value="<?php echo $lid;?>"/>
					<input type="hidden" name="subform" value="yes"/>
					
					<?php if($gdpr_enabled):?>
					<div id="gdpr">
						<input type="checkbox" name="gdpr" id="gdpr">
						<span><strong><?php echo _('Marketing permission');?></strong>: <?php if($marketing_permission==''):?>I give my consent to <?php echo $app_name;?> to be in touch with me via email using the information I have provided in this form for the purpose of news, updates and marketing.<?php else: echo $marketing_permission; endif;?></span>
						<br/><br/>
						<span><strong><?php echo _('What to expect');?></strong>: <?php if($what_to_expect==''):?>If you wish to withdraw your consent and stop hearing from us, simply click the unsubscribe link at the bottom of every email we send or contact us at <?php echo $from_email_full;?>. We value and respect your personal data and privacy. To view our privacy policy, please visit our website. By submitting this form, you agree that we may process your information in accordance with these terms.<?php else: echo $what_to_expect; endif;?></span>
					</div>
					<?php endif;?>
			
					<a href="javascript:void(0)" title="" id="submit"><?php echo _('Subscribe to list');?></a>
					
				</form>
				
				<script type="text/javascript">
					$("#subscribe-form").keypress(function(e) {
					    if(e.keyCode == 13) {
							e.preventDefault();
							$("#subscribe-form").submit();
					    }
					});
					$("#submit").click(function(e){
						e.preventDefault(); 
						$("#subscribe-form").submit();
					});
				</script>
			</p>
		</div>
	</body>
</html>