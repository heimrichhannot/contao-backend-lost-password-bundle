# Contao Backend Lost Password Bundle

This bundle offers a lost password function for the backend of the Contao CMS.

![alt preview](docs/lost-password.png)

## Features

- Never send new passwords to your customers again if they have forgotten their old ones. :-)
- After requesting a new password, a password reset link is sent to the user's email.
- Select a mailer transport for outgoing mails in the settings.

## Installation

Install the bundle via composer:

```shell
composer require heimrichhannot/contao-backend-lost-password-bundle
```

## Customize

### Use Notification center

You can use [Notification Center](https://github.com/terminal42/contao-notification_center) to send the password request.

> [!IMPORTANT]
> Only notification center v1 is currently integrated.
> Working on support for notification center v2.

> [!WARNING]
> This will be changed before the first stable v2 release.

1. Create a notification of type `User: Lost password` with `##recipient_email##` as recipient and content that contains `##link##` (the link to the password reset page).
    You can use additional token: `##domain##` and user data withing `##user_*##`.
2. Set the id of the notification in your project configuration in `huh_backend_lost_password.nc_notification`.

```yaml
# config/config.yml
huh_backend_lost_password:
    nc_notification: 5
```

### Adjust the email's text

**Hint: You can also set a notification center message by setting the id in your config.yml (see below).**

Simply override the following `$GLOBALS` entries:

```
$GLOBALS['TL_LANG']['MSC']['backendLostPassword']['messageSubjectResetPassword']
$GLOBALS['TL_LANG']['MSC']['backendLostPassword']['messageBodyResetPassword']
```

## Configuration reference

```yaml
# Default configuration for extension with alias: "huh_backend_lost_password"
huh_backend_lost_password:
    # Automatically add the request new password link to the backend login page.
    # Default: true 
    add_to_template: true

    # The numeric ID of the notification center notification which is sent for resetting the password.
    # Deprecated. Will be removed in the first stable v2 release.
    nc_notification: null
```
