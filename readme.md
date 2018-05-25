# Nextcloud Authserver login

## Installing

1. Copy this repository into `<nextcloud installation directory>/apps/authserver-login`
2. Log in as an nextcloud admin and enable the plugin
3. Add configuration section to `<nextcloud installation directory>/config/config.php`

## Configuration

### Login with OAuth

To make use of OAuth login, a full configuration is required

```php
[...]
'authserver_login_client_id' => '', // OAuth application client id. 
'authserver_login_client_secret' => '', // OAuth application client secret
'authserver_login_base_url' => 'https://studentenraad.be/auth', // Path the the authserver installation
'authserver_login_required_group' => 'nextcloud_users', // Authserver group that users have to be member of to be granted access to OwnCloud
'authserver_login_group_prefix' => 'nextcloud_', // Authserver group prefix. All Authserver groups that start with this prefix will be mapped to the corresponding OwnCloud group (without the prefix)
'authserver_login_auto_redirect' => false, // Automatically redirect from the login page to OAuth login (defaults to false)
'authserver_login_label' => 'Authserver', // Label for the "alternate login" button (defaults to "Authserver")
'authserver_login_scopes' => 'profile:username profile:realname profile:email profile:groups' // OAuth scopes to request (defaults to 'profile:username profile:realname profile:email profile:groups')
'user_backends' => array (
    0 => array (
        'class' => 'Studentenraad\\Owncloud\\AuthserverLogin\\Authserver_User_Backend',
        'arguments' => array (
            0 => 'https://studentenraad.be/auth/api/user.json', // Path to authserver /api/user.json
        ),
    ),
),
[...]
```

### Login with username/password

To make use of the username/password login of Nextcloud, and have it passed to Authserver from the backend,
you only need to configure the groups and the user backend.

If `authserver_login_client_id` is not present, OAuth authentication will not be set up.


```php
[...]
'authserver_login_required_group' => 'nextcloud_users', // Authserver group that users have to be member of to be granted access to OwnCloud
'authserver_login_group_prefix' => 'nextcloud_', // Authserver group prefix. All Authserver groups that start with this prefix will be mapped to the corresponding OwnCloud group (without the prefix)
'user_backends' => array (
    0 => array (
        'class' => 'Studentenraad\\Owncloud\\AuthserverLogin\\Authserver_User_Backend',
        'arguments' => array (
            0 => 'https://studentenraad.be/auth/api/user.json', // Path to authserver /api/user.json
        ),
    ),
),
[...]
```