<?php

function generate_random_key($length)
{
    $key="";
    srand ((double)microtime()*1000000);
    $pattern = "1234567890abcdefghijklmnopqrstuvwxyz";
    for($i=0; $i<$length; $i++)
    {
        $key .= $pattern{rand(0,35)};
    }
    return $key;
}

// Ported from old scripts.
// I'm not quite sure what it returns ~ only_a_ptr, 05/2016
function verify_server($ip, $port, $version)
{
    $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    if ($socket === false)
    {
        return null;
    }
    
    $timeout = 10;
    $time = time();
    
    socket_set_nonblock($socket);
    while (!@socket_connect($socket, $ip, $port))
    {
        $err = socket_last_error($socket);
        if ($err == 115 || $err == 114)
        {
            if ((time() - $time) >= $timeout)
            {
              socket_close($socket);
              return null;
            }
            sleep(1);
            continue;
        }
        return null;
    }
    socket_set_block($socket);
    
    $version = "MasterServer";
    $type = 1000; // 1000 = MSG2_HELLO
    $source = 5000; // no error messages on that number
    $size = strlen($version);
    $stream = 0;
    $version_checked = ($version != null) && in_array($version, $config['protocols']['supported']);
    if ($version_checked)
    {
        $bin = pack("IIIIa12", $type, $source, $stream, $size, $version);
    }
    else
    {
        $bin = pack("IIIa12", $type, $source, $size, $version);
    }
    socket_write($socket, $bin, strlen($bin));
    
    $out = socket_read($socket, 2048);
    $len = strlen(bin2hex($out));
    if ($len == 0)
    {
        return null;
    }
    
    if($len == 24)
    {
        $result = unpack("Itype/Isource/Isize", $out);
    }  
    else if($len >= 54 && $cversion > 0)
    {
        $result = unpack("Itype/Isource/Istream/Isize", $out);
        $ucmd = "Itype/Isource/Istream/Isize/a".((int)$result['size'])."data";
        $result = unpack($ucmd, $out);
    }
    else
    {
        $result = unpack("Itype/Isource/Isize/a10version", $out);
    }
    
    socket_close($socket);
    
    if (($result['type'] > 1000) && ($result['type'] < 2000))
    {
        return $result;
    }
    else
    {
        return null;
    }
}