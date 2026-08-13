# Plugin Stash (tool_pluginstash)

A test/dev-Moodle convenience tool. It lists the non-core (add-on) plugins
installed on a site as a checklist and copies the ticked ones to a **stash
directory outside the code tree**. A CLI script copies them back after an
upgrade or rebuild, before you run Notifications.

This is intended for throwaway test and development sites where you rebuild the
Moodle code tree often and want to keep your add-on plugins handy.

## Requirements

- Moodle 5.0+ (`MOODLE_500_STABLE` or newer)
- PHP 8.2+

## Usage

### Stashing (web UI)

1. Go to **Site administration → Plugins → Plugin Stash** (or search the admin
   tree for "Plugin Stash").
2. Tick the add-on plugins you want to keep and choose **Stash selected
   plugins**.
3. The plugin copies each ticked plugin's directory into the stash directory and
   records it in a `manifest.json`.

The **Enable stashing** setting is a kill-switch: turn it off to lock the tool
without uninstalling it. The stash directory defaults to a folder inside your
Moodle data root and can be changed in the plugin settings.

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

## Licence

This program is free software: you can redistribute it and/or modify it under
the terms of the GNU General Public License as published by the Free Software
Foundation, either version 3 of the License, or (at your option) any later
version. See [LICENSE](LICENSE) for the full text.

&copy; 2026 Vernon Spain
