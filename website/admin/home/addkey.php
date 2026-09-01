<?php
	session_start();
	require("../php-includes/connect.php");
	require("../php-includes/functions.php");
	
	if(!isset($_SESSION['id']) || !isset($_SESSION['username']))
	{
		header("Location:../index.php");
		exit();
	}
	
	function makekey()
	{
		$key1 = substr(str_shuffle(md5(uniqid(rand(), true))), 0, 3);
		$key2 = substr(str_shuffle(md5(uniqid(rand(), true))), 0, 3);
		$key3 = substr(str_shuffle(md5(uniqid(rand(), true))), 0, 3);
		$key4 = substr(str_shuffle(md5(uniqid(rand(), true))), 0, 3);

		return strtoupper($key1."-".$key2."-".$key3."-".$key4);
	}
	
	if(isset($_POST['name']) && isset($_POST['ea']) && isset($_POST['plan']))
	{
        $name = mysqli_real_escape_string($con, $_POST['name']);
        $ea = mysqli_real_escape_string($con, $_POST['ea']);
        $plan = intval($_POST['plan']); // Ensure $plan is an integer
        $key = makekey($name, $ea, $plan);
        
        $currentDate = new DateTime(); // Get current date
        $expirationDate = $currentDate->add(new DateInterval('P' . $plan . 'D')); // Add plan duration to current date
        
        // Format the expiration date as 'Y-m-d'
        $expirationDateString = $expirationDate->format('Y-m-d');
		
		$User = get_admin($_SESSION["username"],"id");
		
		// Validate user ID
		if(empty($User) || !is_numeric($User))
		{
			header("Location:key.php?error=invalid_user");
			exit();
		}
		
		$allowed_total_keys = get_admin($_SESSION["username"],"total_keys");
		$total_keys = total_licences($User, "None");
		
		if($total_keys+0 <= $allowed_total_keys+0)
		{
    		$query = mysqli_query($con,"INSERT INTO licences (owner,ea,user,k_ey,expires,plan) VALUES ('$User','$ea','$name','$key','$expirationDateString','$plan')");
		if($query)
			{
				if (!empty($_POST['send_email']) && !empty($_POST['license_email'])) {
					try {
						require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
						auraai_email_bootstrap();
						$mentorName = (string) get_admin($_SESSION['username'], 'displayname');
						$eaName = (string) getea($ea, $User, 'name');
						auraai_email_license_key(trim($_POST['license_email']), $key, $eaName, $mentorName);
					} catch (Throwable $e) {
						error_log('[NexTradeAI Email] license key: ' . $e->getMessage());
					}
				}
				$link = "key-info.php?key=".$key;
				header("Location:".$link);
				exit();
			}
			else
			{
				header("Location:key.php?error=db_error");
				exit();
			}
		}
		else
		{
			header("Location:key.php?error=max_keys_reached");
			exit();
		}
	}
	else
	{
		header("Location:key.php?error=missing_fields");
		exit();
	}
?>