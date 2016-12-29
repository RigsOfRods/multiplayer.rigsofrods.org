<?php

$config = [
    'database' => [
        "host"     => "localhost",
        "name"     => "multiplayer",
        "user"     => "dbuser",
        "password" => "12345"
    ],
    'protocols' => [
        'supported' => [
            'RoRnet_2.31',
            'RoRnet_2.32',
            'RoRnet_2.33',
            'RoRnet_2.34',
            'RoRnet_2.35',
            'RoRnet_2.36',
            'RoRnet_2.37',
            'RoRnet_2.38'
        ]
    ],
    'heartbeat' => [
        'hide-timeout-sec' => 90, // Timeout until server is excluded from output
        'purge-timeout-sec' => 1500 // Timeout until server is purged from DB
    ],
    'ip-lists' => [
        'official' => [
        ],
        'blacklist' => [
        ]
    ],
    "html-serverlist" => [
        "title" => "Rigs of Rods multiplayer serverlist",
        "css" => "
            html { 
                font-family: Verdana, Geneva, sans-serif; 
            }
            table, th, td {
                border: 1px solid gray;
                border-collapse: collapse;
                padding: 5px;
            }"
    ],
    "logging" => [
        "filename" => "masterserver.log", // Set null to disable
        "verbosity" => LOG_LEVEL_DEBUG
    ]
];
