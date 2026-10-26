<?php
/**
 * Terms without a language.
 *
 * @package Linguator
 */

namespace Linguator\Modules\Scan\Checks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Linguator\Modules\Scan\Abstract_Check;
use Linguator\Modules\Scan\Result;

/**
 * Finds categories, tags and custom taxonomy terms that belong to no language.
 *
 * Such terms never show in a language's archives and cannot be linked to translations.
 *
 * @since 2.3.0
 */
class Terms_No_Language extends Abstract_Check {

	/**
	 * Detail rows kept.
	 */
	const DETAIL_LIMIT = 50;

	/**
	 * @var string
	 */
	protected $group = 'Content';

	/**
	 * @var string
	 */
	protected $section = 'content';

	/**
	 * Runs the check.
	 *
	 * @since 2.3.0
	 *
	 * @return Result[]
	 */
	public function run(): array {
		if ( count( $this->active_languages() ) < 2 || ! $this->wants( 'term_no_language' ) ) {
			return array();
		}

		$taxonomies = array_values( array_diff( (array) $this->model->get_translated_taxonomies(), array( 'nav_menu', 'link_category', 'post_format' ) ) );
		$taxonomies = array_values( array_filter( $taxonomies, 'taxonomy_exists' ) );
		if ( empty( $taxonomies ) ) {
			return array();
		}

		$ids = (array) $this->model->get_terms_with_no_lang( $taxonomies, self::DETAIL_LIMIT + 1 );

		$details = array();
		foreach ( $ids as $id ) {
			$term = get_term( (int) $id );
			if ( ! $term instanceof \WP_Term || ! in_array( $term->taxonomy, $taxonomies, true ) ) {
				continue;
			}

			$object    = get_taxonomy( $term->taxonomy );
			$details[] = array(
				'label'        => '' !== $term->name ? $term->name : __( '(no name)', 'translate-words' ),
				'message'      => __( 'This term has no language assigned.', 'translate-words' ),
				'action_url'   => (string) get_edit_term_link( $term->term_id, $term->taxonomy ),
				'action_label' => __( 'Edit', 'translate-words' ),
				'ignore_key'   => 'term_no_language:' . $term->term_id . ':' . $term->taxonomy,
				'post_type'    => $object && ! empty( $object->labels->singular_name ) ? $object->labels->singular_name : $term->taxonomy,
			);
		}

		if ( empty( $details ) ) {
			return array(
				$this->result(
					'term_no_language',
					Result::PASS,
					__( 'All terms have a language', 'translate-words' ),
					__( 'Every category and tag is assigned to a language.', 'translate-words' )
				),
			);
		}

		return array(
			$this->result(
				'term_no_language',
				Result::WARNING,
				__( 'Terms without a language', 'translate-words' ),
				__( 'Some categories or tags have no language and will not show in language archives. Assign the default language to them.', 'translate-words' )
			)
				->with_action( 'assign_default_lang', __( 'Assign default language', 'translate-words' ) )
				->with_details( array_slice( $details, 0, self::DETAIL_LIMIT ), count( $details ) > self::DETAIL_LIMIT ),
		);
	}
}
