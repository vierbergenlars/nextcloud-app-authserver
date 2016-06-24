# OwnCloud Authserver login

## Installing

1. Copy this repository into `<owncloud installation directory>/apps/owncloud-app-authserver`
2. Log in as an OwnCloud admin and enable the plugin
3. Add configuration section to `<owncloud installation directory>/config/config.php`

## Configuration

```php
[...]
'user_backends' => array (
    0 => array (
        'class' => 'Studentenraad\\Owncloud\\AuthserverLogin\\Authserver_User_Backend',
        'arguments' => array (
            0 => 'https://studentenraad.be/auth/api/user.json', // Path to authserver /api/user.json
            1 => 'owncloud_studentenraad', // Authserver group that users have to be member of to be granted access to OwnCloud
            2 => 'owncloud_', // Authserver group prefix. All Authserver groups that start with this prefix will be mapped to the corresponding OwnCloud group (without the prefix)
        ),
    ),
),
[...]
```
