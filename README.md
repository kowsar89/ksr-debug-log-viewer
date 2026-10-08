# Debug Log Viewer

View, edit and delete the WordPress `debug.log` file in an editor-style screen, and switch `WP_DEBUG`, `WP_DEBUG_LOG` and `WP_DEBUG_DISPLAY` on or off from the admin. You don't need FTP or a code editor.

Every action runs over AJAX, so the page never reloads.

## Features

- **Editor-style log view**: a full-width, full-height monospace editor with line numbers, a light theme and an optional word wrap.
- **Edit and save** the log in place. Press `Ctrl/Cmd + S` to save, and `Tab` inserts a tab character.
- **Delete** the log file with one click, after a confirmation prompt.
- **Refresh** the log to pick up new entries without reloading the page.
- **Toggle the debug constants**. The plugin writes `WP_DEBUG`, `WP_DEBUG_LOG` and `WP_DEBUG_DISPLAY` straight into `wp-config.php`.
- **Unsaved-changes marker**, plus a warning if you try to leave the page with unsaved edits.
- **Conflict check**: if new log entries were written after you loaded the file, the plugin asks before overwriting them.
- **Large-file safety**: logs over 5 MB open read-only and show only the most recent 5 MB, so a save can't wipe the rest of the file.
- **No database storage**: nothing is written to the database. Viewer preferences such as word wrap are kept in your browser only.

## Requirements

- WordPress 5.3 or later
- PHP 7.4 or later
- A writable `wp-config.php` file, needed only for toggling the constants
- A writable `wp-content` folder (or a writable custom log location), needed for saving and deleting the log

## Installation

1. Download or clone this repository into `wp-content/plugins/debug-log-viewer`:

   ```bash
   cd wp-content/plugins
   git clone https://github.com/<your-username>/debug-log-viewer.git
   ```

2. Activate **Debug Log Viewer** under **Plugins** in the WordPress admin.
3. Go to **Tools → Debug Log**.

## Usage

### Debug constants

Each constant has a switch and a badge that shows its current state.

| Constant           | What it does                                     |
| ------------------ | ------------------------------------------------ |
| `WP_DEBUG`         | Enables debug mode across WordPress.             |
| `WP_DEBUG_LOG`     | Saves errors to the `debug.log` file.            |
| `WP_DEBUG_DISPLAY` | Shows errors in the page HTML.                   |

`WP_DEBUG_LOG` and `WP_DEBUG_DISPLAY` only apply while `WP_DEBUG` is on. Changes take effect on the next page request.

When you flip a switch, the plugin edits `wp-config.php` like this:

- If the constant is already defined, only its value is changed. That includes a define inside an `if ( ! defined( 'WP_DEBUG' ) )` block.
- If it isn't defined yet, a new `define()` line is added just above the `/* That's all, stop editing! */` comment.
- Commented-out defines are left alone.
- If `WP_DEBUG_LOG` is set to a custom file path, turning it on keeps that path.
- Before saving, the plugin checks that the edited file is still valid PHP. If it isn't, the change is cancelled and the file is left as it was.

### Log file

The plugin reads the same file WordPress writes to:

- `wp-content/debug.log` by default
- the custom path, when `WP_DEBUG_LOG` is set to a file path, for example `define( 'WP_DEBUG_LOG', '/var/log/wp-errors.log' );`

## Filters

| Filter               | Default                        | Description                                                         |
| -------------------- | ------------------------------ | ------------------------------------------------------------------- |
| `dlv_log_path`       | WordPress' debug log path      | Change which file the viewer opens.                                 |
| `dlv_max_read_bytes` | `5 * MB_IN_BYTES`              | Files larger than this open read-only, showing only their last part. |

```php
// Allow editing logs up to 20 MB
add_filter( 'dlv_max_read_bytes', function() {
	return 20 * MB_IN_BYTES;
} );
```

## Security

- The page and all of its AJAX actions need the `manage_options` capability, and every request is checked with a nonce.
- Only `WP_DEBUG`, `WP_DEBUG_LOG` and `WP_DEBUG_DISPLAY` can be changed. All other constants are rejected.
- No backup copy of `wp-config.php` is written to the web root, because a backup there could expose your database credentials.
- The plugin only loads in the WordPress admin.

> **Note:** Turn off `WP_DEBUG_DISPLAY` on live sites. Showing errors to visitors can leak file paths and other sensitive details.

## File structure

```
debug-log-viewer/
├── debug-log-viewer.php     Plugin bootstrap
├── inc/
│   ├── init.php             Loads files, enqueues assets
│   ├── admin-page.php       Tools → Debug Log page
│   ├── ajax.php             AJAX handlers
│   ├── config.php           Reads and updates wp-config.php constants
│   └── helper.php           Log file read/write/delete
└── assets/
    ├── css/admin.css
    └── js/admin.js          jQuery UI logic
```

## Author

**Kowsar Hossain** · [kowsarhossain.com](http://kowsarhossain.com)

## License

[GNU General Public License v3.0](http://www.gnu.org/licenses/gpl-3.0.html)
