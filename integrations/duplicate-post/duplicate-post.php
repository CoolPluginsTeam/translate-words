<?php
/**
 * @package Linguator
 */
namespace Linguator\Integrations\duplicate_post;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Manages the compatibility with Duplicate Post.
 *
 *  
 */
class Linguator_Duplicate_Post {
	/**
	 * Setups actions.
	 *
	 *  
	 */
	public function init() {
		add_filter( 'option_duplicate_post_taxonomies_blacklist', array( $this, 'linguator_taxonomies_blacklist' ) );
		add_filter( 'lmat_sync_post_fields', array( $this, 'exclude_rewrite_copy_fields' ), 10, 2 );
	}

	/**
	 * Keeps Duplicate Post's temporary rewrite status off translated posts.
	 *
	 * @param string[] $fields  Post fields to synchronize.
	 * @param int      $post_id Synchronization source post ID.
	 * @return string[]
	 */
	public function exclude_rewrite_copy_fields( $fields, $post_id ) {
		if ( 1 === (int) get_post_meta( $post_id, '_dp_is_rewrite_republish_copy', true ) ) {
			unset( $fields['post_status'] );
		}

		return $fields;
	}

	/**
	 * Avoid duplicating the 'lmat_post_translations' taxonomy.
	 *
	 *  
	 *
	 * @param array|string $taxonomies The list of taxonomies not to duplicate.
	 * @return array
	 */
	public function linguator_taxonomies_blacklist( $taxonomies ) {
		if ( empty( $taxonomies ) ) {
			$taxonomies = array(); // As we get an empty string when there is no taxonomy.
		}

		$taxonomies[] = 'lmat_post_translations';
		return $taxonomies;
	}
}

