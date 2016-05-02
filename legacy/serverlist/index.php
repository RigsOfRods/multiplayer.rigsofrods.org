<?php

header('content-type: text/html; charset: utf-8');
error_reporting(0);
$stime = microtime(true);

function connectcache()
{
  $memcache = new Memcache;
  $memcache->connect('localhost', 11211);
  return $memcache;
}

function connectdb()
{
  $dblink = mysql_connect("127.0.0.1","repouser","") or die (mysql_error());
  mysql_select_db("repository", $dblink) or die(mysql_error());
  return $dblink;
}

$version = "RoRnet_2.0";
if(isset($_GET['version']))
 $version = addslashes($_GET['version']);

if(in_array($version,array('RoRnet_2.33', 'RoRnet_2.32')))
{
 echo "<h1>Your client is too old.</h1><h1>Please update now.</h1><br/><br/>";
}
if($version == 'RoRnet_2.0')
 echo 'You are using an old RoR client, please consider to update.<br/>';
if(isset($_GET['lang']))
{
 $language = $_GET['lang'];
 //echo $language;
}


$usercountry="unkown";
//if (geoip_db_avail(GEOIP_COUNTRY_EDITION))
//    $usercountry=geoip_country_name_by_name($_SERVER['REMOTE_ADDR']);


$now = time();
$timeout = 90; // 90 seconds
$lastheartbeat = $now - $timeout;

//$memcache = connectcache();
//$cachename = "api.serverlist.data.".$version;
//$data = $memcache->get($cachename);
$cached = false;


// XXX: IMPORTANT: THIS DISABLES CACHING!
$data = FALSE;


if($data === FALSE)
{
  $cached = false;
  // not in cache, update!
  $dblink = connectdb();
  $versionsql = "";
  if($version != "")
    $versionsql = " and version = '$version'";
  
  $data = array();
  // officials
  $sql = "SELECT * FROM servers WHERE `lastheartbeat` >= $lastheartbeat $versionsql and verified>0 and name like 'Official%' order by (currentusers/maxclients) DESC, name;";
  //echo "<pre>$sql</pre>";
  $result = mysql_query($sql, $dblink) or die(mysql_error());
  if(mysql_num_rows($result)>0)
  {
    while($row = mysql_fetch_array($result, MYSQL_ASSOC))
    {
      array_push($data, $row);
    }
  }
  
  // non-officials
  $sql = "SELECT * FROM servers WHERE `lastheartbeat` >= $lastheartbeat $versionsql and verified>0 and name not like 'Official%' order by (currentusers/maxclients) DESC, name;";
  //echo "<pre>$sql</pre>";
  $result = mysql_query($sql, $dblink) or die(mysql_error());
  if(mysql_num_rows($result)>0)
  {
    while($row = mysql_fetch_array($result, MYSQL_ASSOC))
    {
      array_push($data, $row);
    }
  }
   
  

  //$memcache->set($cachename, $data, false, 360);
  mysql_close($dblink);
}
//$memcache->close();


//echo "We got nominated in the Sourceforge Community Choice awards!<br>Please vote there: http://sourceforge.net/community/cca09/vote/<br>(you can find RoR under Games)<p><br>";

$fullserver = array();
$newformat = true; //(in_array($version, array("RoRnet_2.1","RoRnet_2.2","RoRnet_2.3","RoRnet_2.34",'"RoRnet_2.33"')));
if($newformat)
{
 //echo "<table border='1'><tr><td><b>Players</b></td><td><b>Type</b></td><td><b>Location</b></td><td><b>Name</b></td><td><b>Terrain</b></td></tr>";
 echo "<table border='1'><tr><td><b>Players</b></td><td><b>Type</b></td><td><b>Name</b></td><td><b>Terrain</b></td></tr>";
}
foreach($data as $key=>$row)
{
    if($row['currentusers'] == $row['maxclients'])
    {
        array_push($fullserver, $row);
	continue;
    }
    $isofficial=false;
    $name = substr($row['name'], 0, 20);
    $opos = strpos(strtolower($name), "official");
    if($opos === false) {
     // not found
    } else {
        $isofficial=true;
    }

    $rcon = $row['rconenabled'];
    $verified = $row['verified'];
    $pw = $row['passwordprotected'];
    $uptime = (int)(($now - intval($row['starttime'])) / 60);
    $ipport = $row['ip'].":".$row['port'];
    $terrain = $row['terrainname'];
    $id = $row['id'];
    $country2 = strtolower($row['country2']);
    $country = $row['country'];
    if($country=="unkown" || $country == "")
      $country="";
    $occ = $row['currentusers']." / ".$row['maxclients'];
    $joinable = ($row['currentusers'] < $row['maxclients']);
    if($newformat)
    {
        echo "<tr>";
	echo "<td valign='middle'>${occ}</td>";
    	echo "<td valign='middle'>";
	$fl = array();
	if($verified == 2) array_push($fl, "ranked");
        if($isofficial) array_push($fl, "official");
        if($pw) array_push($fl, "password");
	echo implode($fl, ',');
	echo "</td>";
    	//echo "<td valign='middle'>$country</td>";
	if($pw == 1)
		echo "<td valign='middle'><a href='rorserver://user:pass@$ipport/'>$name</a></td>";
	else
		echo "<td valign='middle'><a href='rorserver://$ipport/'>$name</a></td>";
	echo "<td valign='middle'>$terrain</td>";
	echo "</tr>";
    }else
    	//echo "<a href='rorserver://$ipport/$terrain'>$name, $terrain</a> $occ $country<br>";
    	echo "<a href='rorserver://$ipport/$terrain'>$name, $terrain</a> $occ<br>";
}
if($newformat)
{
 echo '</table>';
}
if(sizeof($fullserver) > 0)
{
    echo "<h3>Full server</h3>";
    foreach($fullserver as $key=>$row)
    {
        $name = $row['name'];
	$terrain = $row['terrainname'];
	$occ = $row['currentusers']." / ".$row['maxclients'];
        echo "$name, $terrain $occ<br>";
    }

}
if ($newformat && count($data)>0) { 

/*
?>
<h3>Legend</h3>
<img src='official.gif'> official server<br>
<img src='rcon.gif'> administrated server<br>
<img src='pw.gif'> password protected server<br>
<?
$dur = microtime(true) - $stime;
if($cached)
  echo "<br><small>(cached, ".round($dur,5)." seconds needed)</small>";
else
  echo "<br><small>(uncached: ".round($dur,5)." seconds needed)</small>";

*/
} 
if(count($data) == 0)
{
 echo "<h3>No servers running for your version ($version)</h3>Please try again later<br>(Or try to setup your own server)<p/>&nbsp;<p/>&nbsp;<p/>";
 echo "<h3><b>THIS IS NO ERROR</b></h3> Please do not file bug reports about this";
}
?>
