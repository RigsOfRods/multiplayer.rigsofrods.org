# Rigs of Rods multiplayer API

Will become available at multiplayer.rigsofrods.org

## Serverlist

Provides a RESTful API to register and query multiplayer servers. Uses JSON.

### GET

Params:
- **version (string, optional)**: RoRNet protocol version. Should have form 'RoRNet_NN'.

Output: text/html page.

- On success, HTTP 200 + table of webservers.
    This is to remain compatible with older RoR releases.
- On failure, HTTP 500 + plain text error message.

### POST

Params (submitted via URL):
- **name** (string): Server name. 
- **ip** (string): Server IP address.
- **port** (int): Server network port.
- **terrain-name** (string): Name of the terrain file, without the '.terrn2' extension.
- **max-clients** (int): Max number of clients.
- **version** (string): Version of RoRNet protocol. Should have form 'RoRNet_NN'.
- **is-official** (int{0/1}, optional): Advertise as official (default disabled).
- **description** (string, optional)
- **is-rcon-enabled** (int{0/1}, optional)
- **uses-password** (int{0/1}, optional)

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

Params (submitted via URL):
- **challenge** (string)
- **current-users** (int)

Output: JSON in form:

    {
        'result': true,
        'message': 'Success'
    }

- On success, HTTP 200 + JSON as above.
- On bad parameters, HTTP 400 + JSON with info.
- On internal failure (database for example), HTTP 500 + JSON with info.