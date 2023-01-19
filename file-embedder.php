<?php
/**
 * File Embedder
 *
 * @author  Mehdi Jalili
 * @license GPL-2.0+
 * @link    https://github.com/afragen/file-embedder
 * @package file-embedder
 */

/**
 * Plugin Name:       File Embedder
 * Plugin URI:        https://github.com/afragen/file-embedder
 * Description:       File Embedder helps you to Embed PDF from the Media Library or via easily.
 * Author:            Mehdi Jalili
 * Author URI:        https://github.com/mehdi-jalili
 * Version:           1.0.0
 * License:           GPLv2+
 * Domain Path:       /languages
 * Text Domain:       file-embedder
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.html
 * GitHub Plugin URI: https://github.com/mehdi-jalili/file-embedder
 * Requires PHP:      7.4
 * Requires at least: 5.8
 */

/**
 * Exit if called directly.
 */
if ( ! defined( 'WPINC' ) ) {
	die;
}

add_filter( 'media_send_to_editor', [ Flle_Embedder::instance(), 'embed_pdf_media_editor' ], 20, 2 );
wp_embed_register_handler(
	'flle_embedder',
	'#(^(https?)\:\/\/.+\.pdf$)#i',
	[
		Flle_Embedder::instance(),
		'flle_embedder',
	]
);
add_action(
	'init',
	function () {
		load_plugin_textdomain( 'flle-embedder' );
		wp_set_script_translations( 'flle-embedder-scripts', 'flle-embedder' );

		// phpcs:ignore WordPress.WP.EnqueuedResourceParameters.NoExplicitVersion
		wp_enqueue_style(
			'flle-embedder',
			plugins_url( 'css/flle-embedder.css', __FILE__ ),
			[],
			false,
			'screen'
		);
	}
);
add_action( 'init', [ Flle_Embedder::instance(), 'register_block' ] );

/**
 * Class Flle_Embedder
 */
class Flle_Embedder {
	/**
	 * For singleton.
	 *
	 * @var bool
	 */
	private static $instance = false;

	/**
	 * Create singleton.
	 *
	 * @return bool
	 */
	public static function instance() {
		if ( false === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register block.
	 *
	 * @return void
	 */
	public function register_block() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		// phpcs:ignore WordPress.WP.EnqueuedResourceParameters.NoExplicitVersion
		wp_register_script(
			'flle-embedder',
			plugins_url( 'blocks/build/index.js', __FILE__ ),
			[ 'wp-i18n', 'wp-blocks', 'wp-block-editor', 'wp-element', 'wp-components', 'wp-compose', 'wp-blob' ],
			false,
			true
		);

		register_block_type(
			'flle-embedder/pdf',
			[
				'editor_script' => 'flle-embedder',
			]
		);
	}

	/**
	 * Insert URL to PDF from Media Library, then render as oEmbed.
	 *
	 * @param string  $html an href link to the media.
	 * @param integer $id   post_id.
	 *
	 * @return string
	 */
	public function embed_pdf_media_editor( $html, $id ) {
		$post = get_post( $id );
		if ( 'application/pdf' !== $post->post_mime_type ) {
			return $html;
		}

		return $post->guid . "\n\n";
	}

	/**
	 * Create oEmbed code.
	 *
	 * @param array  $matches Regex matches.
	 * @param array  $atts    array of media height/width.
	 * @param string $url     URI for media file.
	 *
	 * @return string
	 */
	public function flle_embedder( $matches, $atts, $url ) {
		$attachment_id = $this->get_attachment_id_by_url( $url );
		if ( ! empty( $attachment_id ) ) {
			$post = get_post( $this->get_attachment_id_by_url( $url ) );
		} else {
			/*
			 * URL is from outside of the Media Library.
			 */
			$post                 = new WP_Post( new stdClass() );
			$post->guid           = $matches[0];
			$post->post_mime_type = 'application/pdf';
			$post->post_name      = preg_replace( '/\.pdf$/', '', basename( $matches[0] ) );
		}

		return $this->create_output( $post, $atts );
	}

	/**
	 * Create output for Google Doc Viewer and href link to file.
	 *
	 * @param \WP_Post     $post Current post.
	 * @param array|string $atts array of media height/width or
	 *                           href to media library asset.
	 *
	 * @return bool|string
	 */
	private function create_output( WP_Post $post, $atts = [] ) {
		if ( 'application/pdf' !== $post->post_mime_type ) {
			return $atts;
		}

		$default = [
			'height'      => 500,
			'width'       => 800,
			'title'       => $post->post_title,
			'description' => $post->post_content,
		];

		/*
		 * Ensure $atts isn't the href.
		 */
		$atts = is_array( $atts ) ? $atts : [];

		if ( isset( $atts['height'] ) ) {
			$atts['height'] = ( $atts['height'] / 2 );
		}
		$atts = array_merge( $default, $atts );

		/**
		 * Filter PDF attributes.
		 *
		 * @since 1.6.0
		 * @param  array $atts Array of PDF attributes.
		 * @return array $atts
		 */
		$atts = apply_filters( 'embed_pdf_viewer_pdf_attributes', $atts );

		// Fix title or create from filename.
		$atts['title']       = empty( $atts['title'] )
			? ucwords( preg_replace( '/(-|_)/', ' ', $post->post_name ) )
			: ucwords( preg_replace( '/(-|_)/', ' ', $atts['title'] ) );
		$atts['description'] = empty( $atts['description'] ) ? $atts['title'] : $atts['description'];

		$iframe_fallback  = '<iframe class="flle-embedder" src="https://docs.google.com/viewer?url=' . rawurlencode( $post->guid );
		$iframe_fallback .= '&amp;embedded=true" frameborder="0" ';
		$iframe_fallback .= 'style="height:' . $atts['height'] . 'px;width:' . $atts['width'] . 'px;" ';
		$iframe_fallback .= 'title="' . $atts['description'] . '"></iframe>' . "\n";

		$object  = '<object class="flle-embedder" data="' . $post->guid;
		$object .= '#scrollbar=1&toolbar=1"';
		$object .= 'type="application/pdf" ';
		$object .= 'height=' . $atts['height'] . ' width=' . $atts['width'] . ' ';
		$object .= 'title="' . $atts['description'] . '"> ';
		$object .= '</object>';

		$embed  = '<figure>';
		$embed .= $object . $iframe_fallback;
		$embed .= '<p><a href="' . $post->guid . '" title="' . $atts['description'] . '">' . $atts['title'] . '</a></p>';
		$embed .= '</figure>';

		return $embed;
	}

	/**
	 * Get attachment id by url. Thanks Pippin.
	 *
	 * @link  https://pippinsplugins.com/retrieve-attachment-id-from-image-url/
	 *
	 * @param string $url URI of attachment.
	 *
	 * @return mixed
	 */
	private function get_attachment_id_by_url( $url ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB
		$attachment = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE guid='%s';", $url ) );

		if ( empty( $attachment ) ) {
			return null;
		}

		return $attachment[0];
	}
}
