<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Everything a visitor sees: the [lokahitam_videos] shortcode and the
 * two read-only REST endpoints its JavaScript calls.
 */
class LVL_Frontend {

	public static function init() {
		add_shortcode( 'lokahitam_videos', array( __CLASS__, 'render_shortcode' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	public static function render_shortcode( $atts ) {
		wp_enqueue_style(
			'lvl-frontend-fonts',
			'https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Archivo:wght@400;500;600;700&display=swap',
			array(),
			null
		);
		wp_enqueue_style( 'lvl-frontend', LVL_PLUGIN_URL . 'assets/css/frontend.css', array(), LVL_VERSION );
		wp_enqueue_script( 'lvl-frontend', LVL_PLUGIN_URL . 'assets/js/frontend.js', array(), LVL_VERSION, true );

		wp_localize_script(
			'lvl-frontend',
			'lvlData',
			array(
				'restUrl' => esc_url_raw( rest_url( 'lvl/v1' ) ),
				'perPage' => (int) get_option( 'lvl_per_page', 24 ),
			)
		);

		ob_start();
		include LVL_PLUGIN_DIR . 'includes/views/frontend-app.php';
		return ob_get_clean();
	}

	public static function register_routes() {
		register_rest_route(
			'lvl/v1',
			'/playlists',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'api_playlists' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'lvl/v1',
			'/videos',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'api_videos' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public static function api_playlists( WP_REST_Request $request ) {
		$playlists = LVL_DB::get_playlists();
		$out       = array();

		foreach ( $playlists as $p ) {
			$covers = LVL_DB::get_playlist_cover_videos( $p['playlist'], 4 );
			$out[]  = array(
				'playlist' => $p['playlist'],
				'count'    => (int) $p['video_count'],
				'covers'   => array_map( array( __CLASS__, 'thumb_url' ), $covers ),
			);
		}

		return rest_ensure_response( $out );
	}

	public static function api_videos( WP_REST_Request $request ) {
		$args = array(
			'playlist' => sanitize_text_field( (string) $request->get_param( 'playlist' ) ),
			'search'   => sanitize_text_field( (string) $request->get_param( 'q' ) ),
			'page'     => absint( $request->get_param( 'page' ) ) ?: 1,
			'per_page' => absint( $request->get_param( 'per_page' ) ) ?: (int) get_option( 'lvl_per_page', 24 ),
		);

		$result = LVL_DB::get_videos( $args );
		foreach ( $result['items'] as &$item ) {
			$item['thumb'] = self::thumb_url( $item['video_id'] );
		}
		unset( $item );

		return rest_ensure_response( $result );
	}

	public static function thumb_url( $video_id ) {
		return 'https://i.ytimg.com/vi/' . rawurlencode( $video_id ) . '/hqdefault.jpg';
	}
}
