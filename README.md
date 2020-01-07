# Contao Backend Lost Password Bundle

This bundle offers a lost password function for the backend of the Contao CMS.

![alt preview](docs/lost-password.png)

## Features

- don't ever send your customers new passwords if they forgot or lost theirs again :-)
- after a password request, an email is being sent to the email address of the corresponding user offering a reset link

## Installation

1. Install via composer: `composer require heimrichhannot/contao-backend-lost-password-bundle` and update your database.
1. Copy the template `be_login.html5` from Contao's core to your project's (or project bundle's) templates folder and insert the "lost password"-link by calling `BackendLostPasswortManager->getLostPasswordLink()`. Simply do the following change:

```
<!-- ... -->
<div class="widget">
    <label for="password"><?= $this->password ?></label>
    <input type="password" name="password" id="password" class="tl_text" value="" placeholder="<?= $this->password ?>" required>
</div>

<div class="submit_container cf">
    <button type="submit" name="login" id="login" class="tl_submit"><?= $this->loginButton ?></button>
    <a href="<?= $this->route('contao_root') ?>" class="footer_preview"><?= $this->feLink ?> ›</a>
</div>
<!-- ... -->
```

to

```
<!-- ... -->
<div class="widget">
    <label for="password"><?= $this->password ?></label>
    <input type="password" name="password" id="password" class="tl_text" value="" placeholder="<?= $this->password ?>" required>
</div>

<?= System::getContainer()->get(\HeimrichHannot\BackendLostPasswordBundle\Manager\BackendLostPasswordManager::class)->getLostPasswordLink() ?>

<div class="submit_container cf">
    <button type="submit" name="login" id="login" class="tl_submit"><?= $this->loginButton ?></button>
    <a href="<?= $this->route('contao_root') ?>" class="footer_preview"><?= $this->feLink ?> ›</a>
</div>
<!-- ... -->
```

## Configuration

### Adjust the email's text

Simply override the following `$GLOBALS` entries:

```
$GLOBALS['TL_LANG']['MSC']['backendLostPassword']['messageSubjectResetPassword']
$GLOBALS['TL_LANG']['MSC']['backendLostPassword']['messageBodyResetPassword']
```