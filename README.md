# Plugin Stash (tool_pluginstash)

A test/dev-Moodle convenience tool. It lists the non-core (add-on) plugins
installed on a site as a checklist and copies the ticked ones to a **stash
directory outside the code tree**. A CLI script copies them back after an
upgrade or rebuild, before you run Notifications.

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

1. Download the plugin ZIP (from a release, or from the **Download as zip** link
   on another site's stash page).
2. Go to **Site administration → Plugins → Install plugins**.
3. Drag the ZIP into the installer, or choose it with the file picker, and
   follow the prompts to **Install plugin from the ZIP file**.
4. Moodle unpacks it to `admin/tool/pluginstash` and runs the upgrade; complete
   the upgrade when prompted.

### Manually (unzip into the code tree)

1. Unzip the plugin so its contents land in `admin/tool/pluginstash` under your
   Moodle code tree (the directory must be named `pluginstash`).
2. Log in as an administrator and visit **Site administration → Notifications**,
   or run `php admin/cli/upgrade.php`, to complete the installation.

Either way, after installing, grant the `tool/pluginstash:manage` capability to
the roles that should use the tool (it is assigned to the Manager archetype by
default).

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

The **Enable stashing** setting is a kill-switch: turn it off to lock the tool
without uninstalling it. The stash directory defaults to a folder inside your
Moodle data root and can be changed in the plugin settings.

Below the stash form, the page lists the plugins currently in the stash with a
**Download as zip** link for each. The zip contains the plugin under its own
top-level folder, so it can be re-installed through **Site administration →
Plugins → Install plugins**. This list (and the download links) stay available
even when stashing is disabled, since downloading is read-only.

### Restoring (CLI)

After you have rebuilt or upgraded the code tree, and **before** you run
Notifications:

```sh
php admin/tool/pluginstash/cli/restore.php --overwrite
```

Useful options:

- `--list` — list the components recorded in the manifest.
- `--component=frankenstyle_name` — restore a single component.
- `--overwrite` — overwrite destination directories that already exist.
- `--help` — full help.

Restore is CLI-only by design: it must run before Moodle boots the plugins, so
it cannot live in the web UI.

> **Prerequisite:** `restore.php` is part of this plugin, so this plugin must
> itself be present in the rebuilt code tree before you can restore. It excludes
> itself from stashing precisely because you cannot bootstrap the restore script
> from a stash it would have to already be running to unpack. Keep
> `admin/tool/pluginstash` in your project's version control (or reinstall it
> from the Moodle plugins directory) as the first step after a rebuild; then run
> the command above to restore the remaining add-on plugins.

## Licence

This program is free software: you can redistribute it and/or modify it under
the terms of the GNU General Public License as published by the Free Software
Foundation, either version 3 of the License, or (at your option) any later
version. See [LICENSE](LICENSE) for the full text.

&copy; 2026 Vernon Spain
