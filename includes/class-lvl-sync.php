<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Everything to do with pulling data from the Google Sheet into the
 * local videos table.
 */
class LVL_Sync {

	public static function build_csv_url() {
		$sheet_id = get_option( 'lvl_sheet_id', '' );
		$gid      = get_option( 'lvl_sheet_gid', '0' );

		if ( empty( $sheet_id ) ) {
			return '';
		}

		return sprintf(
			'https://docs.google.com/spreadsheets/d/%s/export?format=csv&gid=%s',
			rawurlencode( $sheet_id ),
			rawurlencode( $gid )
		);
	}

	/**
	 * Accepts either a full Google Sheets URL (edit link or share link) or a
	 * bare Sheet ID, pulls out the ID and gid, and stores them as options.
	 */
	public static function save_sheet_source( $raw_input, $gid_input = '' ) {
		$raw_input = trim( $raw_input );
		$gid       = trim( $gid_input );
		$sheet_id  = $raw_input;

		if ( preg_match( '#/d/([a-zA-Z0-9_-]+)#', $raw_input, $matches ) ) {
			$sheet_id = $matches[1];
		}

		if ( '' === $gid && preg_match( '/[?&#]gid=(\d+)/', $raw_input, $gid_matches ) ) {
			$gid = $gid_matches[1];
		}
		if ( '' === $gid ) {
			$gid = '0';
		}

		update_option( 'lvl_sheet_id', sanitize_text_field( $sheet_id ) );
		update_option( 'lvl_sheet_gid', sanitize_text_field( $gid ) );
	}

	/**
	 * Downloads the sheet as CSV and upserts every valid row.
	 *
	 * @return array|WP_Error Summary of the run, or an error explaining what to fix.
	 */
	public static function run_sync() {
		global $wpdb;

		$csv_url = self::build_csv_url();
		if ( empty( $csv_url ) ) {
			return new WP_Error( 'lvl_no_sheet', 'No Google Sheet is configured yet. Add your sheet link under Settings first.' );
		}

		$response = wp_remote_get(
			$csv_url,
			array(
				'timeout'     => 45,
				'redirection' => 5,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return new WP_Error( 'lvl_fetch_failed', 'Could not download the sheet (HTTP ' . $code . '). Make sure it is shared as "Anyone with the link – Viewer".' );
		}

		$body = wp_remote_retrieve_body( $response );
		if ( empty( $body ) ) {
			return new WP_Error( 'lvl_empty', 'The sheet came back empty.' );
		}

		$trimmed_body = ltrim( $body );
		if ( 0 === stripos( $trimmed_body, '<!doctype html' ) || 0 === stripos( $trimmed_body, '<html' ) ) {
			return new WP_Error( 'lvl_not_csv', 'That link returned a web page instead of CSV data — the sheet is probably not shared publicly yet. Set sharing to "Anyone with the link – Viewer" and try again.' );
		}

		$table            = LVL_DB::table_name();
		$existing_before  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" );

		$tmp_file = wp_tempnam( 'lvl-sync' );
		file_put_contents( $tmp_file, $body );

		$handle = fopen( $tmp_file, 'r' );
		if ( ! $handle ) {
			@unlink( $tmp_file );
			return new WP_Error( 'lvl_tmp_failed', 'Could not read the downloaded sheet data.' );
		}

		$header = fgetcsv( $handle );
		if ( ! $header ) {
			fclose( $handle );
			@unlink( $tmp_file );
			return new WP_Error( 'lvl_no_header', 'The sheet appears to have no header row.' );
		}

		$col_index = array();
		foreach ( $header as $i => $name ) {
			$col_index[ strtolower( trim( $name ) ) ] = $i;
		}

		$get_cell = function ( $row, $key ) use ( $col_index ) {
			$key = strtolower( $key );
			return isset( $col_index[ $key ], $row[ $col_index[ $key ] ] ) ? trim( $row[ $col_index[ $key ] ] ) : '';
		};

		$batch         = array();
		$processed     = 0;
		$skipped       = 0;
		$sheet_row_num = 1;

		while ( false !== ( $row = fgetcsv( $handle ) ) ) {
			$sheet_row_num++;

			$video_id = $get_cell( $row, 'Video ID' );
			$playlist = $get_cell( $row, 'Playlist' );

			if ( '' === $video_id || '' === $playlist ) {
				$skipped++;
				continue;
			}

			$title       = $get_cell( $row, 'Title' );
			$part_number = 0;
			if ( preg_match( '/part\s*(\d+)/i', $title, $part_match ) ) {
				$part_number = (int) $part_match[1];
			}

			$is_duplicate = ( 'TRUE' === strtoupper( $get_cell( $row, 'Duplicate' ) ) ) ? 1 : 0;

			$views_raw = $get_cell( $row, 'Views From API' );
			if ( '' === $views_raw ) {
				$views_raw = $get_cell( $row, 'Views' );
			}

			$batch[] = array(
				'video_id'            => sanitize_text_field( $video_id ),
				'playlist'            => sanitize_text_field( $playlist ),
				'title'               => sanitize_text_field( $title ),
				'video_url'           => esc_url_raw( $get_cell( $row, 'Video URL' ) ),
				'video_type'          => sanitize_text_field( $get_cell( $row, 'Type' ) ),
				'duration_formatted'  => sanitize_text_field( $get_cell( $row, 'Duration (Formatted)' ) ),
				'duration_seconds'    => (int) preg_replace( '/[^0-9]/', '', $get_cell( $row, 'Duration (Seconds)' ) ),
				'part_number'         => $part_number,
				'views'               => (int) preg_replace( '/[^0-9]/', '', $views_raw ),
				'likes'               => (int) preg_replace( '/[^0-9]/', '', $get_cell( $row, 'Likes' ) ),
				'comments'            => (int) preg_replace( '/[^0-9]/', '', $get_cell( $row, 'Comments' ) ),
				'privacy_status'      => sanitize_text_field( strtolower( $get_cell( $row, 'Privacy Status' ) ) ),
				'is_duplicate'        => $is_duplicate,
				'description'         => sanitize_textarea_field( $get_cell( $row, 'Description' ) ),
				'sheet_row'           => $sheet_row_num,
			);
			$processed++;

			if ( count( $batch ) >= 200 ) {
				LVL_DB::upsert_videos( $batch );
				$batch = array();
			}
		}

		if ( ! empty( $batch ) ) {
			LVL_DB::upsert_videos( $batch );
		}

		fclose( $handle );
		@unlink( $tmp_file );

		$existing_after = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" );
		$inserted       = max( 0, $existing_after - $existing_before );
		$updated        = max( 0, $processed - $inserted );

		$result = array(
			'processed' => $processed,
			'inserted'  => $inserted,
			'updated'   => $updated,
			'skipped'   => $skipped,
			'total_now' => $existing_after,
			'time'      => current_time( 'mysql' ),
		);

		update_option( 'lvl_last_sync', $result );

		return $result;
	}
}
