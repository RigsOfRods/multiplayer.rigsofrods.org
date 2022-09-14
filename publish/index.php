<?php

require 'globals.include.php';
require 'config.include.php';

$data = [
    "num-online-servers" => 0,
    "num-online-players" => 0,
    "serverlist-html" => []
];

$db_config = $config['database'];
$mysqli = new MySQLi($db_config['host'], $db_config['user'], $db_config['password'], $db_config['name']);

if ($mysqli->connect_errno != 0) {
    $data["serverlist-html"] = "<p><strong>Sorry</strong>, a database error occurred.</p>";
} else {
    $sql_and_heart = ' AND `last-heartbeat` >= ' . (time() - $config['heartbeat']['hide-timeout-sec']);
    // Available servers
    $sql = "   
        SELECT
            `has-password`,             `current-users`,
            `max-clients`,              `verified`,
            `is-official`,              `ip`,
            `port`,                     `terrain-name`,
            `name`
        FROM
            servers
        WHERE 
            `verified` > 0
            $sql_and_heart
        ORDER BY
            `is-official`,
            (`current-users`/`max-clients`) DESC,
            name";

    $result = $mysqli->query($sql);
    if ($result === false) {
        $data["serverlist-html"] = "<p><strong>Sorry</strong>, a database error occurred.</p>";
    } else if ($result->num_rows == 0) {
        $data["serverlist-html"] = "<p>No servers online at the moment.</p>";
    } else {
        $num_players_total = 0;
        $servlist_html = ["<table class='w3-table w3-striped w3-bordered w3-border'>
        <tr class='w3-orange'>
            <th><span class='fa fa-user    w3-margin-right'></span>Players</th>
            <th><span class='fa fa-info    w3-margin-right'></span>Type</th>
            <th><span class='fa fa-gamepad w3-margin-right'></span>Name</th>
            <th><span class='fa fa-road    w3-margin-right'></span>Terrain</th>
        </tr>"];

        while ($row = $result->fetch_assoc()) {
            $type = array();
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
            }
            $types = implode(', ', $type);
            $name_html = htmlspecialchars($name);
            $terrn_html = htmlspecialchars($row['terrain-name']);

            $servlist_html[] =
                "<tr>
                <td>{$row['current-users']} / {$row['max-clients']}</td>
                <td>$types</td>
                <td>$name_html</td>
                <td>$terrn_html</td>
            </tr>";

            $num_players_total += $row['current-users'];
        }

        $servlist_html[] = "</table>";
        $data["serverlist-html"] = implode($servlist_html);
        $data["num-online-players"] = $num_players_total;
        $data["num-online-servers"] = $result->num_rows;
    }
}

?>
<!DOCTYPE html>
<html lang=en>
<head>
    <meta charset=utf-8>
    <meta http-equiv=x-ua-compatible content="ie=edge">
    <meta name=viewport content="width=device-width, initial-scale=1"/>
    <meta name=robots content="noindex, nofollow">
    <link rel="stylesheet" href="http://www.w3schools.com/lib/w3.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
        #ror-mp-dev-info-box {
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
        }

        #ror-logo {
            height: 38px;
        }
    </style>
</head>
<body>
<!-- Navbar -->
<div class="w3-display-container">
    <ul class="w3-navbar w3-left-align w3-orange">
        <li class="w3-hide-medium w3-hide-large w3-opennav w3-right">
            <a class="w3-hover-khaki w3-theme-d2" href="javascript:void(0);" onclick="ror_toggle_mobile_nav()"><i
                        class="fa fa-bars"></i></a>
        </li>
        <li><a href="#" class="w3-orange w3-padding-0"><img id="ror-logo"
                                                            src="http://rigsofrods.org/images/logos/rorlogo.png"
                                                            alt="Logo"></a></li>
        <li class="w3-hide-small w3-right"><a href="https://github.com/RigsOfRods" class="w3-hover-khaki"><span
                        class="fa fa-wrench   w3-margin-right"></span>Development</a></li>
        <li class="w3-hide-small w3-right"><a href="http://docs.rigsofrods.org/" class="w3-hover-khaki"><span
                        class="fa fa-book     w3-margin-right"></span>Docs</a></li>
        <li class="w3-hide-small w3-right"><a href="http://forum.rigsofrods.org/" class="w3-hover-khaki"><span
                        class="fa fa-comments w3-margin-right"></span>Forum</a></li>
        <li class="w3-hide-small w3-right"><a href="http://www.rigsofrods.org/" class="w3-hover-khaki"><span
                        class="fa fa-home     w3-margin-right"></span>Home</a></li>
    </ul>

    <!-- Navbar on small screens -->
    <div id="ror_mobile_nav" class="w3-hide w3-hide-large w3-hide-medium">
        <ul class="w3-navbar w3-left-align w3-gray">
            <li><a href="http://www.rigsofrods.org/"> <span class="fa fa-home     w3-margin-right"></span>Home</a></li>
            <li><a href="http://forum.rigsofrods.org/"> <span class="fa fa-comments w3-margin-right"></span>Forum</a>
            </li>
            <li><a href="http://docs.rigsofrods.org/"> <span class="fa fa-book     w3-margin-right"></span>Docs</a></li>
            <li><a href="https://github.com/RigsOfRods"><span class="fa fa-wrench   w3-margin-right"></span>Development</a>
            </li>
        </ul>
    </div>
</div>

<header class="w3-container w3-center">
    <h1>Rigs of Rods - Multiplayer portal</h1>

    <p><?php echo (int)$data["num-online-servers"] ?> servers / <?php echo $data["num-online-players"] ?> players
        online.</p>

    <div id="ror-mp-dev-info-box" class="w3-panel w3-teal w3-round">
        <h2>Under development</h2>
        <p>For info and discussion on multiplayer features, see <a href="http://forum.rigsofrods.org/thread-503.html">this
                forum topic</a>.</p>
        <p>Contributors welcome! Find the development repository at <a
                    href="https://github.com/RigsOfRods/multiplayer.rigsofrods.org">GitHub</a>.</p>
    </div>
</header>

<section class="w3-container w3-center">
    <h1>Server list</h1>

    <?php echo $data["serverlist-html"] ?>
</section>

<script>
    function ror_toggle_mobile_nav() {
        var x = document.getElementById("ror_mobile_nav");
        if (x.className.indexOf("w3-show") == -1) {
            x.className += " w3-show";
        } else {
            x.className = x.className.replace(" w3-show", "");
        }
    }
</script>
</body>
</html>
