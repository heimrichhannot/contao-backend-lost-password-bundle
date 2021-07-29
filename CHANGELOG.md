# Changelog

All notable changes to this project will be documented in this file.

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
