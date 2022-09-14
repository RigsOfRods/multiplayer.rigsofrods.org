<?php

require 'globals.include.php';
require 'config.include.php';

log_info("New request, method: {$_SERVER['REQUEST_METHOD']}");

// -----------------------------------------------------------------------------
// GET = retrieve serverlist
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    // Connect DB
    $db_config = $config['database'];
    $mysqli = new MySQLi($db_config['host'], $db_config['user'], $db_config['password'], $db_config['name']);
    if ($mysqli->connect_errno != 0) {
        header("HTTP/1.1 500 Internal Server Error");
        die("Server error, cannot connect to database.");
    }

    $sql_and_version = "";
    if (isset($_GET['version'])) {
        $version = $mysqli->real_escape_string($_GET['version']);
        $sql_and_version .= " AND `version` = '$version'";
    }

    check_and_purge_outdated();

    $sql_and_heart = ' AND `last-heartbeat` >= ' . (time() - $config['heartbeat']['hide-timeout-sec']);

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
            `is-official`,
            (`current-users`/`max-clients`) DESC,
            name";

    $result = $mysqli->query($sql);
    if ($result === false) {
        header("HTTP/1.1 500 Internal Server Error");
        die("Server error, cannot read database.");
    }


    if (isset($_GET["json"])) {
        header('content-type: application/json; charset: utf-8');
        $rows = array();
        while ($row = $result->fetch_assoc()) {
            foreach ($row as $key => $value) {
                if (is_numeric($value))
                    $row[$key] = intval($value);
            }
            $rows[] = $row;
        }
        print json_encode($rows);
    } else {// HTML for compatibility with RoRConfig

        header('content-type: text/html; charset: utf-8');

        print("
        <!DOCTYPE html>
        <html>
        <head>
            <title>Rigs of Rods multiplayer serverlist</title>
            <style>{$config['html-serverlist']['css']}</style>
        </head>
        <body>
        <table>
        <tr>
            <th>Players</th>
            <th>Type</th>
            <th>Name</th>
            <th>Terrain</th>
            <th>Country</th>
        </tr>");

        while ($row = $result->fetch_assoc()) {
            $type = array();
            $url = "rorserver://";
            $name = $row['name'];
            if ($row['verified'] == 2) {
                array_push($type, "ranked");
            }
            if ($row['is-official'] == 1) {
                array_push($type, "official");
                $name = "Official: {$row['name']}";
            }
            if ($row['has-password']) {
                array_push($type, "password");
                $url = "rorserver://user:pass@";
            }
            $url .= "{$row['ip']}:{$row['port']}/";
            $types = implode($type, ', ');

            $country_html = htmlspecialchars(geoip_country_name_by_name($row['ip']));
            $name_html = htmlspecialchars($name);
            $terrn_html = htmlspecialchars($row['terrain-name']);

            print("
            <tr>
                <td>{$row['current-users']} / {$row['max-clients']}</td>
                <td>$types</td>
                <td><a href='$url'>$name_html</a></td>
                <td>$terrn_html</td>
                <td>$country_html</td>
            </tr>");

        }

        if ($result->num_rows == 0) {
            if (isset($_GET['version'])) {
                print("<tr><td colspan='5'>No available servers found for your version.</td></tr>");
            } else {
                print("<tr><td colspan='5'>No available servers found.</td></tr>");
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
        if ($result === false) {
            exit("</body></html>"); // Whatever
        }

        if ($result->num_rows > 0) {
            print("<h3>Full servers</h3>");
            print("<ul>");
            while ($row = $result->fetch_assoc()) {
                print("<li>{$row['name']} 
                    | {$row['terrain-name']}
                    | {$row['current-users']} / {$row['max-clients']}</li>");
            }
            print("</ul>");
        }
        print("</body></html>");
    }


    $mysqli->close();
}

// -----------------------------------------------------------------------------
// POST = Register a server
// -----------------------------------------------------------------------------
else if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    header('content-type: application/json; charset: utf-8');

    $_args = get_json_input();

    // Check required arguments
    check_args_or_die($_args, array(
        'name',
        'ip',
        'port',
        'terrain-name',
        'max-clients',
        'version'
    ));

    // Check IP blacklist
    if (in_array($_args['ip'], $config['ip-lists']['blacklist'])) {
        log_warn("Blacklisted IP: {$_args['ip']}");
        die_json(403, 'This server IP is blacklisted');
    }

    // Check authority
    $is_official = 0;

    if (in_array($_args['ip'], $config['ip-lists']['official'])) {
        $is_official = 1;
    }

    require_once 'register-server.include.php';

    $is_rcon_enabled = isset($_args['is-rcon-enabled']) && $_args['is-rcon-enabled'] == 1;
    $uses_password = isset($_args['uses-password']) && $_args['uses-password'] == 1;

    $remote_ip = $_SERVER['REMOTE_ADDR'];
    if (isset($_SERVER["HTTP_X_FORWARDED_FOR"])) {
        $remote_ip = $_SERVER["HTTP_X_FORWARDED_FOR"];
    }
    $challenge = sha1(generate_random_key(255) + $remote_ip + $_args['ip'] + $_args['port'] + time());

    $verify_result = verify_server($config, $_args['ip'], $_args['port'], $_args['version']);
    if ($verify_result === null) {
        $message = "Could not connect to your server and verify it's version. " . "Please check your Firewall or leave it as it is now to create a local only server. " . "Your server is NOT advertised on the Master server. " . "See also http://docs.rigsofrods.org/gameplay/multiplayer-server-setup/";
        die_json(503, $message);
    }
    $verified_level = 1;

    $mysqli = connect_mysqli_or_die($config);

    check_and_purge_outdated();

    // Check servername is unique
    $t = time() - 3600; // Ported from old serverlist scripts
    $server_name = $mysqli->real_escape_string($_args['name']);
    $uniq_sql = "
        SELECT * 
        FROM `servers`
        WHERE
            `name` = '{$server_name}'
            AND `state` = 0
            AND `last-heartbeat` > $t";
    $uniq_result = $mysqli->query($uniq_sql);
    if ($uniq_result === false) {
        die_json(500, 'Cannot verify unique name against database.');
    }
    if ($uniq_result->num_rows > 0) {
        die_json(409, 'Your server name is already used.'); // HTTP 409 Conflict
    }

    // Insert the server
    function fetch_and_escape_arg($arg_name, $default_val)
    {
        global $_args, $mysqli;

        if (isset($_args[$arg_name])) {
            // PHP is pure garbage, strings needs to be converted to string
            return strval($mysqli->real_escape_string($_args[$arg_name]));
        } else {
            return strval($default_val);
        }
    }

    $server_desc = fetch_and_escape_arg('description', null);
    $server_ip = fetch_and_escape_arg('ip', null);
    $server_port = fetch_and_escape_arg('port', null);
    $server_terrn = fetch_and_escape_arg('terrain-name', null);
    $server_max = fetch_and_escape_arg('max-clients', 0);
    $server_ver = fetch_and_escape_arg('version', null);
    $server_pw = fetch_and_escape_arg('uses-password', 0);
    $server_rcon = fetch_and_escape_arg('is-rcon-enabled', 0);
    $t = time();

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
            `has-rcon`,
            `json-userlist`,
            `users`,
            `is-official`
            
        ) VALUES (
            '$server_name',
            '$server_desc',
            '$server_ip',
            '$server_port',
            '$server_terrn',
            '$server_max',
            '$t',
            '$t',
            '$server_ver',
            '$challenge',
            '$verified_level',
            '$server_pw',
            '$server_rcon',
            '[]',
            '[]',
            '$is_official');";

    $result = $mysqli->query($sql);
    if ($result === false) {
        log_error("Failed to add server to database: {$mysqli->error}");
        die_json(500, 'Failed to add server to database.');
    }

    $answer = array(
        'result' => true,
        'message' => 'Server sucessfully registered',
        'challenge' => $challenge,
        'verified-level' => $verified_level
    );
    http_response_code(200);
    log_info("Response: HTTP 200, message: {$answer['message']}");
    log_detail("JSON output:\n" . json_encode($answer, JSON_PRETTY_PRINT));
    die(json_encode($answer));
}

// -----------------------------------------------------------------------------
// PUT = Heartbeat - update server status
// -----------------------------------------------------------------------------
else if ($_SERVER['REQUEST_METHOD'] == 'PUT') {
    header('content-type: application/json; charset: utf-8');
    $_args = get_json_input();

    check_args_or_die($_args, array(
        'challenge',
        'users'
    ));

    $mysqli = connect_mysqli_or_die($config);

    $num_users = (int)count($_args['users']);
    $challenge = $mysqli->real_escape_string($_args['challenge']);
    $json_users = $mysqli->real_escape_string(json_encode($_args['users'], JSON_PRETTY_PRINT));
    $t = time();

    $sql = "UPDATE `servers`
            SET
                `last-heartbeat` = $t,
                `current-users`  = $num_users,
                `json-userlist`  = '$json_users'
            WHERE
                `challenge` = '$challenge'";

    if ($mysqli->query($sql) !== true) {
        log_error("Heartbeat: failed to update database, message: {$mysqli->error}");
        die_json(500, 'Server error, failed to update database.');
    }

    die_json(200, 'Heartbeat OK');
}

// -----------------------------------------------------------------------------
// DELETE = Unregister a server
// -----------------------------------------------------------------------------
else if ($_SERVER['REQUEST_METHOD'] == 'DELETE') {
    header('content-type: application/json; charset: utf-8');
    $_args = get_json_input();

    check_args_or_die($_args, array(
        'challenge'
    ));

    $mysqli = connect_mysqli_or_die($config);

    check_and_purge_outdated();

    $challenge = $mysqli->real_escape_string($_args['challenge']);
    $sql = "DELETE FROM `servers` WHERE `challenge` = '$challenge'";
    if ($mysqli->query($sql) !== true) {
        die_json(500, 'Server error, failed to update database.');
    }

    die_json(200, 'Success!');
}
