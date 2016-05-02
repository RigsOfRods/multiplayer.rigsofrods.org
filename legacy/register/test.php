<?php
error_reporting(0);

function verify($ip, $port, $cversion)
{
/* Create a TCP/IP socket. */
$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
if ($socket === false)
    return array(-3,array());
socket_set_nonblock($socket);

$timeout = 10;
$time = time();
while (!@socket_connect($socket, $ip, $port))
{
  $err = socket_last_error($socket);
  if ($err == 115 || $err == 114)
  {
    if ((time() - $time) >= $timeout)
    {
      socket_close($socket);
      return array(-4,array());
    }
    sleep(1);
    continue;
  }
  return array(-5,array());
}
socket_set_block($socket);

//$version = "RoRnet_2.0";
$version = "MasterServer";
$type = 1000; // 1000 = MSG2_HELLO
$source = 5000; // no error messages on that number
$size = strlen($version);
$stream = 0;
if($cversion > 0)
  $bin = pack("IIIIa12", $type, $source, $stream, $size, $version);
else
  $bin = pack("IIIa12", $type, $source, $size, $version);
socket_write($socket, $bin, strlen($bin));
$out = socket_read($socket, 2048);
$len = strlen(bin2hex($out));
if($len == 0)
    return array(-2,array());
if($len == 24)
    $result = unpack("Itype/Isource/Isize", $out);
else if($len >= 54 && $cversion > 0)
{
    $result = unpack("Itype/Isource/Istream/Isize", $out);
    $ucmd = "Itype/Isource/Istream/Isize/a".((int)$result['size'])."data";
    $result = unpack($ucmd, $out);
}
else
    $result = unpack("Itype/Isource/Isize/a10version", $out);
socket_close($socket);

//print_r($result);

if($result['type'] > 1000 && $result['type'] < 2000)
	return array(1, $result);
return array(-1,array());
}
//echo verify('188.40.228.51', 12000, 1);
?>
