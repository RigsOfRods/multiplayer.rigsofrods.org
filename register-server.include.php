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

function verify_server($config, $ip, $port, $version)
{
    $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    if ($socket === false)
    {
        return null;
    }
    
    $timeout = 10;
    $time = time();
    // Means 'in progress', see https://bobobobo.wordpress.com/2008/11/09/
    define('WINSOCK_WSAEWOULDBLOCK', 10035);
    // Means 'already connected'
    define('WINSOCK_WSAEISCONN', 10056);
    
    // See: http://stackoverflow.com/a/9761659
    socket_set_nonblock($socket);
    while (!@socket_connect($socket, $ip, $port))
    {
        $err = socket_last_error($socket);
        if ($err == WINSOCK_WSAEISCONN)
        {
            break; // On Windows, socket_connect() won't return TRUE
        }
        elseif (($err != WINSOCK_WSAEWOULDBLOCK) && 
                ($err != SOCKET_EINPROGRESS) &&
                ($err != SOCKET_EALREADY))
        {
            socket_close($socket);
            return null;
        }    

        if ((time() - $time) >= $timeout)
        {
            socket_close($socket);
            return null;
        }
        usleep(500000);        
    }
    socket_set_block($socket);
    
    $poke_payload = "MasterServer";
    $poke_msg = 1000; // RoRNet: MSG2_HELLO
    $poke_source = 5000; // (Magic) no error messages on that number
    $poke_payload_length = strlen($poke_payload);
    $poke_stream_id = 0;
    $version_checked = ($version != null) && in_array($version, $config['protocols']['supported']);
    if ($version_checked)
    {
        $bin = pack("IIIIa12", 
            $poke_msg, $poke_source, $poke_stream_id, $poke_payload_length, $poke_payload);
    }
    else
    {
        $bin = pack("IIIa12", $poke_msg, $poke_source, $poke_payload_length, $poke_payload);
    }
    $written = socket_write($socket, $bin, strlen($bin));
    if ($written === false)
    {
        socket_close($socket);
        return null;
    }
    
    $out = socket_read($socket, 2048);
    $len = strlen(bin2hex($out));
    if ($len == 0)
    {
        socket_close($socket);
        return null;
    }
    
    if($len == 24)
    {
        $result = unpack("Itype/Isource/Isize", $out);
    }  
    else if($len >= 54 && $version_checked)
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