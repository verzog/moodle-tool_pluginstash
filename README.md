# Plugin Stash (tool_pluginstash)

A test/dev-Moodle convenience tool. It lists the non-core (add-on) plugins
installed on a site as a checklist and copies the ticked ones to a **stash
directory outside the code tree**. After an upgrade or rebuild, you can put them
back from the web UI with **Reinstall**, or from the command line with a CLI
script, and then finish in Notifications.

This is intended for throwaway test and development sites where you rebuild the
Moodle code tree often and want to keep your add-on plugins handy.

## Requirements

- Moodle 5.1–5.3 LTS (`MOODLE_501_STABLE` through `MOODLE_503_STABLE`)
- PHP 8.2–8.4 on Moodle 5.1; PHP 8.3–8.4 on Moodle 5.2 and 5.3

The declared support range is `$plugin->supported = [501, 503]`; older releases
(5.0 and earlier) and newer releases (5.4+) are not tested and will report as
unsupported.

The stash directory must live **outside** the Moodle code tree (`$CFG->dirroot`).
A path inside the code tree is rejected by the settings form, because a rebuild
would delete the stash along with the code it is meant to protect.

## Installation

### From a ZIP (web UI)

1. Download the plugin ZIP: the `tool_pluginstash-vX.Y.Z.zip` attached to a
   [GitHub release](https://github.com/verzog/moodle-tool_pluginstash/releases),
   or **Download Plugin Stash as zip** on another site's Plugin Stash page. If
   you use GitHub's **Source code (zip)** instead, its folder is not named
   `pluginstash`: on the install page, open **Show more…** and enter
   `pluginstash` in **Rename the root directory**.
2. Go to **Site administration → Plugins → Install plugins**.
3. Drag the ZIP into the installer, or choose it with the file picker, and
   follow the prompts to **Install plugin from the ZIP file**.
4. Moodle unpacks it to `public/admin/tool/pluginstash` and runs the upgrade;
   complete the upgrade when prompted.

### Manually (unzip into the code tree)

1. Unzip the plugin so its contents land in `public/admin/tool/pluginstash`
   under your Moodle code tree (the directory must be named `pluginstash`).
2. Log in as an administrator and visit **Site administration → Notifications**,
   or run `php admin/cli/upgrade.php`, to complete the installation.

After installing, only site administrators can use the tool. The
`tool/pluginstash:manage` capability is not given to any role by default,
because it lets the holder copy every add-on plugin into the stash and download
its source. Grant it to another role only if you mean to. Reinstalling also
needs `moodle/site:config`.

## Usage

### Stashing (web UI)

1. Go to **Site administration → Plugins → Plugin Stash** (or search the admin
   tree for "Plugin Stash").
2. Tick the add-on plugins you want to keep and choose **Stash selected
   plugins**.
3. The plugin copies each ticked plugin's directory into the stash directory and
   records it in a `manifest.json`.

The checklist only lists plugins that need stashing. A plugin already stashed at
its installed version is left out, because it appears in the **Stashed plugins**
table below the checklist. It is listed again when a newer version is installed,
so you can refresh the stashed copy, or if its stashed copy has been removed
from the stash directory. When every add-on is stashed and up to date, the page
shows a notice instead of the checklist.

The **Enable stashing** setting is a kill-switch: turn it off to lock stashing
and reinstalling without uninstalling the tool. The stash directory defaults to
`tool_pluginstash` inside your Moodle data root (`$CFG->dataroot`) and can be
changed in **Site administration → Plugins → Plugin Stash settings**.

Below the stash form, the page lists the plugins currently in the stash with a
**Download as zip** link for each. The zip contains the plugin under its own
top-level folder, so it can be re-installed through **Site administration →
Plugins → Install plugins**. This list (and the download links) stay available
even when stashing is disabled, since downloading is read-only.

The stash lives outside the code tree, so Plugin Stash checks it rather than
trusting it. A stashed plugin's `version.php` is read, never run. An entry is
only used if it sits in that plugin's own folder inside the stash (no symlinks),
and is refused if its `version.php` names a different plugin. If an entry fails
these checks, the plugin appears in the checklist again: stash it again to
repair the entry.

### Reinstalling (web UI)

When stashing is enabled, the **Stashed plugins** table has a **Reinstall**
column for site administrators (it needs `moodle/site:config` as well as
`tool/pluginstash:manage`, just like Moodle's own **Install plugins**).

1. After an upgrade or rebuild has removed some add-on plugins, open the Plugin
   Stash page.
2. Choose **Reinstall** next to a plugin, or **Reinstall all N available
   plugins** below the table.
3. Confirm with **Yes, reinstall**. The stashed copy is copied back into the
   Moodle code tree, replacing any copy already there, and you are taken to
   **Notifications** to finish the install.

Where a plugin cannot be reinstalled, the column says why instead:

- **Installed** — the code tree already has the stashed version or newer.
- **The stashed copy does not match this plugin** — the stash entry is not in
  the plugin's own folder, or its `version.php` names another plugin. Stash the
  plugin again.
- **The stashed copy is missing** — the plugin's folder has gone from the stash
  directory.
- **The site already has a newer version** — the database records a newer
  version than the stash, and Moodle does not allow downgrades.
- **The stashed version does not support this version of Moodle** — its
  `version.php` requires a newer Moodle, or marks this Moodle branch as
  incompatible, so Notifications would refuse to continue with it installed.
- **Installing plugins from the web is turned off** — the site sets
  `$CFG->disableupdateautodeploy`, which also turns off **Install plugins**.
- **This site does not recognise the plugin type** — the plugin is a
  subplugin whose owner plugin is not installed. Reinstall the owner first.
- **The web server cannot write to this plugin's folder** — the code tree, or
  part of an existing copy of the plugin, is read-only to the web server. Use
  the CLI restore below, or ask whoever manages the server to add the plugin.

### If an upgrade removes Plugin Stash itself

Plugin Stash does not stash itself: it has to be installed to bring your other
plugins back, so it cannot be restored from its own stash. If a site upgrade
replaces the whole code tree, Plugin Stash disappears along with the other
add-ons and its menu item goes with it. Your stash and settings are kept,
because they live in the Moodle data root and the database.

To recover without server access:

1. **Before you upgrade**, open the Plugin Stash page and choose **Download
   Plugin Stash as zip** under **If an upgrade removes Plugin Stash**. Keep the
   zip on your computer. (The `tool_pluginstash-vX.Y.Z.zip` from a GitHub
   release works too.)
2. After the upgrade, go to **Site administration → Plugins → Install plugins**
   and install that zip, then complete the upgrade in **Notifications**.
3. Open **Site administration → Plugins → Plugin Stash** again and use
   **Reinstall** for your other plugins.

Both steps need a site that allows installing plugins from the web (the code
tree writable by the web server and `$CFG->disableupdateautodeploy` not set).
If your host does not allow that, ask them to keep
`public/admin/tool/pluginstash` in the code they deploy.

### Restoring (CLI)

After you have rebuilt or upgraded the code tree, and **before** you run
Notifications, run this from the Moodle root (the folder that contains
`public/`):

```sh
php public/admin/tool/pluginstash/cli/restore.php --overwrite
```

Useful options:

- `--list` — list the components recorded in the manifest.
- `--component=frankenstyle_name` — restore a single component.
- `--overwrite` — overwrite destination directories that already exist.
- `--help` — full help.

The CLI restore is for administrators with server access. It runs before
Notifications, so the plugins are back in place before the upgrade checks them.
A subplugin is restored once the plugin it belongs to is back, whatever order
they appear in the manifest. An entry that fails the stash checks described
above is reported as failed and left alone.

> **Prerequisite:** `restore.php` is part of this plugin, so this plugin must
> itself be present in the rebuilt code tree before you can restore. Keep
> `public/admin/tool/pluginstash` in your project's version control, or reinstall it
> from its zip as described above, before running the command.

## Releasing

Publishing a GitHub release whose tag matches `$plugin->release` in
`version.php` (for example `v1.3.1`) runs the **Release zip** workflow, which
calls the shared `release-zip.yml` from
[verzog/moodle-ci-workflows](https://github.com/verzog/moodle-ci-workflows). It
builds `tool_pluginstash-v1.3.1.zip`, with the plugin in a `pluginstash/`
folder and without development files, and attaches it to the release. If the
tag and `version.php` disagree, the workflow fails and nothing is attached.

## Development

The **Moodle Plugin CI** workflow runs on every pull request, and on pushes to
`main` and to tags. Most of it comes from the shared `moodle-plugin-ci.yml` in
[verzog/moodle-ci-workflows](https://github.com/verzog/moodle-ci-workflows),
pinned to `@v1`:

- A quick `policy` job, kept in this repository, checks that `LICENSE` is the
  GNU GPL v3 text and that every PHP file has the `@license … GNU GPL v3 or
  later` tag.
- The Moodle matrix covers 5.1 (PHP 8.2 on PostgreSQL, PHP 8.4 on MariaDB),
  5.2 (PHP 8.3 on PostgreSQL) and 5.3 LTS (PHP 8.3 on PostgreSQL, PHP 8.4 on
  MariaDB), plus Moodle's `main` branch on PHP 8.4, which is allowed to fail.
  Tests run with the time zone set to Australia/Sydney.
- Each cell runs the Moodle code and PHPDoc checkers, validation, the upgrade
  savepoint and Mustache checks, PHPUnit and Behat (with SCSS deprecation
  checks; a failed run uploads a Behat faildump). Warnings fail the build.
  PHP Mess Detector findings are reported but do not fail it.
- PHP lint runs once, on PHP 8.2, and the Grunt check once, on Moodle 5.1,
  because neither result depends on the Moodle branch or database.
- The PHPUnit suite also fails if a capability with configuration, XSS or
  data-loss risk is given to a role by default.

## Changelog

### v1.3.1 (22/10/2026)

- **Licence file fixed.** `LICENSE` now contains the GNU GPL v3, matching the
  licence every file declares. It previously held the GNU AGPL v3 by mistake.
- **No default role for the capability.** `tool/pluginstash:manage` is no longer
  given to the Manager role on new installs, so only site administrators can use
  the tool unless one grants it. Upgrading does not change existing role
  permissions: to take it away from managers on an existing site, go to
  **Site administration → Users → Define roles → Manager**.
- **Stash contents are checked, not trusted.** A stashed plugin's `version.php`
  is read, never run. Restore, Reinstall and Download refuse a stash entry that
  is not in the plugin's own directory, is reached through a symlink, or whose
  `version.php` names a different plugin. Such a plugin is offered in the stash
  checklist again so you can repair it.
- **CLI restore handles subplugins.** A subplugin is restored after the plugin
  it belongs to, instead of failing after a rebuild.
- **CI checks** for the licence file and for risky capabilities given to a role
  by default.

### v1.3.0 (09/10/2026)

- **Reinstall from the web UI.** The Stashed plugins table has a Reinstall
  button for each plugin, plus **Reinstall all** when several are available.
  After you confirm, the stashed copy goes back into the code tree and you
  finish the install in Notifications. Where a plugin cannot be reinstalled,
  the table says why.
- **Recovery when an upgrade removes Plugin Stash.** **Download Plugin Stash as
  zip** gives you a copy to install through Install plugins, with no server
  access needed. Your stash and settings are kept.
- **Release zips.** Each GitHub release has an installable
  `tool_pluginstash-vX.Y.Z.zip` attached automatically.
- Paths in this README now use the `public/` layout of Moodle 5.1 and later.

### v1.2.1

- The stash checklist only lists plugins that need stashing.
- Supports Moodle 5.1 to 5.3 LTS.
- Fixed the download link failing because `filelib.php` was not loaded.

## Licence

This program is free software: you can redistribute it and/or modify it under
the terms of the GNU General Public License as published by the Free Software
Foundation, either version 3 of the License, or (at your option) any later
version. See [LICENSE](LICENSE) for the full text.

&copy; 2026 Vernon Spain
