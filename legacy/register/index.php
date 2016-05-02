<?php
//error_reporting (1);
require_once('test.php');
function randomkeys($length)
{
    $key="";
    srand ((double)microtime()*1000000);
    $pattern = "1234567890abcdefghijklmnopqrstuvwxyz";
    for($i=0;$i<$length;$i++)
    {
        $key .= $pattern{rand(0,35)};
    }
    return $key;
}
if (!isset($_GET['name']) || !isset($_GET['description']) || !isset($_GET['ip']) || !isset($_GET['port']) || !isset($_GET['terrainname']) || !isset($_GET['maxclients']) || !isset($_GET['version']))
{
	echo "error\ninvalid arguments";
    die(0);
}


$officialips = array();
$bannedips = array();


$name = addslashes($_GET['name']);
$name = str_replace(' ', '_', strip_tags(urldecode($name)));
$description = addslashes($_GET['description']);
$ip = addslashes($_GET['ip']);

if(in_array($ip, $bannedips))
{
	echo "error\nbanned";
	die(0);
}

$format = 1;
if(isset($_GET['format']))
 $format=intval($_GET['format']);

$country="unkown";
/*
$country2="";
if (geoip_db_avail(GEOIP_COUNTRY_EDITION))
{
	$country = geoip_country_name_by_name($ip);
	$country2=geoip_country_code_by_name($ip);
}
*/

$port = intval($_GET['port']);
$terrainname = addslashes($_GET['terrainname']);
$terrainname = str_replace(' ', '_', strip_tags(urldecode($terrainname)));
$maxclients = intval($_GET['maxclients']);
$pw=0;
if(isset($_GET['pw']))
    $pw = intval($_GET['pw']);
$rcon=0;
if(isset($_GET['rcon']))
    $rcon = intval($_GET['rcon']);
$version = addslashes($_GET['version']);
$now = time();

/*
if($maxclients > 16)
{
 echo "error\nYou may not register online servers with above 16 players, this is silly!";
 die(0);
}
*/
$opos = strpos(strtolower($name), "offi");
if($opos === false) {
 // not found
} else {
 if(in_array($ip, $officialips)) 
 {
 	//echo "ok";
 }
 else
 {
  if($format == 1)
  {
   echo "error:you may not register any official servers                                                                                                                                                                                                                                                                                                                               "; // we need those spaces to get a charcount > 100
   die(0);
  }else if($format == 2)
  {
   echo "error\nyou may not register any official servers";
   die(0);
  }
 }
}

$rip=$_SERVER['REMOTE_ADDR'];
if (isset($_SERVER["HTTP_X_FORWARDED_FOR"]))
 $rip=$_SERVER["HTTP_X_FORWARDED_FOR"];

$challenge = sha1(randomkeys(255) + $rip + $ip + $port + time());

///////////////////////////////////////////////////////////////////////////////
// wait a second to give the server a chance to come up
sleep(2);
$cversion=0;
if(in_array($version, array('RoRnet_2.31', 'RoRnet_2.32', 'RoRnet_2.33', 'RoRnet_2.34', 'RoRnet_2.35', 'RoRnet_2.36', 'RoRnet_2.37')))
 $cversion=1;

$verified = 0;

$res = verify($ip, $port, $cversion);

$verified = $res[0];

// TODO: analyze $returnarr

///////////////////////////////////////////////////////////////////////////////

if($verified<0 && $format == 1)
{
  echo "error:cannot connect from the master server. \nPlease check your Firewall or leave it as it is now to create a local only server. \nYour server is NOT advertised on the Master server. \nYou should have a look at http://wiki.rigsofrods.com/index.php?title=Port_Forwardings_for_Multiplayer";
  die(0);
}else if($verified<0 && $format == 2)
{
  echo "error\ncannot connect from the master server. Please check your Firewall or leave it as it is now to create a local only server. Your server is NOT advertised on the Master server. You should have a look at http://wiki.rigsofrods.com/index.php?title=Port_Forwardings_for_Multiplayer";
  die(0);
}

if($verified && in_array($ip, $officialips))
{
 $verified = 2;
}

$dblink = mysql_connect("127.0.0.1","repouser","");
if(!$dblink)
{
	echo "error\ndatabase error 1";
	die(0);
}
$db_selected = mysql_select_db("repository", $dblink);
if (!$db_selected)
{
	echo "error\ndatabase error 2";
	die(0);
}
$t = time()-3600;
$sql2= "select * from servers where name = \"{$name}\" and state = 0 and lastheartbeat > ${t}";
$res2 = mysql_query($dblink, $sql2);
if(mysql_num_rows($res2)>0 && $format == 1)
{
 echo "error! this servername is already used. Please use unique names!                                                                                                                                                 ";
 die(0);
}else if (mysql_num_rows($res2)>0 && $format == 2)
{
 echo "error\nthis servername is already used. Please use unique names!";
 die(0);
}
$sql = "INSERT INTO servers (
        `name`,
        `description`,
        `ip`,
        `port`,
        `terrainname`,
        `maxclients`,
        `lastheartbeat`,
        `currentusers`,
        `starttime`,
        `version`,
        `state`,
        `challenge`,
	`verified`,
	`country`,
	`country2`,
	`passwordprotected`,
	`rconenabled`
        ) VALUES (
        '$name',
        '$description',
        '$ip',
        $port,
        '$terrainname',
        $maxclients,
        $now,
        0,
        $now,
        '$version',
        0,
        '$challenge',
	$verified,
	'$country',
	'$country2',
	$pw,
	$rcon);";
//echo "$sql";
$result = mysql_query($sql, $dblink);
if(!$result)
{
	echo "error\ndatabase error 3";
	die(0);
}

//delete old offline servers (1 hour)
$sql = "delete from servers where lastheartbeat < ${t};";
$result = mysql_query($sql, $dblink);
if(!$result)
{
	echo "error\ndatabase error 3";
	die(0);
}
mysql_close($dblink);

if($format == 2)
{
	echo "ok\n";
	echo "server sucessfully registered at master server!\n";
	echo "$challenge\n";
	echo "$verified\n";
} else if ($format == 1)
{
	echo "$challenge";
} else 
{
	echo "error\nunknown format";
}
?>
