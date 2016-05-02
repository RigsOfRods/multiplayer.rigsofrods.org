<?php
$ip=$_SERVER["REMOTE_ADDR"];
if (isset($_SERVER["HTTP_X_FORWARDED_FOR"]))
 $ip=$_SERVER["HTTP_X_FORWARDED_FOR"];

echo $ip;
?>
