# Changelog

All notable changes to this project will be documented in this file.

## [1.6.0] - 2024-03-25
- Added: support for Contao 5
- Changed: dropped support for Contao 4.4 - 4.9
- Changed: version requirements of dependencies

## [1.5.7] - 2022-02-14

- Fixed: array index issues in php 8+

## [1.5.6] - 2022-02-10

- Removed: usage of Utf8 bundle functions

## [1.5.5] - 2022-01-06
- Fixed: invalid tokens for notification center check

## [1.5.4] - 2021-11-22
- Fixed: invalid and unnecessary tokens for notification center

## [1.5.3] - 2021-08-27

- Added: php8 support

## [1.5.2] - 2021-08-10

- Fixed: no feedback if notification not exist (when nc is used). Now an exception is thrown

## [1.5.1] - 2021-08-10

- Fixed: missing english translation for notification center notification

## [1.5.0] - 2021-07-29

- added possibility to request a new password not only by typing in the email address but also the username
- added new yml config option `nc_notification` in order to assign a notification center notification id instead of the
  raw email for password reset
- added simple logging after password requests in system log

## [1.4.1] - 2021-07-28

- Fixed: UndefinedMethodError in BackendController

## [1.4.0] - 2021-07-27

- Added: option to add forgot password link automatically
- Added: template option to BackendLostPasswordManager::getLostPasswordLink()
- Added: license file
- Changed: removed some deprecations

## [1.3.1] - 2021-06-02

- changes for contao 4.9+

## [1.3.0] - 2020-09-15

- changed the mail transport from swift mailer to contao's email class

## [1.2.1] - 2020-06-03

- fixed email vs. username issues

## [1.2.0] - 2020-05-26

- added French translation (thanks to LupusVII)

## [1.1.1] - 2020-02-28

- fixes for Contao 4.9

## [1.1.0] - 2020-01-07

- added English translation

## [1.0.0] - 2020-01-07

- added initial version
