<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * All database access for the video library lives here: the table schema,
 * the bulk upsert used by sync, and the read queries the front end and
 * admin screen call.
 */
class LVL_DB {

	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'lvl_videos';
	}

	/**
	 * Creates (or upgrades) the videos table. Safe to call repeatedly —
	 * dbDelta only applies the differences.
	 */
	public static function create_table() {
		global $wpdb;
		$table           = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			video_id VARCHAR(32) NOT NULL,
			playlist VARCHAR(191) NOT NULL DEFAULT '',
			title TEXT NULL,
			video_url VARCHAR(255) NULL,
			video_type VARCHAR(32) NULL,
			duration_formatted VARCHAR(20) NULL,
			duration_seconds INT UNSIGNED NOT NULL DEFAULT 0,
			part_number INT UNSIGNED NOT NULL DEFAULT 0,
			views BIGINT UNSIGNED NOT NULL DEFAULT 0,
			likes BIGINT UNSIGNED NOT NULL DEFAULT 0,
			comments BIGINT UNSIGNED NOT NULL DEFAULT 0,
			privacy_status VARCHAR(20) NOT NULL DEFAULT '',
			is_duplicate TINYINT UNSIGNED NOT NULL DEFAULT 0,
			description LONGTEXT NULL,
			sheet_row INT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY video_id (video_id),
			KEY playlist (playlist),
			KEY part_number (part_number)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Bulk insert-or-update, keyed on video_id, in chunks so a ~1000 row
	 * sync never builds one huge query.
	 *
	 * @param array $rows Each row is an assoc array matching the column list below.
	 * @return int Rows affected across all chunks (MySQL semantics: 1 per insert, 2 per changed update).
	 */
	public static function upsert_videos( array $rows ) {
		global $wpdb;
		if ( empty( $rows ) ) {
			return 0;
		}

		$table    = self::table_name();
		$fields   = array( 'video_id', 'playlist', 'title', 'video_url', 'video_type', 'duration_formatted', 'duration_seconds', 'part_number', 'views', 'likes', 'comments', 'privacy_status', 'is_duplicate', 'description', 'sheet_row' );
		$affected = 0;

		foreach ( array_chunk( $rows, 200 ) as $chunk ) {
			$placeholders = array();
			$values       = array();

			foreach ( $chunk as $row ) {
				$placeholders[] = '(%s,%s,%s,%s,%s,%s,%d,%d,%d,%d,%d,%s,%d,%s,%d,NOW(),NOW())';
				foreach ( $fields as $field ) {
					$values[] = isset( $row[ $field ] ) ? $row[ $field ] : '';
				}
			}

			$sql = 'INSERT INTO ' . $table . ' (' . implode( ',', $fields ) . ',created_at,updated_at)
				VALUES ' . implode( ',', $placeholders ) . '
				ON DUPLICATE KEY UPDATE
					playlist = VALUES(playlist),
					title = VALUES(title),
					video_url = VALUES(video_url),
					video_type = VALUES(video_type),
					duration_formatted = VALUES(duration_formatted),
					duration_seconds = VALUES(duration_seconds),
					part_number = VALUES(part_number),
					views = VALUES(views),
					likes = VALUES(likes),
					comments = VALUES(comments),
					privacy_status = VALUES(privacy_status),
					is_duplicate = VALUES(is_duplicate),
					description = VALUES(description),
					sheet_row = VALUES(sheet_row),
					updated_at = NOW()';

			$wpdb->query( $wpdb->prepare( $sql, $values ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$affected += (int) $wpdb->rows_affected;
		}

		return $affected;
	}

	/** Distinct playlists (series) with a video count each, alphabetical. */
	public static function get_playlists() {
		global $wpdb;
		$table = self::table_name();
		$sql   = "SELECT playlist, COUNT(*) as video_count
			FROM $table
			WHERE is_duplicate = 0 AND (privacy_status = 'public' OR privacy_status = '') AND playlist != ''
			GROUP BY playlist
			ORDER BY playlist ASC";
		return $wpdb->get_results( $sql, ARRAY_A );
	}

	/** A handful of video IDs from a playlist, for building its cover-art mosaic. */
	public static function get_playlist_cover_videos( $playlist, $limit = 4 ) {
		global $wpdb;
		$table = self::table_name();
		$sql   = $wpdb->prepare(
			"SELECT video_id FROM $table
				WHERE playlist = %s AND is_duplicate = 0 AND (privacy_status = 'public' OR privacy_status = '')
				ORDER BY (part_number = 0) ASC, part_number ASC, id ASC
				LIMIT %d",
			$playlist,
			$limit
		);
		return $wpdb->get_col( $sql );
	}

	/**
	 * Paginated, filterable video listing used by the front end.
	 *
	 * @param array $args playlist, search, page, per_page.
	 */
	public static function get_videos( $args = array() ) {
		global $wpdb;
		$table = self::table_name();

		$args = wp_parse_args(
			$args,
			array(
				'playlist' => '',
				'search'   => '',
				'page'     => 1,
				'per_page' => 24,
			)
		);

		$where  = array( 'is_duplicate = 0', "(privacy_status = 'public' OR privacy_status = '')" );
		$params = array();

		if ( ! empty( $args['playlist'] ) ) {
			$where[]  = 'playlist = %s';
			$params[] = $args['playlist'];
		}
		if ( ! empty( $args['search'] ) ) {
			$where[]  = 'title LIKE %s';
			$params[] = '%' . $wpdb->esc_like( $args['search'] ) . '%';
		}
		$where_sql = implode( ' AND ', $where );

		$per_page = max( 1, min( 100, (int) $args['per_page'] ) );
		$page     = max( 1, (int) $args['page'] );
		$offset   = ( $page - 1 ) * $per_page;

		$count_sql = "SELECT COUNT(*) FROM $table WHERE $where_sql";
		$total     = $params
			? (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) )
			: (int) $wpdb->get_var( $count_sql );

		$data_sql     = "SELECT video_id, playlist, title, duration_formatted, duration_seconds, part_number, views, likes
			FROM $table WHERE $where_sql
			ORDER BY (part_number = 0) ASC, part_number ASC, title ASC
			LIMIT %d OFFSET %d";
		$data_params  = array_merge( $params, array( $per_page, $offset ) );
		$rows         = $wpdb->get_results( $wpdb->prepare( $data_sql, $data_params ), ARRAY_A );

		return array(
			'items'       => $rows,
			'total'       => $total,
			'page'        => $page,
			'per_page'    => $per_page,
			'total_pages' => max( 1, (int) ceil( $total / $per_page ) ),
		);
	}

	public static function get_stats() {
		global $wpdb;
		$table = self::table_name();
		return array(
			'total_videos'    => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE is_duplicate = 0" ),
			'total_playlists' => (int) $wpdb->get_var( "SELECT COUNT(DISTINCT playlist) FROM $table WHERE playlist != '' AND is_duplicate = 0" ),
			'total_raw_rows'  => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" ),
		);
	}
}
