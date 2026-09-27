<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * @var string $sheet_id
 * @var string $gid
 * @var int    $per_page
 * @var string $interval
 * @var array  $last_sync
 * @var array  $stats
 * @var string $site_url
 */
?>
<div class="wrap lvl-wrap">
	<h1>Video Library</h1>

	<?php if ( isset( $_GET['lvl_saved'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>
	<?php endif; ?>

	<div class="lvl-stats">
		<div class="lvl-stat-card">
			<div class="lvl-stat-num" id="lvl-stat-videos"><?php echo esc_html( $stats['total_videos'] ); ?></div>
			<div class="lvl-stat-label">Videos in the library</div>
		</div>
		<div class="lvl-stat-card">
			<div class="lvl-stat-num"><?php echo esc_html( $stats['total_playlists'] ); ?></div>
			<div class="lvl-stat-label">Series (playlists)</div>
		</div>
		<div class="lvl-stat-card">
			<div class="lvl-stat-num" style="font-size:16px;">
				<?php echo ! empty( $last_sync['time'] ) ? esc_html( human_time_diff( strtotime( $last_sync['time'] ) ) . ' ago' ) : 'Never'; ?>
			</div>
			<div class="lvl-stat-label">Last synced</div>
		</div>
	</div>

	<div class="lvl-sync-box">
		<h2 style="margin-top:0;">Sync now</h2>
		<p>Pulls the latest rows from your Google Sheet and adds or updates them here. Safe to run as often as you like — existing videos are matched by Video ID and updated, not duplicated.</p>
		<button type="button" class="button button-primary button-hero" id="lvl-sync-btn">Sync now</button>
		<div class="lvl-sync-result" id="lvl-sync-result"></div>
		<?php if ( ! empty( $last_sync ) && ! isset( $_GET['lvl_saved'] ) ) : ?>
			<p style="color:#646970;margin-top:14px;">
				Last run: <?php echo esc_html( $last_sync['inserted'] ); ?> new,
				<?php echo esc_html( $last_sync['updated'] ); ?> updated,
				<?php echo esc_html( $last_sync['skipped'] ); ?> skipped (blank rows).
			</p>
		<?php endif; ?>
	</div>

	<div class="lvl-sync-box">
		<h2 style="margin-top:0;">Settings</h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'lvl_save_settings' ); ?>
			<input type="hidden" name="action" value="lvl_save_settings">

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="lvl_sheet_input">Google Sheet link</label></th>
					<td>
						<input type="text" id="lvl_sheet_input" name="lvl_sheet_input" class="regular-text"
							value="<?php echo esc_attr( $sheet_id ); ?>"
							placeholder="Paste the full sheet URL, or just the Sheet ID">
						<p class="description">
							Paste the sheet's share link directly — the ID (and tab, if the link includes one) is picked out automatically.
							Make sure sharing is set to <strong>"Anyone with the link – Viewer"</strong>, or the sync can't read it.
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="lvl_gid_input">Sheet tab (gid)</label></th>
					<td>
						<input type="text" id="lvl_gid_input" name="lvl_gid_input" class="small-text" value="<?php echo esc_attr( $gid ); ?>">
						<p class="description">Optional. Leave as <code>0</code> unless your data lives on a tab other than the first — the number after <code>gid=</code> in that tab's URL.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="lvl_per_page">Videos per page</label></th>
					<td>
						<input type="number" id="lvl_per_page" name="lvl_per_page" class="small-text" min="6" max="60" value="<?php echo esc_attr( $per_page ); ?>">
						<p class="description">How many video cards load at a time before someone taps "Load more".</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="lvl_interval">Auto-sync frequency</label></th>
					<td>
						<select id="lvl_interval" name="lvl_interval">
							<option value="hourly" <?php selected( $interval, 'hourly' ); ?>>Hourly</option>
							<option value="twicedaily" <?php selected( $interval, 'twicedaily' ); ?>>Twice daily</option>
							<option value="daily" <?php selected( $interval, 'daily' ); ?>>Daily</option>
							<option value="lvl_weekly" <?php selected( $interval, 'lvl_weekly' ); ?>>Weekly</option>
						</select>
						<p class="description">How often the library checks the sheet for new videos automatically, in addition to the manual button above.</p>
					</td>
				</tr>
			</table>

			<?php submit_button( 'Save settings' ); ?>
		</form>
	</div>

	<div class="lvl-cron-note">
		<strong>Make auto-sync reliable.</strong> WordPress only checks the schedule above when someone visits the site, which can be unpredictable on quieter days. For a shared host without shell access, add a <strong>Cron Job</strong> from your hosting control panel (cPanel &rarr; Cron Jobs — no SSH needed, it's a plain web form) that runs on the schedule you chose and hits this address:
		<br><code><?php echo esc_html( $site_url ); ?></code>
		<br>Most cPanel cron forms give you a ready-made command — typically <code>wget -q -O /dev/null "<?php echo esc_html( $site_url ); ?>"</code>.
	</div>

	<div class="lvl-sync-box lvl-shortcode-box">
		<h2 style="margin-top:0;">Show the library on a page</h2>
		<p>Create (or edit) a Page and add this shortcode wherever you want the video library to appear:</p>
		<code>[lokahitam_videos]</code>
	</div>
</div>
