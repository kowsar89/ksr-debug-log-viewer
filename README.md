# KSR Debug Log Viewer

A WordPress plugin to view and manage your `debug.log` right from the admin, no FTP needed.

![KSR Debug Log Viewer](assets/screenshot.png)

## Features

- Shows your `debug.log` in a full-screen, code-editor-style view
- Lets you edit and save the log, or delete it with one click
- Lets you turn `WP_DEBUG`, `WP_DEBUG_LOG` and `WP_DEBUG_DISPLAY` on or off with simple switches
- Minimal and lightweight: nothing is saved to the database
- Uses only jQuery, no JS frameworks

## How to use

1. Install and activate the plugin.
2. Click **Debug Log** in the admin bar at the top of any wp-admin screen.

## Multisite support

All sites in a network share one `debug.log` and one `wp-config.php`, so the plugin treats them as network-wide:

- **Network-activated:** the page lives in Network Admin, and the admin bar link on every site's admin leads there.
- **Activated on a single site:** the page lives in that site's admin.
- In both cases, only super admins can open the page.
