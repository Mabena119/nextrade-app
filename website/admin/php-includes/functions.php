<?php

function get_all_subscriptions($mentor_id)
{
    require("connect.php");
    $data = array();
    
    $q = "select * from members";
    if($mentor_id != null){ 
        $d = mysqli_real_escape_string($con, $mentor_id);
        $q = "select * from members where mentor_id='$d' ";
        
    }
    $query = mysqli_query($con, $q);
    
    
    while($u2 = mysqli_fetch_assoc($query))
    {
        array_push($data, array(
            "id" => $u2['id'],
            "email"=> $u2['email'],
            "status"=> $u2['paid'],
            "mentor_id"=> $u2["mentor_id"]
        ));
        
    }
    
    return $data;
}
function total_subscriptions($id, $active)
{
    require("connect.php");
    $query = mysqli_query($con,"select * from members where mentor_id='$id'");
    $total =0;
    while($u = mysqli_fetch_assoc($query))
    {
        if($u['paid'] && $active)
        {
           $total +=1; 
        }else if(!$active)
        {
            $total +=1; 
        }
    }
    
    return $total;
    
}

function get_all_users()
{
    require("connect.php");
    $data = array();

    // Order by id descending
    $query = mysqli_query($con, "SELECT * FROM admin ORDER BY id DESC");
    
    while ($u2 = mysqli_fetch_assoc($query))
    {
        $data[] = array(
            "id" => $u2['id'],
            "fullname" => $u2['fullname'],
            "email" => $u2['email'],
            "phone" => $u2['phone'],
            "instagram" => $u2['instagram'],
            "tiktok" => $u2['tiktok'],
            "telegram" => $u2['telegram'],
            "ea_file" => $u2['ea_file'],
            "total_keys" => $u2['total_keys'],
            "status" => $u2['status'],
            "super" => $u2['super']
        );
    }
    
    return $data;
}


/** @return array<string, array<string, mixed>|null> */
function &_nextrade_admin_row_cache(): array
{
	static $row_cache = array();
	return $row_cache;
}

function get_admin($user,$data)
{
	$cache = &_nextrade_admin_row_cache();
	$key = (string) $user;
	if ( ! array_key_exists($key, $cache)) {
		require("connect.php");
		$esc = mysqli_real_escape_string($con, $key);
		$query = mysqli_query($con, "select * from admin where email='$esc'");
		if ($query && mysqli_num_rows($query) > 0) {
			$cache[$key] = mysqli_fetch_assoc($query);
		} else {
			$cache[$key] = null;
		}
	}
	$u = $cache[$key];
	if ( ! is_array($u)) {
		return null;
	}
	return isset($u[$data]) ? $u[$data] : null;
}

/** Drop cached admin row after profile/photo updates so the next get_admin() is fresh. */
function clear_admin_row_cache(?string $user = null): void
{
	$cache = &_nextrade_admin_row_cache();
	if ($user === null) {
		$cache = array();
		return;
	}
	unset($cache[(string) $user]);
}

function nextrade_admin_is_placeholder_image(?string $imageBasename): bool
{
	$base = strtolower(trim(basename((string) $imageBasename)));
	if ($base === '' || $base === 'default.png' || $base === 'default.jpg' || $base === 'placeholder.png') {
		return true;
	}
	return false;
}

/**
 * Public URL for mentor profile photo with cache-bust, or sitelogo fallback when unset/missing.
 */
function nextrade_admin_avatar_src(
	?string $imageBasename,
	string $uploadsFs,
	string $relativeUploadsPrefix = '../uploads/'
): string {
	$fallback = '../assets/sitelogo.png';
	if (nextrade_admin_is_placeholder_image($imageBasename)) {
		return $fallback;
	}
	$base = basename((string) $imageBasename);
	$fs = rtrim($uploadsFs, '/') . '/' . $base;
	if (!is_file($fs) || (int) @filesize($fs) < 32) {
		return $fallback;
	}
	$mtime = (int) @filemtime($fs);
	$encoded = implode('/', array_map('rawurlencode', explode('/', $base)));
	return rtrim($relativeUploadsPrefix, '/') . '/' . $encoded . ($mtime > 0 ? ('?v=' . $mtime) : '');
}

/**
 * Re-encode uploaded mentor photo so iOS/browser always get a valid raster (fixes blank/white renders).
 */
function nextrade_normalize_profile_upload(string $path, string $mime): bool
{
	if (!is_file($path) || (int) @filesize($path) < 1) {
		return false;
	}
	if (!function_exists('imagecreatetruecolor')) {
		@chmod($path, 0644);
		return true;
	}

	$mime = strtolower($mime);
	if ($mime === 'image/jpeg' || $mime === 'image/jpg') {
		$img = @imagecreatefromjpeg($path);
		if ($img === false) {
			return false;
		}
		if (function_exists('exif_read_data')) {
			$exif = @exif_read_data($path);
			$orientation = (int) ($exif['Orientation'] ?? 1);
			$img = nextrade_orient_jpeg_image($img, $orientation);
		}
		$ok = imagejpeg($img, $path, 90);
		imagedestroy($img);
		if ($ok) {
			@chmod($path, 0644);
		}
		return (bool) $ok;
	}

	if ($mime === 'image/png') {
		$img = @imagecreatefrompng($path);
		if ($img === false) {
			return false;
		}
		imagealphablending($img, false);
		imagesavealpha($img, true);
		$ok = imagepng($img, $path, 6);
		imagedestroy($img);
		if ($ok) {
			@chmod($path, 0644);
		}
		return (bool) $ok;
	}

	return false;
}

/** @param resource $img */
function nextrade_orient_jpeg_image($img, int $orientation)
{
	switch ($orientation) {
		case 3:
			$rotated = imagerotate($img, 180, 0);
			if ($rotated !== false) {
				imagedestroy($img);
				$img = $rotated;
			}
			break;
		case 6:
			$rotated = imagerotate($img, -90, 0);
			if ($rotated !== false) {
				imagedestroy($img);
				$img = $rotated;
			}
			break;
		case 8:
			$rotated = imagerotate($img, 90, 0);
			if ($rotated !== false) {
				imagedestroy($img);
				$img = $rotated;
			}
			break;
	}
	return $img;
}

function get_admin_fromid($user,$data)
{
	require("connect.php");
	$query = mysqli_query($con,"select * from admin where id='$user'");
	
	if(mysqli_num_rows($query)>0)
	{

		$u = mysqli_fetch_assoc($query);
		return $u[$data];
	}

}

function total_licences($owner, $value="None")
{
	require("connect.php");
	$oid = (int) $owner;
	if($value == "Active")
		$sql = "SELECT COUNT(*) AS c FROM licences WHERE status='Active' AND owner=" . $oid;
	else if($value == "Expired")
		$sql = "SELECT COUNT(*) AS c FROM licences WHERE status='Expired' AND owner=" . $oid;
	else
		$sql = "SELECT COUNT(*) AS c FROM licences WHERE owner=" . $oid;
	$query = mysqli_query($con, $sql);
	if ($query) {
		$row = mysqli_fetch_assoc($query);
		if (isset($row['c'])) {
			return (int) $row['c'];
		}
	}
	return 0;
}
function total_EAs($owner)
{
	require("connect.php");
	$oid = (int) $owner;
	$query = mysqli_query($con, "SELECT COUNT(*) AS c FROM eas WHERE owner=" . $oid);
	if ($query) {
		$row = mysqli_fetch_assoc($query);
		if (isset($row['c'])) {
			return (int) $row['c'];
		}
	}
	return 0;
}

function licence_details($index,$value,$owner)
{
	require("connect.php");
	$query = mysqli_query($con,"select * from licences where owner='$owner' ");
	$i = 1;
	while($u = mysqli_fetch_assoc($query))
	{
		if($i == $index)
		{
			return $u[$value];
		}
		$i++;
	}
}
function licence_details_key($key,$value)
{
	require("connect.php");
	$query = mysqli_query($con,"select * from licences where k_ey='$key'");
	
	if(mysqli_num_rows($query) > 0)
	{
		$u = mysqli_fetch_assoc($query);

		return $u[$value];
	}
	
	return "Invalid Key";
}

function EA_details($index,$value, $owner)
{
	require("connect.php");
	
	$query = mysqli_query($con,"select * from eas where owner='$owner' ");
	$i = 1;
	while($u = mysqli_fetch_assoc($query))
	{
		if($i == $index)
		{
			return $u[$value];
		}
		$i++;
	}
}

function getea($id,$owner,$value)
{
	require("connect.php");
	
	$query = mysqli_query($con,"select * from eas where owner='$owner' and id='$id' ");
	if(mysqli_num_rows($query) > 0)
	{
		$u = mysqli_fetch_assoc($query);

		return $u[$value];
	}
	
	return "Invalid Key";
}

function users_from_EA($ea, $state ='None')
{
	require("connect.php");
	if($state != "None")
		$query = mysqli_query($con,"select * from licences where status='Active' and ea='$ea' ");
	else
		$query = mysqli_query($con,"select * from licences where ea='$ea' ");
	
	$total =0;
	while($u = mysqli_fetch_assoc($query))
	{
		$total +=1;
	}
	
	return $total;
}
function get_signals($ea,$page,$type)
{
    require("connect.php");
    
    $data = array();

    $skip = ($page-1)*15;
    $next = false;
    
    $query2 = mysqli_query($con,
    "select * from signals where ea='$ea' and type='$type' order by time desc
    LIMIT 15 OFFSET $skip ");
    while($u2 = mysqli_fetch_assoc($query2))
    {
        array_push($data, array(
            "id"=> $u2['id'],
            "asset"=> $u2['asset'],
            "type"=> $u2['type'],
            "action"=> $u2['action'],
            "price"=> $u2['price'],
            "tp"=> $u2['tp'],
            "sl"=> $u2['sl'],
            "results"=> $u2['results'],
            "time"=> $u2['time'],
            "latestupdate"=> $u2['latestupdate']
        ));
        
        $lastup =$u2['time'];
        $query_for = mysqli_query($con,"select * from signals where ea='$ea' and type='$type' and time<'$lastup' order by time desc");
        if(mysqli_num_rows($query_for) > 0){
            $next = true;
        }else{
            $next = false;
        }
    }

    $response =array(
        "message"=>"accept",
        "page"=>$page,
        "next"=>$next,
        "data"=>$data
    );
    return json_encode($response);
}

function get_symbols($ea)
{
    require("connect.php");
    
    $data = array();

    
    $query2 = mysqli_query($con,
    "select * from symbols where ea='$ea' ");
    while($u2 = mysqli_fetch_assoc($query2))
    {
        array_push($data, array(
            "id"=> $u2['id'],
            "name"=> $u2['name'],
        ));
        
    }

    $response =array(
        "message"=>"accept",
        "data"=>$data
    );
    return json_encode($response);
}

?>