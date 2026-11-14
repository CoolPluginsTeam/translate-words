<?php
namespace Linguator\Includes\Services\Translation;

use WP_Term;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Taxonomy names carried with an AI post translation. */
class Post_Term_Translation_Service {
	const KEY_PREFIX = '_lmat_term_name_';
	const MARKER = '_lmat_ai_term_translation';
	private $model;
	private $options;

	public function __construct( $linguator ) {
		$this->model = $linguator->model;
		$this->options = $linguator->options ?? array();
	}

	/** Source assignments and their parents, limited to translated taxonomies. */
	private function source_terms( $post_id ) {
		$terms = array();
		foreach ( get_object_taxonomies( get_post_type( $post_id ) ) as $taxonomy ) {
			if ( ! $this->model->is_translated_taxonomy( $taxonomy ) ) {
				continue;
			}
			$assigned = get_the_terms( $post_id, $taxonomy );
			foreach ( is_array( $assigned ) ? $assigned : array() as $term ) {
				$terms[ $term->term_id ] = $term;
				foreach ( get_ancestors( $term->term_id, $taxonomy, 'taxonomy' ) as $parent_id ) {
					$parent = get_term( $parent_id, $taxonomy );
					if ( $parent instanceof WP_Term ) {
						$terms[ $parent_id ] = $parent;
					}
				}
			}
		}
		return $terms;
	}

	/** Keep manual translations; repair copies and refresh unchanged AI names. */
	private function is_pending( $source, $target_id ) {
		if ( ! $target_id ) {
			return true;
		}
		$target = get_term( $target_id, $source->taxonomy );
		if ( ! $target instanceof WP_Term || $source->term_id === $target->term_id ) {
			return false;
		}
		$marker = get_term_meta( $target_id, self::MARKER, true );
		if ( is_array( $marker ) && isset( $marker['source'], $marker['name'] ) ) {
			return $marker['source'] !== $source->name && $marker['name'] === $target->name;
		}
		return $source->name === $target->name;
	}

	/** Provider strings keyed by source ID, including unassigned ancestors. */
	public function get_pending_names( $post_id, array $languages ) {
		$names = array();
		foreach ( $this->source_terms( $post_id ) as $term ) {
			$source_language = $this->model->term->get_language( $term->term_id );
			if ( ! $source_language ) {
				continue;
			}
			foreach ( $languages as $slug ) {
				$language = $this->model->get_language( $slug );
				if ( $language && $language->slug !== $source_language->slug
					&& $this->is_pending( $term, $this->model->term->get_translation( $term->term_id, $language->slug ) ) ) {
					$names[ self::KEY_PREFIX . $term->term_id ] = $term->name;
					break;
				}
			}
		}
		return $names;
	}

	/** Remove transport-only fields before any custom field is written. */
	public function extract_names( array &$fields ) {
		$names = array();
		foreach ( $fields as $key => $value ) {
			if ( is_string( $key ) && 0 === strpos( $key, self::KEY_PREFIX ) ) {
				if ( preg_match( '/^' . self::KEY_PREFIX . '([1-9][0-9]*)$/D', $key, $match ) && is_string( $value ) ) {
					$name = sanitize_text_field( $value );
					if ( '' !== trim( $name ) ) {
						$names[ (int) $match[1] ] = $name;
					}
				}
				unset( $fields[ $key ] );
			}
		}
		return $names;
	}

	/** Resolve parents first and reuse translations shared by other posts. */
	private function translate_term( $source, $language, $names, $allowed, &$resolved ) {
		$id = (int) $source->term_id;
		if ( isset( $resolved[ $id ] ) ) {
			return $resolved[ $id ];
		}
		$resolved[ $id ] = 0; // Guard malformed cyclic hierarchies.
		$target_id = (int) $this->model->term->get_translation( $id, $language->slug );
		if ( ! $this->is_pending( $source, $target_id ) || ! isset( $names[ $id ], $allowed[ self::KEY_PREFIX . $id ] ) ) {
			$resolved[ $id ] = $target_id;
			return $target_id;
		}
		$taxonomy = get_taxonomy( $source->taxonomy );
		if ( ! $taxonomy || ! current_user_can( $taxonomy->cap->edit_terms ) || ! current_user_can( 'edit_term', $id )
			|| ( $target_id && ! current_user_can( 'edit_term', $target_id ) ) ) {
			return new WP_Error( 'lmat_term_forbidden', __( 'You cannot translate these terms.', 'translate-words' ), array( 'status' => 403 ) );
		}
		$parent_id = 0;
		if ( $source->parent ) {
			$parent = get_term( $source->parent, $source->taxonomy );
			if ( $parent instanceof WP_Term ) {
				$parent_id = $this->translate_term( $parent, $language, $names, $allowed, $resolved );
				if ( is_wp_error( $parent_id ) ) {
					return $parent_id;
				}
				if ( ! $parent_id ) {
					return new WP_Error( 'lmat_term_parent_missing', __( 'The parent term has not been translated.', 'translate-words' ) );
				}
			}
		}
		if ( ! $target_id ) {
			$existing_id = $this->model->term_exists( $names[ $id ], $source->taxonomy, $parent_id, $language );
			$existing = $existing_id ? get_term( $existing_id, $source->taxonomy ) : null;
			$group = $existing_id ? $this->model->term->get_translations( $existing_id ) : array();
			// A manually created target-language term can be linked, provided it has
			// the right parent and does not already belong to another translation group.
			if ( $existing instanceof WP_Term && (int) $existing->parent === (int) $parent_id
				&& ! array_diff( array_keys( $group ), array( $language->slug ) )
				&& current_user_can( 'edit_term', $existing_id ) ) {
				$translations = $this->model->term->get_translations( $id );
				$translations[ $language->slug ] = (int) $existing_id;
				$this->model->term->save_translations( $id, $translations );
				$resolved[ $id ] = (int) $existing_id;
				return (int) $existing_id;
			}
		}
		// Renaming an existing term must not change URLs already used by other posts.
		$args = array( 'name' => $names[ $id ], 'parent' => $parent_id );
		if ( ! $target_id ) {
			$keep_slug = 'slug_keep' === ( $this->options['ai_translation_configuration']['slug_translation_option'] ?? 'title_translate' );
			$args['slug'] = $keep_slug ? $source->slug : sanitize_title( $names[ $id ] );
		}
		$created = ! $target_id;
		if ( $target_id ) {
			$result = $this->model->term->update( $target_id, wp_slash( $args ) );
		} else {
			$args['description'] = $source->description;
			$result = $this->model->term->insert( wp_slash( $names[ $id ] ), $source->taxonomy, $language, wp_slash( $args ) );
		}
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$target_id = (int) $result['term_id'];
		$translations = $this->model->term->get_translations( $id );
		$translations[ $language->slug ] = $target_id;
		$this->model->term->save_translations( $id, $translations );
		if ( $created ) {
			// Preserve configured term metadata, as ordinary post duplication does.
			do_action( 'lmat_duplicate_term', $id, $target_id, $language->slug );
		}
		update_term_meta( $target_id, self::MARKER, wp_slash( array( 'source' => $source->name, 'name' => $names[ $id ] ) ) );
		$resolved[ $id ] = $target_id;
		return $target_id;
	}

	/** Handle the first real save in Classic Editor, Gutenberg REST, or a builder. */
	public function reapply_pending( $target_id ) {
		$source_id = (int) get_post_meta( $target_id, '_lmat_pending_ai_term_source', true );
		if ( ! $source_id || 'auto-draft' === get_post_status( $target_id ) ) {
			return;
		}
		$language = $this->model->post->get_language( $target_id );
		if ( ! $language || (int) $this->model->post->get_translation( $source_id, $language->slug ) !== (int) $target_id ) {
			return;
		}
		$result = $this->apply( $source_id, (int) $target_id, array() );
		if ( ! is_wp_error( $result ) ) {
			delete_post_meta( $target_id, '_lmat_pending_ai_term_source' );
		}
	}

	/** Apply names belonging to this source post, then assign target-language terms. */
	public function apply( $source_id, $target_id, array $names ) {
		$language = $this->model->post->get_language( $target_id );
		$source_language = $this->model->post->get_language( $source_id );
		if ( $source_id === $target_id || ! $language || ! $source_language || $language->slug === $source_language->slug
			|| get_post_type( $source_id ) !== get_post_type( $target_id )
			|| ! current_user_can( 'edit_post', $source_id ) || ! current_user_can( 'edit_post', $target_id ) ) {
			return new WP_Error( 'lmat_invalid_term_translation', __( 'Invalid source or target post for term translation.', 'translate-words' ), array( 'status' => 403 ) );
		}
		$allowed = $this->get_pending_names( $source_id, array( $language->slug ) );
		$resolved = array();
		foreach ( $this->source_terms( $source_id ) as $term ) {
			$result = $this->translate_term( $term, $language, $names, $allowed, $resolved );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}
		$assigned = array();
		foreach ( get_object_taxonomies( get_post_type( $source_id ) ) as $taxonomy ) {
			if ( ! $this->model->is_translated_taxonomy( $taxonomy ) ) {
				continue;
			}
			$terms = get_the_terms( $source_id, $taxonomy );
			if ( ! is_array( $terms ) || ! $terms ) {
				continue;
			}
			$ids = array();
			foreach ( $terms as $term ) {
				$id = $this->model->term->get_translation( $term->term_id, $language->slug );
				if ( ! $id || ! current_user_can( 'assign_term', $id ) ) {
					continue 2; // Never replace an assignment with an incomplete set.
				}
				$ids[] = (int) $id;
			}
			$result = wp_set_object_terms( $target_id, $ids, $taxonomy );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			$assigned[ $taxonomy ] = $ids;
		}
		return $assigned;
	}
}
