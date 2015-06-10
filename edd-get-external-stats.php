<?php
/**
 * Plugin Name:     Easy Digital Downloads - Get External Stats
 * Plugin URI:      https://filament-studios.com/downloads/edd-external-stats
 * Description:     Allows a download to pull it's stats from an external EDD site
 * Version:         1.0
 * Author:          Chris Klosowski
 * Author URI:      http://filament-studios.com.com
 * Text Domain:     edd-ges
 *
 * @package         EDD\GetExternalStats
 * @author          Chris Klosowski <chris@filament-studios.com>
 */


class EDD_Get_External_Stats {

	private static $instance;

	private function __construct() {

		$this->setup_constants();
		$this->hooks();
		$this->filters();

	}

	public static function getIntance() {

			if( ! self::$instance ) {
				self::$instance = new EDD_Get_External_Stats();
			}

			return self::$instance;
	}

	private function setup_constants() {
		// Plugin version
		define( 'EDD_GES_VER', '1.0' );

		// Plugin path
		define( 'EDD_GES_DIR', plugin_dir_path( __FILE__ ) );

		// Plugin URL
		define( 'EDD_GES_URL', plugin_dir_url( __FILE__ ) );
	}

	public function hooks() {
		add_action( 'add_meta_boxes', array( $this, 'register_metabox' ) );
		add_action( 'save_post'     , array( $this, 'save_post' ), 10, 1 );
	}

	public function filters() {

		// These filters integrate with Vendd, to get external data
		add_filter( 'vendd_show_sales_in_sidebar', '__return_true' );
		add_filter( 'vendd_download_sales_count' , array( $this, 'download_sales_count' ), 10, 2 );
		add_filter( 'vendd_download_is_licensed' , array( $this, 'download_is_licensed' ), 10, 2 );
		add_filter( 'vendd_download_version'     , array( $this, 'download_version' ), 10, 2 );
	}

	public function register_metabox() {
		global $post;

		if ( 'download' !== $post->post_type ) {
			return;
		}

		add_meta_box(
			'edd_ges_metabox',
			__( 'Easy Digital Downloads - External Stats', 'edd-ges' ),
			array( $this, 'render_metabox' ),
			'download',
			'normal',
			'high'
		);
	}

	public function render_metabox() {
		global $post;
		$url = get_post_meta( $post->ID, '_edd_ges_url', true );
		?>
		<label for="edd-ges-url"><?php _e( 'Stats URL', 'edd-ges' ); ?></label>
		<input type="text" id="edd-ges-url" size="100" name="edd-ges-url" value="<?php echo $url; ?>" />
		<?php
	}

	public function save_post( $post_id ) {
		$post_type = get_post_type( $post_id );
		if ( 'download' !== $post_type ) {
			return;
		}

		$url = ! empty( $_POST['edd-ges-url'] ) ? sanitize_text_field( $_POST['edd-ges-url'] ) : '';

		if ( ! empty( $url ) ) {
			update_post_meta( $post_id, '_edd_ges_url', $url );
		} else {
			delete_post_meta( $post_id, '_edd_ges_url' );
		}
	}

	public function download_sales_count( $sales_count, $post ) {

		$stats = $this->get_external_stats( $post->ID );

		if ( empty( $stats ) ) {
			return $sales_count;
		}

		if ( empty( $stats['products'][0]['stats']['total']['sales'] ) ) {
			return $sales_count;
		}

		$sales_count = $stats['products'][0]['stats']['total']['sales'];

		return $sales_count;

	}

	public function download_is_licensed( $is_licensed, $post ) {
		$stats = $this->get_external_stats( $post->ID );

		if ( empty( $stats ) ) {
			return $is_licensed;
		}

		if ( empty( $stats['products'][0]['licensing']['enabled'] ) ) {
			return $is_licensed;
		}

		$is_licensed = $stats['products'][0]['licensing']['enabled'];

		return $is_licensed;
	}


	public function download_version( $version, $post ) {
		$stats = $this->get_external_stats( $post->ID );

		if ( empty( $stats ) ) {
			return $version;
		}

		if ( empty( $stats['products'][0]['licensing']['version'] ) ) {
			return $version;
		}

		$version = $stats['products'][0]['licensing']['version'];

		return $version;
	}

	private function get_external_stats( $post_id ) {

		$post_type = get_post_type( $post_id );
		$results = false;

		if ( 'download' !== $post_type ) {
			return $results;
		}

		$url = get_post_meta( $post_id, '_edd_ges_url', true );

		if ( empty( $url ) ) {
			return $results;
		}

		//$results = get_transient( '_edd_ges_data' . $post_id );
		if ( false === $results ) {
			$data = wp_remote_get( $url );

			if ( is_wp_error( $data ) ) {
				return $results;
			}

			$results = json_decode( wp_remote_retrieve_body( $data ), true );

			set_transient( '_edd_ges_data' . $post_id, $results, DAY_IN_SECONDS );
		}

		return $results;

	}


}

function load_edd_ges() {
	return EDD_Get_External_Stats::getIntance();
}
add_action( 'plugins_loaded', 'load_edd_ges' );
