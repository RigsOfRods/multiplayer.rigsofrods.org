<?php
error_reporting (E_ALL);
if (!isset($_GET['challenge']))
    die(0);

$challenge = addslashes($_GET['challenge']);
$now = time();

$dblink = mysql_connect("127.0.0.1","repouser","") or die (mysql_error());
mysql_select_db("repository", $dblink) or die(mysql_error());

$sql = "DELETE FROM servers WHERE `challenge` = '$challenge';";
$result = mysql_query($sql, $dblink) or die("error");
if (mysql_affected_rows($dblink) > 0)
    echo "ok";
else
    echo "failed";
mysql_close($dblink);
?>
