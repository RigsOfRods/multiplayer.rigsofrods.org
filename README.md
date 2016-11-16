# Rigs of Rods multiplayer API

Available online at http://multiplayer.rigsofrods.org

## Serverlist

Provides a RESTful API to register and query multiplayer servers. Uses JSON.

### GET

Params:
- **version** (string, optional): RoRNet protocol version. Should have form 'RoRNet_NN'.
- **json** (bool, optional): Should output be JSON?

Output: text/html page.

- On success, HTTP 200 + HTML table of webservers (or JSON with 'json' flag)
    This is to remain compatible with older RoR releases.
- On failure, HTTP 500 + plain text error message.

### POST

Input: JSON payload

	{
	    "name": STRING,
		"ip": STRING,
		"port": INT,
		"terrain-name": STRING (Name of the terrain file, without the '.terrn2' extension),
		"max-clients": INT,
		"version": STRING (Version of RoRNet protocol. Should have form 'RoRNet_NN'.),
	    "is-rcon-enabled": BOOL (optional),
	    "uses-password": BOOL (optional),
	    "description": STRING (optional),
	    "is-official": BOOL (Advertise as official, default: false)
	}

Output: JSON in form:

    {
        'result': true,
        'message': 'Success',
        'challenge': CODE (only on success),
        'verified-level': NUMBER (only on success)
    }
    
- On success, HTTP 200 + JSON as above.
- On bad parameters, HTTP 400 + JSON with info.
- If the IP is blacklisted, HTTP 403 + JSON with info.
- If you're not allowed to register an official server, HTTP 403 + JSON.
- If serverlist fails to contact and verify the server, HTTP 503 + JSON.
- If server name already exists, HTTP 409 "Conflict" + JSON.
- On internal failure (database for example), HTTP 500 + JSON with info.

### PUT (Heartbeat)

Input: JSON payload

	{
	    "challenge: : STRING,
	    "users: [STRING] (array of user nicknames)
	}

Output: JSON in form:

    {
        'result': true,
        'message': 'Success'
    }

- On success, HTTP 200 + JSON as above.
- On bad parameters, HTTP 400 + JSON with info.
- On internal failure (database for example), HTTP 500 + JSON with info.

### DELETE

Params (submitted via URL):
- **challenge** (string)

Output: JSON in form:

    {
        'result': true,
        'message': 'Success'
    }

- On success, HTTP 200 + JSON as above.
- On bad parameters, HTTP 400 + JSON with info.
- On internal failure (database for example), HTTP 500 + JSON with info.
