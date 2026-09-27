=== Lokahitam Video Library ===
Requires at least: 5.6
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later

Turns a Google Sheet of YouTube videos into a browsable, searchable video
library on your WordPress site, with an admin panel to sync new rows on
demand or on a schedule. Built for shared hosting with no shell/SSH access.

== Install ==

1. In WordPress: Plugins -> Add New -> Upload Plugin -> choose this ZIP -> Install Now -> Activate.
   (No FTP or shell needed.)
2. In Google Sheets, open your sheet -> Share -> General access ->
   "Anyone with the link" -> Viewer. The sync reads the sheet as CSV and
   needs it to be link-viewable.
3. In wp-admin, go to Video Library (left sidebar). Paste your sheet's
   share link into Settings and save.
4. Click "Sync now". Your videos should appear within a few seconds.
5. Create or edit a Page and add the shortcode: [lokahitam_videos]
6. Visit that page — you should see your series as cards, click into one
   to see its videos, click a video to play it in a popup.

== Keeping it up to date automatically ==

The plugin schedules an automatic sync (WP-Cron) at the interval you pick
in Settings. WP-Cron only checks in when someone visits the site though,
so on a quiet site it can run late. For reliable timing, add a real Cron
Job from your hosting control panel (cPanel -> Cron Jobs — this is a plain
web form, no SSH/shell needed) pointing at:

    https://yourdomain/wp-cron.php?doing_wp_cron

The exact URL for your site is shown on the Video Library settings page.

== Notes on the sheet ==

- Expected columns (by header name, any order): Playlist, Video ID,
  Duplicate, Video URL, Title, Type, Duration (Formatted),
  Duration (Seconds), Views, Views From API, Likes, Comments,
  Privacy Status, Description.
- Rows marked Duplicate = TRUE, and rows whose Privacy Status is not
  "public" (and not blank), are stored but hidden from the public library.
- Videos are matched by Video ID, so re-syncing updates existing rows
  (views, likes, etc.) instead of creating duplicates.
- If a video's title contains "Part <number>", that number is used to
  sort episodes within a series in watch order.

== Uninstall ==

Deleting the plugin removes its settings but keeps the synced video table,
so nothing is lost if you reinstall it later.
