<?php
/**
 * Plugin Name: Limit Modified Date
 * Plugin URI: https://github.com/billerickson/Limit-Modified-Date
 * Description: Prevent the "modified date" from changing when making minor changes to your content.
 * Version: 1.0.0
 * Author: Bill Erickson
 * Author URI: https://www.billerickson.net
 */
class Limit_Modified_Date {

	/**
	 * Limit Modified Date enabled post meta key.
	 *
	 * @var string
	 */
	private $meta_key = 'limit_modified_date';

	/**
	 * Limit Modified Date modified date post meta key.
	 *
	 * @var string
	 */
	private $last_mod_meta_key = 'last_modified_date';

	/**
	 * Limit Modified Date nonce key.
	 *
	 * @var string
	 */
	private $nonce_key;

	/**
	 * Limit Modified Date asset version.
	 *
	 * @var string
	 */
	private $asset_version = '1.0';

	/**
	 * Instantiate the plugin.
	 */
	public function __construct() {
		$this->nonce_key = $this->meta_key . '_nonce';

		// Use original modified date.
		add_action( 'wp_insert_post_data', [ $this, 'use_original_modified_date' ], 20, 2 );

		// Checkbox in block editor.
		add_action( 'init', [ $this, 'register_post_meta' ] );
		add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_block_editor_assets' ] );

		// Checkbox in classic editor.
		add_action( 'post_submitbox_misc_actions', [ $this, 'classic_editor_checkbox' ] );
		add_action( 'save_post', [ $this, 'save_post' ] );

	}

	/**
	 * Use original modified date
	 *
	 * @param array $data Slashed post data.
	 * @param array $postarr Raw post data.
	 * @return array Slashed post data with modified post_modified and post_modified_gmt
	 */
	public function use_original_modified_date( $data, $postarr ) {

		// Block editor uses post meta.
		$use_original  = get_post_meta( $postarr['ID'], $this->meta_key, true );
		$last_modified = get_post_meta( $postarr['ID'], $this->last_mod_meta_key, true );

		if ( $use_original && $last_modified ) {
			$data['post_modified']     = date( 'Y-m-d H:i:s', strtotime( $last_modified ) );
			$data['post_modified_gmt'] = get_gmt_from_date( $data['post_modified'] );
		} else {
			// Classic editor.
			$use_original = isset( $_POST[ $this->meta_key ] ) ? '1' === $_POST[ $this->meta_key ] : false;
			if ( $use_original ) {

				if ( isset( $postarr['post_modified'] ) ) {
					$data['post_modified'] = $postarr['post_modified'];
				}
				if ( isset( $postarr['post_modified_gmt'] ) ) {
					$data['post_modified_gmt'] = $postarr['post_modified_gmt'];
				}
			}
		}

		return $data;
	}

	/**
	 * Registers the custom post meta fields needed by the post type.
	 */
	public function register_post_meta() {
		$args = [
			'show_in_rest' => true,
			'single'       => true,
		];

		register_meta( 'post', $this->meta_key, $args );
		register_meta( 'post', $this->last_mod_meta_key, $args );
	}

	/**
	 * Enqueues JavaScript and CSS for the block editor.
	 */
	public function enqueue_block_editor_assets() {

		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		if ( ! $this->is_supported_post_type( get_post_type() ) ) {
			return;
		}

		wp_enqueue_script(
			'limit-modified-date-js',
			plugins_url( 'assets/js/editor.js', __FILE__ ),
			[
				'wp-components',
				'wp-data',
				'wp-edit-post',
				'wp-editor',
				'wp-element',
				'wp-i18n',
				'wp-plugins',
			],
			$this->asset_version,
			true
		);

		wp_localize_script( 'limit-modified-date-js', 'limit_modified_date', [ 'current' => get_the_modified_time() ] );
	}

	/**
	 * Determine whether a post type supports limiting the modified date.
	 *
	 * @param string $type The post type to check.
	 * @return bool Whether this post type supports limiting the modified date.
	 */
	public static function is_supported_post_type( $type ) {
		$supported_post_types = (array) apply_filters( 'limit_modified_date_post_types', [ 'post' ] );
		return in_array( $type, $supported_post_types, true );
	}

	/**
	 * Checkbox in classic editor.
	 */
	public function classic_editor_checkbox() {
		if ( ! $this->is_supported_post_type( get_post_type() ) ) {
			return;
		}

		wp_nonce_field( $this->meta_key, $this->nonce_key );
		$val = get_post_meta( get_the_ID(), $this->meta_key, true );

		echo '<div class="misc-pub-section">';
			echo '<input type="checkbox" name="' . esc_attr( $this->meta_key ) . '" id="' . esc_attr( $this->meta_key ) . '" value="1"' . checked( $val, '1', true ) . ' />';
			echo '<label for="' . esc_attr( $this->meta_key ) . '">' . esc_html__( 'Don\'t update the modified date', 'limit-modified-date' ) . '</label>';
		echo '</div>';
	}

	/**
	 * Save the original modified date if applicable.
	 *
	 * @param int $post_id The Post ID.
	 */
	public function save_post( $post_id ) {
		if ( ! isset( $_POST['post_type'] ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if (
			! isset( $_POST[ $this->nonce_key ] ) ||
			! wp_verify_nonce( sanitize_text_field( $_POST[ $this->nonce_key ], $this->meta_key ) )
		) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( ! isset( $_POST['limit_modified_date'] ) ) {
			// Editing the post causes it to be purged anyway, so just remove the meta.
			delete_post_meta( $post_id, $this->meta_key );
		} else {
			if ( 1 === absint( $_POST['limit_modified_date'] ) ) {
				update_post_meta( $post_id, $this->meta_key, 1 );
			}
		}
	}
}

new Limit_Modified_Date();
