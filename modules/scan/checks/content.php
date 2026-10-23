<?php
/**
 * Content translation checks.
 *
 * @package Linguator
 */

namespace Linguator\Modules\Scan\Checks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Linguator\Modules\Scan\Abstract_Check;
use Linguator\Modules\Scan\Freshness;
use Linguator\Modules\Scan\Result;

/**
 * Finds content with no language and default-language content with no translation.
 *
 * @since 2.3.0
 */
class Content extends Abstract_Check {

	/**
	 * Default-language posts inspected per post type.
	 */
	const POST_LIMIT = 500;

	/**
	 * Detail rows kept per result.
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
	 * Per-language coverage collected by the last run.
	 *
	 * @var array[]
	 */
	private $coverage = array();

	/**
	 * Coverage rows for the Coverage tab.
	 *
	 * @since 2.3.0
	 *
	 * @return array
	 */
	public function extra(): array {
		return array( 'coverage' => array_values( $this->coverage ) );
	}

	/**
	 * Runs the check.
	 *
	 * @since 2.3.0
	 *
	 * @return Result[]
	 */
	public function run(): array {
		$languages = $this->active_languages();
		$default   = $this->default_lang();
		$types     = $this->translated_post_types();

		if ( count( $languages ) < 2 || '' === $default || empty( $types ) ) {
			return array();
		}

		$results = array();
		if ( $this->wants( 'content_no_language' ) ) {
			$results[] = $this->no_language_result( $types );
		}

		$default_lang = $this->model->get_language( $default );
		if ( ! $default_lang ) {
			return $results;
		}

		$targets = array_values(
			array_filter(
				$languages,
				static function ( $lang ) use ( $default ) {
					return $lang->slug !== $default;
				}
			)
		);

		foreach ( $targets as $lang ) {
			$this->coverage[ $lang->slug ] = array(
				'slug'       => $lang->slug,
				'name'       => $lang->name,
				'total'      => 0,
				'translated' => 0,
				'percent'    => 100,
				'sampled'    => false,
			);
		}

		foreach ( $types as $type ) {
			if ( $this->wants( 'content_' . $type ) ) {
				$results[] = $this->missing_translations_result( $type, $default_lang, $targets );
			}
		}

		if ( $this->wants( 'content_source_updated' ) ) {
			$results[] = $this->source_updated_result( $types, $default_lang, $targets );
		}

		foreach ( $this->coverage as $slug => $row ) {
			$this->coverage[ $slug ]['percent'] = $row['total'] > 0 ? (int) floor( 100 * $row['translated'] / $row['total'] ) : 100;
		}

		return $results;
	}

	/**
	 * Posts that belong to no language never appear on the front end of a multilingual site.
	 *
	 * @since 2.3.0
	 *
	 * @param string[] $types Translated post types.
	 * @return Result
	 */
	private function no_language_result( array $types ): Result {
		$scoped = $this->scoped_ids( 'content_no_language' );
		$limit  = self::DETAIL_LIMIT + 1;
		$ids    = $scoped ? array_keys( $scoped ) : $this->model->get_posts_with_no_lang( $types, $limit );

		// A scoped recheck asks about specific posts: they are fixed once they have a language.
		if ( $scoped ) {
			$ids = array_filter(
				$ids,
				function ( $id ) {
					return ! $this->model->post->get_language( (int) $id );
				}
			);
		}

		// The model also returns objects of internal types (revisions, global styles); only report real content.
		$ids = array_values(
			array_filter(
				(array) $ids,
				static function ( $id ) use ( $types ) {
					return in_array( get_post_type( (int) $id ), $types, true );
				}
			)
		);

		if ( empty( $ids ) ) {
			return $this->result(
				'content_no_language',
				Result::PASS,
				__( 'All content has a language', 'translate-words' ),
				__( 'Every post and page is assigned to a language.', 'translate-words' )
			);
		}

		$truncated = count( $ids ) > self::DETAIL_LIMIT;
		$details   = array();

		foreach ( array_slice( $ids, 0, self::DETAIL_LIMIT ) as $id ) {
			$details[] = array(
				'label'        => get_the_title( $id ) ? get_the_title( $id ) : __( '(no title)', 'translate-words' ),
				'message'      => __( 'This item has no language assigned.', 'translate-words' ),
				'action_url'   => (string) get_edit_post_link( $id, 'url' ),
				'action_label' => __( 'Edit', 'translate-words' ),
				'ignore_key'   => 'content_no_language:' . $id,
				'post_type'    => (string) get_post_type( $id ),
			);
		}

		return $this->result(
			'content_no_language',
			Result::WARNING,
			__( 'Content without a language', 'translate-words' ),
			__( 'Some content has no language and will not show on translated pages. Assign the default language to it.', 'translate-words' )
		)
			->with_action( 'assign_default_lang', __( 'Assign default language', 'translate-words' ) )
			->with_details( $details, $truncated );
	}

	/**
	 * Lists default-language posts that lack a translation in an active language.
	 *
	 * @since 2.3.0
	 *
	 * @param string   $type         Post type.
	 * @param object   $default_lang Default language.
	 * @param object[] $targets      Languages to translate into.
	 * @return Result
	 */
	private function missing_translations_result( string $type, $default_lang, array $targets ): Result {
		$object = get_post_type_object( $type );
		$name   = $object ? $object->labels->name : $type;
		$id     = 'content_' . $type;

		$scoped   = $this->scoped_ids( 'content' );
		$post_ids = $scoped ? array_values( array_filter( array_keys( $scoped ), static function ( $id ) use ( $type ) {
			return get_post_type( $id ) === $type;
		} ) ) : get_posts(
			array(
				'post_type'        => $type,
				'post_status'      => 'publish',
				'fields'           => 'ids',
				'posts_per_page'   => self::POST_LIMIT,
				'orderby'          => 'ID',
				'order'            => 'DESC',
				'no_found_rows'    => true,
				'suppress_filters' => true,
				'lang'             => '',
				'tax_query'        => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					array(
						'taxonomy' => 'lmat_language',
						'field'    => 'term_id',
						'terms'    => (int) $default_lang->term_id,
					),
				),
			)
		);

		$details   = array();
		$missing   = 0;
		$truncated = ! $scoped && count( $post_ids ) >= self::POST_LIMIT;

		foreach ( $post_ids as $post_id ) {
			$translations = $this->model->post->get_translations( (int) $post_id );

			foreach ( $targets as $lang ) {
				if ( $scoped ) {
					// A row recheck only revisits the languages of the rows it was asked about.
					if ( ! in_array( $lang->slug, $scoped[ (int) $post_id ] ?? array(), true ) ) {
						continue;
					}
				} else {
					++$this->coverage[ $lang->slug ]['total'];
					$this->coverage[ $lang->slug ]['sampled'] = $truncated;
				}

				if ( ! empty( $translations[ $lang->slug ] ) ) {
					if ( ! $scoped ) {
						++$this->coverage[ $lang->slug ]['translated'];
					}
					continue;
				}

				++$missing;
				if ( ! $scoped && count( $details ) >= self::DETAIL_LIMIT ) {
					$truncated = true;
					continue;
				}

				$title     = get_the_title( $post_id );
				$details[] = array(
					'label'        => '' !== $title ? $title : __( '(no title)', 'translate-words' ),
					'message'      => sprintf(
						/* translators: %s: language name with code */
						__( 'Not translated into %s.', 'translate-words' ),
						$this->lang_label( $lang )
					),
					'action_url'   => $this->new_translation_url( (int) $post_id, $lang->slug ),
					'action_label' => sprintf(
						/* translators: %s: language name */
						__( 'Translate %s', 'translate-words' ),
						$lang->name
					),
					'ignore_key'   => 'content:' . $post_id . ':' . $lang->slug,
					'post_type'    => $type,
					'language'     => $lang->slug,
				);
			}
		}

		if ( 0 === $missing ) {
			return $this->result(
				$id,
				Result::PASS,
				/* translators: %s: post type plural label */
				sprintf( __( '%s fully translated', 'translate-words' ), $name ),
				__( 'Every published item has a translation in each language.', 'translate-words' )
			);
		}

		return $this->result(
			$id,
			Result::WARNING,
			/* translators: %s: post type plural label */
			sprintf( __( '%s missing translations', 'translate-words' ), $name ),
			sprintf(
				/* translators: %d: number of missing translations */
				_n( '%d translation is missing.', '%d translations are missing.', $missing, 'translate-words' ),
				$missing
			)
		)
			->with_fix( admin_url( 'edit.php?post_type=' . rawurlencode( $type ) ), __( 'Bulk translate', 'translate-words' ) )
			->with_details( $details, $truncated );
	}

	/**
	 * Post ids (and languages) named by the row keys of a scoped recheck.
	 *
	 * @since 2.3.0
	 *
	 * @param string $prefix Row key prefix, such as `content` for `content:12:fr`.
	 * @return array<int,string[]> Languages by post id; empty when the run is not scoped to rows.
	 */
	private function scoped_ids( string $prefix ): array {
		$out = array();
		foreach ( $this->row_keys as $key ) {
			if ( preg_match( '/^' . preg_quote( $prefix, '/' ) . ':(\d+)(?::([a-z0-9_-]+))?$/i', (string) $key, $parts ) ) {
				$out[ (int) $parts[1] ] = array_merge( $out[ (int) $parts[1] ] ?? array(), isset( $parts[2] ) ? array( $parts[2] ) : array() );
			}
		}

		return $out;
	}

	/**
	 * Lists translations that are older than the default-language text they were made from.
	 *
	 * @since 2.3.0
	 *
	 * @param string[] $types        Translated post types.
	 * @param object   $default_lang Default language.
	 * @param object[] $targets      Languages to translate into.
	 * @return Result
	 */
	private function source_updated_result( array $types, $default_lang, array $targets ): Result {
		$fresh  = new Freshness( $this->model );
		$scoped = $this->scoped_ids( 'content_updated' );
		$rows   = array();

		foreach ( $types as $type ) {
			$post_ids = $scoped ? array_values( array_filter( array_keys( $scoped ), static function ( $id ) use ( $type ) {
				return get_post_type( $id ) === $type;
			} ) ) : get_posts(
				array(
					'post_type'        => $type,
					'post_status'      => 'publish',
					'fields'           => 'ids',
					'posts_per_page'   => self::POST_LIMIT,
					'orderby'          => 'ID',
					'order'            => 'DESC',
					'no_found_rows'    => true,
					'suppress_filters' => true,
					'lang'             => '',
					'tax_query'        => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
						array(
							'taxonomy' => 'lmat_language',
							'field'    => 'term_id',
							'terms'    => (int) $default_lang->term_id,
						),
					),
				)
			);

			foreach ( $post_ids as $post_id ) {
				$post_id      = (int) $post_id;
				$translations = $this->model->post->get_translations( $post_id );
				$source_ts    = 0;

				foreach ( $targets as $lang ) {
					if ( $scoped && ! in_array( $lang->slug, $scoped[ $post_id ] ?? array(), true ) ) {
						continue;
					}

					$translation_id = (int) ( $translations[ $lang->slug ] ?? 0 );
					if ( $translation_id <= 0 || 'publish' !== get_post_status( $translation_id ) || 'publish' !== get_post_status( $post_id ) ) {
						continue;
					}

					$source_ts = $source_ts > 0 ? $source_ts : $fresh->changed_at( $post_id );
					if ( 'source_updated' !== $fresh->classify( $post_id, $lang->slug, $source_ts, $fresh->changed_at( $translation_id ) ) ) {
						continue;
					}

					$title  = get_the_title( $post_id );
					$rows[] = array(
						'label'          => '' !== $title ? $title : __( '(no title)', 'translate-words' ),
						'message'        => sprintf(
							/* translators: %s: language name with code */
							__( 'The default language changed after the %s translation was last up to date.', 'translate-words' ),
							$this->lang_label( $lang )
						),
						'action_url'     => (string) get_edit_post_link( $translation_id, 'url' ),
						'action_label'   => sprintf(
							/* translators: %s: language name */
							__( 'Edit %s', 'translate-words' ),
							$lang->name
						),
						'ignore_key'     => 'content_updated:' . $post_id . ':' . $lang->slug,
						'post_type'      => $type,
						'language'       => $lang->slug,
						'translation_id' => $translation_id,
					);
				}
			}
		}

		if ( empty( $rows ) ) {
			return $this->result(
				'content_source_updated',
				Result::PASS,
				__( 'Translations are up to date with the default language', 'translate-words' ),
				__( 'No translation looks older than the default-language text.', 'translate-words' )
			);
		}

		$truncated = count( $rows ) > self::DETAIL_LIMIT;

		return $this->result(
			'content_source_updated',
			Result::WARNING,
			__( 'Default language content was updated', 'translate-words' ),
			sprintf(
				/* translators: %d: number of outdated translations */
				_n( '%d translation may need updating after the default language changed.', '%d translations may need updating after the default language changed.', count( $rows ), 'translate-words' ),
				count( $rows )
			)
		)->with_details( $scoped ? $rows : array_slice( $rows, 0, self::DETAIL_LIMIT ), $truncated && ! $scoped );
	}
}
