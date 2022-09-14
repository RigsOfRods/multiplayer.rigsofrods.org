<?php

require 'globals.include.php';
require 'config.include.php';

$mysqli = connect_mysqli_or_die($config);

$sql_where = "";

if (isset($_GET['name'])) {
    $name = $mysqli->real_escape_string($_GET['name']);
    $sql_where .= "`name` = '$name'";
} else if (isset($_GET['ip'])) {
    $ip = $mysqli->real_escape_string($_GET['ip']);
    $sql_where .= "`ip` = '$ip'";
} else {
    die_json(400, "name or ip not specified");
}

$sql = "
SELECT
    `name`,
    `description`,
    `terrain-name`,
    `version`
    `is-official`,
    `current-users`,
    `max-clients`,    
    `country`,
    `has-password`,
    `json-userlist`
FROM
    servers
WHERE 
    $sql_where";

$result = $mysqli->query($sql);
if ($result === false || $result->num_rows == 0) {
    die_json(404, "not found");
}

$data = array();

header('content-type: application/json; charset: utf-8');
while ($row = $result->fetch_assoc()) {
    foreach ($row as $key => $value) {
        if ($key == "json-userlist")
            $data[$key] = json_decode($value);
        else if (is_numeric($value))
            $data[$key] = intval($value);
        else
            $data[$key] = $value;
    }
}
print json_encode($data);