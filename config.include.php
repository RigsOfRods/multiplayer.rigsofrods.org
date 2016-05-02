<?php

$config = [
	'database' => [
		"host"     => "localhost",
		"name"     => "multiplayer",
		"user"     => "dbuser",
		"password" => "123456"
	],
	'protocols' => [
		'supported' => [
        	'RoRnet_2.31',
			'RoRnet_2.32',
			'RoRnet_2.33',
			'RoRnet_2.34',
			'RoRnet_2.35',
			'RoRnet_2.36',
			'RoRnet_2.37'
		]
	],
	'heartbeat' => [
		'timeout-seconds' => 90 // Timeout until server is excluded from serverlist.
	],
    'ip-lists' => [
        'official' => [
        ],
        'blacklist' => [
        ]
    ]
];

