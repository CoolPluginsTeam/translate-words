<?php
/**
 * @package Linguator
 */

namespace Linguator\Modules\sync;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Linguator\Settings\Controllers\LMAT_Settings_Module;



/**
 * Settings class for synchronization settings management
 *
 *  
 */
class LMAT_Settings_Sync extends LMAT_Settings_Module {
	/**
	 * Stores the display order priority.
	 *
	 * @var int
	 */
	public $priority = 50;

	/**
	 * Constructor
	 *
	 *  
	 *
	 * @param object $linguator The linguator object.
	 */
	public function __construct( &$linguator ) {
		parent::__construct(
			$linguator,
			array(
				'module'      => 'sync',
				'title'       => __( 'Synchronization', 'translate-words' ),
				'description' => __( 'The synchronization options allow to maintain exact same values (or translations in the case of taxonomies and page parent) of meta content between the translations of a post or page.', 'translate-words' ),
			)
		);
	}

	/**
	 * Deactivates the module
	 *
	 *  
	 */
	public function deactivate() {
		$this->options['sync'] = array();
	}


	/**
	 * Prepare the received data before saving.
	 *
	 *  
	 *
	 * @param array $options Raw values to save.
	 * @return array
	 */
	protected function prepare_raw_data( array $options ): array {
		// Take care to return only validated options.
		return array( 'sync' => empty( $options['sync'] ) ? array() : array_keys( $options['sync'], 1 ) );
	}

	/**
	 * Get the row actions.
	 *
	 *  
	 *
	 * @return string[] Row actions.
	 */
	protected function get_actions() {
		return empty( $this->options['sync'] ) ? array( 'configure' ) : array( 'configure', 'deactivate' );
	}

	/**
	 * Get the list of synchronization settings.
	 *
	 *  
	 *
	 * @return string[] Array synchronization options.
	 *
	 * @phpstan-return non-empty-array<non-falsy-string, string>
	 */
	public static function list_metas_to_sync() {
		return array(
			'taxonomies'        => __( 'Taxonomies', 'translate-words' ),
			'post_meta'         => __( 'Custom fields', 'translate-words' ),
			'comment_status'    => __( 'Comment status', 'translate-words' ),
			'ping_status'       => __( 'Ping status', 'translate-words' ),
			'sticky_posts'      => __( 'Sticky posts', 'translate-words' ),
			'post_date'         => __( 'Published date', 'translate-words' ),
			'post_format'       => __( 'Post format', 'translate-words' ),
			'post_parent'       => __( 'Page parent', 'translate-words' ),
			'_wp_page_template' => __( 'Page template', 'translate-words' ),
			'menu_order'        => __( 'Page order', 'translate-words' ),
			'_thumbnail_id'     => __( 'Featured image', 'translate-words' ),
		);
	}
}
