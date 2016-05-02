<?php

require 'config.include.php';

function die_json($http_code, $message)
{
    http_response_code($http_code);
    $answer = array(
        'result'  => ($http_code == 200),
        'message' => $message
    );
    die(json_encode($answer));    
}

function check_args_or_die($_args, $req_args)
{
    foreach ($req_args as $key)
    {
        if (!isset($_args[$key]))
        {
            die_json(400, 'At least 1 required argument is missing');
        }
    }     
}

function connect_mysqli_or_die()
{
    $db_config = $config['database'];
    $mysqli = new MySQLi($db_config['host'], $db_config['user'], $db_config['password'], $db_config['name']);
    if ($mysqli->connect_errno != 0)
    {
        die_json(500, 'Server error, cannot connect to database.');
    }
    return $mysqli;
}

// -----------------------------------------------------------------------------
// GET = retrieve serverlist
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] == 'GET')
{
    // Connect DB
    $db_config = $config['database'];
    $mysqli = new MySQLi($db_config['host'], $db_config['user'], $db_config['password'], $db_config['name']);
    if ($mysqli->connect_errno != 0)
    {
        header("HTTP/1.1 500 Internal Server Error");
        die("Server error, cannot connect to database.");
    }
    
    $sql_and_version = "";
    if (isset($_GET['version']))
    {
        $sql_and_version .= ' AND `version` = ' . $mysqli->real_escape_string($_GET['version']);
    }
    
    $sql_and_heart = ' AND `last-heartbeat` >= ' . (time() - $config['heartbeat']['timeout-seconds']);
    
    // Available servers
    $sql = "
        SELECT
            `has-password`,
            `current-users`,
            `max-clients`,
            `verified`,
            `is-official`,
            `ip`,
            `port`,
            `terrain-name`,
            `name`
        FROM
            servers
        WHERE 
            `current-users` < `max-clients`
            AND `verified` > 0
            $sql_and_heart
            $sql_and_version
        ORDER BY
            `is-official`, (`currentusers`/`maxclients`) DESC, name";
    
    $result = $mysqli->query($sql);
    if ($result === false)
    {
        header("HTTP/1.1 500 Internal Server Error");
        die("Server error, cannot read database."); 
    }
    
    header('content-type: text/html; charset: utf-8');
    // HTML for compatibility with RoRConfig
    // TODO: header('content-type: application/json; charset: utf-8');
    
    print('
        <html>
        <head>
            <title>Rigs of Rods multiplayer serverlist</title>
        </head>
        <body>
        <table>
        <tr>
            <th>Players</th>
            <th>Type</th>
            <th>Name</th>
            <th>Terrain</th>
        </tr>');
        
    while ($row = $result->fetch_assoc())
    {
        $type = array();
        $url = "rorserver://";
        $name = $row['name'];
        if ($row['verified'] == 2)
        {
            array_push($type, "ranked");
        }
        if ($row['is-official'] == 1)
        {
            array_push($type, "official");
            $name = "Official: {$row['name']}";
        }
        if ($row['is-password-protected'])
        {
            array_push($type, "password");
            $url = "rorserver://user:pass@";
        }
        $url .= "{$row['ip']}:{$row['port']}/";
        $types = implode($type, ', ');
        
        print("
            <tr>
                <td>{$row['current-users']} / {$row['max-clients']}</td>
                <td>$types</td>
                <td><a href='$url'>$name</a></td>
                <td>{$row['terrain-name']}</td>
            </tr>");

    }
    
    if ($result->num_rows() == 0)
    {
        if (isset($_GET['version']))
        {
            print("<tr><td colspan='4'>No available servers found for your version.</td></tr>");
        }
        else
        {
            print("<tr><td colspan='4'>No available servers found.</td></tr>");
        }
    }
    
    print("</table>");
    
    // Full servers
    $sql = "
        SELECT
            `current-users`,
            `max-clients`,
            `is-official`,
            `terrain-name`,
            `name`
        FROM
            servers
        WHERE 
            `current-users` = `max-clients`
            AND `verified` > 0
            $sql_and_heart
            $sql_and_version
        ORDER BY
            `is-official`, name";
            
    $result = $mysqli->query($sql);
    if ($result === false)
    {
        exit("</html>"); // Whatever
    }
    
    print("<h3>Full servers</h3>");
    
    if ($result->num_rows() > 0)
    {
        print("<ul>");    
        while ($row = $result->fetch_assoc())
        {
            print("<li>{$row['name']} 
                    | {$row['terrain-name']}
                    | {$row['current-users']} / {$row['max-clients']}</li>");  
        }
        print("</ul>");
    }
    else
    {
        print("<p>None found.</p>");
    }  
    print("</html>");
    $mysqli->close();
}

// -----------------------------------------------------------------------------
// POST = Register a server
// -----------------------------------------------------------------------------
else if ($_SERVER['REQUEST_METHOD'] == 'POST') 
{
    header('content-type: application/json; charset: utf-8');
    
    $_args = $_GET;

    // Check required arguments
    check_args_or_die($_args, array('name', 'ip', 'port', 'terrain-name', 'max-clients', 'version'));
    
    // Check IP blacklist
    if (in_array($_args['ip'], $config['ip-lists']['blacklist']))
    {
        die_json(403, 'This server IP is blacklisted');
    }
    
    // Check authority
    $is_official = false;
    if (isset($_args['is-official']) && $_args['is-official'] == 1)
    {
        if (!in_array($_args['ip'], $config['ip-lists']['official']))
        {
            die_json(403, 'Your server IP is not whitelisted as official');
        }
        $is_official = true;
    }
    
    require 'serverlist-register.include.php';
    
    $is_rcon_enabled = isset($_args['is-rcon-enabled']) && $_args['is-rcon-enabled'] == 1;
    $uses_password = isset($_args['uses-password']) && $_args['uses-password'] == 1;
    
    $remote_ip = $_SERVER['REMOTE_ADDR'];
    if (isset($_SERVER["HTTP_X_FORWARDED_FOR"]))
    {
        $remote_ip = $_SERVER["HTTP_X_FORWARDED_FOR"];
    }
    $challenge = sha1(generate_random_key(255) + $remote_ip + $_args['ip'] + $_args['port'] + time());
    
    // PORTED FROM OLD SERVERLIST: wait a second to give the server a chance to come up
    sleep(2);
    $verify_result = verify_server($_args['ip'], $_args['port'], $_args['version']);
    if ($verify_result === null)
    {
        $message = 
            "Could not connect to your server and verify it's version. " .
            "Please check your Firewall or leave it as it is now to create a local only server. " .
            "Your server is NOT advertised on the Master server. " .
            "See also http://docs.rigsofrods.org/gameplay/multiplayer-server-setup/";
        die_json(503, $message);   
    }
    $verified_level = 1;
    if ($is_official || in_array($_args['ip'], $config['ip-lists']['official']))
    {
        $verified_level = 2;
    } 

    $mysqli = connect_mysqli_or_die();
    
    // Check servername is unique
    $t = time()-3600; // Ported from old serverlist scripts
    $server_name = $mysqli->real_escape_string($_args['name']);
    $uniq_sql = "SELECT * FROM servers
        WHERE name = \"{$server_name}\" and state = 0 and lastheartbeat > $t";
    $uniq_result = $mysqli->query($uniq_sql);
    if ($uniq_result->num_rows() > 0)
    {
        die_json(409, 'Your server name is already used.'); // HTTP 409 Conflict
    }
    
    // Insert the server
    function fetch_and_escape_arg($arg_name, $default_val)
    {
        if (isset($_args[$arg_name]))
        {
            return $mysqli->real_escape_string($_args[$arg_name]);
        }
        else
        {
            return $default_val;
        }
    }

    $server_desc  = fetch_and_escape_arg('description', null);
    $server_ip    = fetch_and_escape_arg('ip', null);
    $server_port  = fetch_and_escape_arg('port', null);
    $server_terrn = fetch_and_escape_arg('terrain-name', null);
    $server_max   = fetch_and_escape_arg('max-clients', 0);
    $server_ver   = fetch_and_escape_arg('version', null);
    $server_pw    = fetch_and_escape_arg('uses-password', 0);
    $server_rcon  = fetch_and_escape_arg('is-rcon-enabled', 0);
    
    $sql = "
        INSERT INTO servers (
            `name`,
            `description`,
            `ip`,
            `port`,
            `terrain-name`,
            `max-clients`,
            `last-heartbeat`,
            `start-time`,
            `version`,
            `challenge`,
            `verified`,
            `has-password`,
            `has-rcon`
        ) VALUES (
            '$server_name',
            '$server_desc',
            '$server_ip',
            $server_port,
            '$server_terrn',
            $server_max,
            NOW(),
            NOW(),
            '$server_ver',
            '$challenge',
            $verified_level,
            $server_pw,
            $server_rcon);";
            
    $result = $mysqli->query($sql);
    if ($result === false)
    {
        die_json(500, 'Failed to add server to database.');
    }
    
    // PORTED FROM OLD SERVERLIST: delete old offline servers (1 hour)
    $purge_sql = "DELETE FROM `servers` WHERE `lastheartbeat` < $t;";
    $mysqli->query($purge_sql); // We don't care about the result.
    
    $mysqli->close();
    
    http_response_code(200);
    $answer = array(
        'result'         => true,
        'message'        => 'Server sucessfully registered',
        'challenge'      => $challenge,
        'verified-level' => $verified_level
    );
    die(json_encode($answer));
}

// -----------------------------------------------------------------------------
// PUT = Heartbeat - update server status
// -----------------------------------------------------------------------------
else if ($_SERVER['REQUEST_METHOD'] == 'PUT')
{
    header('content-type: application/json; charset: utf-8');
    $_args = $_GET;
    
    check_args_or_die($_args, array('challenge', 'current-users'));
    
    $mysqli = connect_mysqli_or_die();
    
    $num_users = intval($_args['current-users']);
    $challenge = $mysqli->real_escape_string($_args['challenge']);
    $sql = 
        "UPDATE 
            `servers`
        SET
            `last-heartbeat` = NOW(),
            `current-users` = $num_users
        WHERE
            `challenge` = $challenge";
    if ($mysqli->query($sql) !== true)
    {
        die_json(500, 'Server error, failed to update database.');
    }

    die_json(200, 'Success!');
}

// -----------------------------------------------------------------------------
// DELETE = Unregister a server
// -----------------------------------------------------------------------------
else if ($_SERVER['REQUEST_METHOD'] == 'DELETE')
{
    header('content-type: application/json; charset: utf-8');
    $_args = $_GET;
    
    check_args_or_die($_args, array('challenge'));
    
    $mysqli = connect_mysqli_or_die();
    
    $sql = "DELETE FROM `servers` WHERE `challenge` = "
        . $mysqli->real_escape_string($_args['challenge']);
    if ($mysqli->query($sql) !== true)
    {
        die_json(500, 'Server error, failed to update database.');
    }
    
    die_json(200, 'Success!');
}
