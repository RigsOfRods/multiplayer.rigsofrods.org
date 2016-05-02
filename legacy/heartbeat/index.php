<?php
error_reporting (E_ALL);
if ( (isset($_GET['challenge']) && isset($_GET['currentusers'])) || (isset($GLOBALS['HTTP_RAW_POST_DATA']) && strlen($GLOBALS['HTTP_RAW_POST_DATA'])>0))
{
    // ok
} else {
    echo "failed:1";
    die(0);
}

$useget = isset($_GET['challenge']);


$bannedips = array('75.39.193.51');
if(isset($_GET['ip']))
{
$ip = addslashes($_GET['ip']);
if(in_array($ip, $bannedips))
{
	echo 'banned';
	die(0);
}
}
 
$users = "";
if($useget)
{
 $challenge = addslashes($_GET['challenge']);
 $currentusers = intval($_GET['currentusers']);
} else {
 // new format!
 $ar = explode("\n", trim($GLOBALS['HTTP_RAW_POST_DATA']));
 //print_r($ar);
 if(sizeof($ar) < 2)
 {
   echo "failed:2";
   die(0);
 }
 $challenge = addslashes($ar[0]);
 $version = intval($ar[1]);
 $currentusers = intval($ar[2]);
 if($currentusers>0)
 {
  for($i=3;$i<$currentusers+3;$i+=1)
  {
   $users .= $ar[$i]."\n";
  }
  $users = addslashes($users);
  $users = " `users` = '$users', "; 
 }else {
  // no users at all
  $users = " `users` = '', ";
 }
}

$now = time();

$dblink = mysql_connect("127.0.0.1","repouser","") or die (mysql_error());
mysql_select_db("repository", $dblink) or die(mysql_error());

$sql = "UPDATE servers SET
        `lastheartbeat` = $now, $users 
        `currentusers` = $currentusers
        WHERE `challenge` = '$challenge';";
$result = mysql_query($sql, $dblink) or die(mysql_error());
if (mysql_affected_rows($dblink) > 0)
    echo "ok";
else
    echo "failed:3";
?>
