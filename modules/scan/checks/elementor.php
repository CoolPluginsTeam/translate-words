<?php
/**
 * Diagnostics for Linguator's built-in Elementor template integration.
 *
 * @package Linguator
 */
namespace Linguator\Modules\Scan\Checks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Linguator\Modules\Scan\Abstract_Check;
use Linguator\Modules\Scan\Elementor_Fields;
use Linguator\Modules\Scan\Freshness;
use Linguator\Modules\Scan\Frontend_Inspector;
use Linguator\Modules\Scan\Link_Tools;
use Linguator\Modules\Scan\Result;

class Elementor extends Abstract_Check {
	use Link_Tools;

	const DOCUMENT_LIMIT = 200;
	const DETAIL_LIMIT = 100;
	protected $section = 'elementor';
	protected $group = 'Elementor';

	public static function available(): bool {
		return defined( 'ELEMENTOR_VERSION' ) || class_exists( '\Elementor\Plugin' ) || did_action( 'elementor/loaded' ) > 0;
	}

	/** Parse the actual element tree, rather than matching text in widget settings. */
	public static function contains_switcher( array $elements, int $depth = 0 ): bool {
		if ( $depth > 50 ) {
			return false;
		}
		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}
			if ( 'lmat_widget' === ( $element['widgetType'] ?? '' ) ) {
				return true;
			}
			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) && self::contains_switcher( $element['elements'], $depth + 1 ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Template counts by document type, for the Elementor insights panel.
	 *
	 * @var array{checked:int,types:array<string,int>,sampled:bool}
	 */
	private $summary = array( 'checked' => 0, 'widgets' => 0, 'types' => array(), 'sampled' => false );

	public function extra(): array {
		return $this->summary['checked'] > 0 && ! $this->row_keys && null === $this->only ? array( 'elementor' => $this->summary ) : array();
	}

	public function run(): array {
		$this->summary = array( 'checked' => 0, 'widgets' => 0, 'types' => array(), 'sampled' => false );
		if ( ! self::available() ) {
			return array();
		}
		$types = array_values( array_filter( array( 'elementor_library', 'elementor_component' ), 'post_type_exists' ) );
		if ( ! $types ) {
			return array();
		}
		$documents = get_posts( array(
			'post_type' => $types,
			'post_status' => array( 'publish', 'draft', 'pending', 'private', 'future' ),
			'posts_per_page' => self::DOCUMENT_LIMIT + 1,
			'orderby' => 'ID', 'order' => 'DESC', 'suppress_filters' => true,
			'post__in' => $this->scoped_document_ids(),
		) );
		$sampled = count( $documents ) > self::DOCUMENT_LIMIT;
		$documents = array_slice( $documents, 0, self::DOCUMENT_LIMIT );
		$buckets = array_fill_keys( array( 'missing', 'unpublished', 'language', 'layout', 'type', 'orphan', 'empty', 'outdated', 'untranslated', 'repeater', 'links' ), array() );
		$default = $this->default_lang();
		$languages = $this->active_languages();
		$active_slugs = array_map( static function ( $language ) { return $language->slug; }, $languages );
		$checked = 0;
		foreach ( $documents as $document ) {
			$type = (string) get_post_meta( $document->ID, '_elementor_template_type', true );
			if ( 'kit' === $type ) {
				continue;
			}
			++$checked;
			$label = '' !== $type ? $type : (string) $document->post_type;
			$this->summary['types'][ $label ] = ( $this->summary['types'][ $label ] ?? 0 ) + 1;
			$this->summary['widgets']        += Elementor_Fields::count_widgets( (int) $document->ID );
			$language = $this->model->post->get_language( $document->ID );
			$slug = $language ? $language->slug : '';
			if ( ! $slug ) {
				$buckets['language'][] = $this->detail( $document, '', __( 'This template has no language assigned.', 'translate-words' ), 'language' );
			}
			$raw = get_post_meta( $document->ID, '_elementor_data', true );
			$data = is_array( $raw ) ? $raw : ( is_string( $raw ) && '' !== trim( $raw ) ? json_decode( $raw, true, 128 ) : null );
			$has_data = is_array( $raw ) || ( is_string( $raw ) && '' !== trim( $raw ) );
			$valid_data = is_array( $data ) && ( ! $data || array_keys( $data ) === range( 0, count( $data ) - 1 ) );
			if ( ( $has_data && ! $valid_data ) || ( 'publish' === $document->post_status && empty( $data ) ) ) {
				$buckets['layout'][] = $this->detail( $document, $slug, __( 'Elementor layout data is empty or invalid. Open the template in Elementor and review its layout.', 'translate-words' ), 'layout' );
			}
			$translations = $this->model->post->get_translations( $document->ID );
			if ( $slug && $default && $slug !== $default && in_array( $slug, $active_slugs, true ) && empty( $translations[ $default ] ) ) {
				$buckets['orphan'][] = $this->detail( $document, $slug, __( 'This translation is not linked to a default-language template.', 'translate-words' ), 'orphan' );
			}
			if ( ! $default || $slug !== $default || 'publish' !== $document->post_status ) {
				continue;
			}
			foreach ( $languages as $target ) {
				if ( $target->slug === $default ) {
					continue;
				}
				$translated = ! empty( $translations[ $target->slug ] ) ? get_post( (int) $translations[ $target->slug ] ) : null;
				if ( ! $translated || in_array( $translated->post_status, array( 'trash', 'auto-draft' ), true ) ) {
					$row = $this->detail( $document, $target->slug, sprintf( __( 'No template translation exists for %s.', 'translate-words' ), $this->lang_label( $target ) ), 'missing' );
					$row['action_url'] = $this->new_translation_url( $document->ID, $target->slug );
					$row['action_label'] = sprintf( __( 'Translate %s', 'translate-words' ), $target->name );
					$buckets['missing'][] = $row;
					continue;
				}
				if ( 'publish' !== $translated->post_status ) {
					$buckets['unpublished'][] = $this->detail( $translated, $target->slug, __( 'The linked template translation is not published.', 'translate-words' ), 'unpublished' );
				}
				$translated_type = (string) get_post_meta( $translated->ID, '_elementor_template_type', true );
				if ( $translated->post_type !== $document->post_type || ( $type && $type !== $translated_type ) ) {
					$buckets['type'][] = $this->detail( $translated, $target->slug, sprintf( __( 'The translation should use the same template type as its source: %s.', 'translate-words' ), $type ?: $document->post_type ), 'type' );
				}
				if ( 'publish' !== $translated->post_status ) {
					continue;
				}
				if ( $this->has_widgets( $document->ID ) && ! $this->has_widgets( $translated->ID ) ) {
					$buckets['empty'][] = $this->detail( $translated, $target->slug, __( 'This translation has no Elementor widgets, but the original has. Visitors may see an empty template.', 'translate-words' ), 'empty' );
				}
				$this->compare_widgets( $document, $translated, $target, $buckets );
				$fresh = $fresh ?? new Freshness( $this->model );
				if ( 'source_updated' === $fresh->classify( $document->ID, $target->slug, $fresh->changed_at( $document->ID ), $fresh->changed_at( $translated->ID ) ) ) {
					$buckets['outdated'][] = $this->detail( $translated, $target->slug, __( 'The original was updated after this translation. Review the changes and update the translated template.', 'translate-words' ), 'outdated' );
				}
			}
		}
		$this->summary['checked'] = $checked;
		$this->summary['sampled'] = $sampled;
		$labels = array(
			'missing' => array( __( 'Missing Template Translations', 'translate-words' ), __( 'Published source templates need a translation in each active language.', 'translate-words' ) ),
			'unpublished' => array( __( 'Unpublished Template Translations', 'translate-words' ), __( 'Publish linked translations so visitors can use the translated templates.', 'translate-words' ) ),
			'language' => array( __( 'Templates Without a Language', 'translate-words' ), __( 'Assign a language before linking or translating these templates.', 'translate-words' ) ),
			'layout' => array( __( 'Template Layout Issues', 'translate-words' ), __( 'Review empty or invalid Elementor layout data.', 'translate-words' ) ),
			'type' => array( __( 'Template Type Mismatches', 'translate-words' ), __( 'Source templates and their translations should use the same document type.', 'translate-words' ) ),
			'orphan' => array( __( 'Unlinked Template Translations', 'translate-words' ), __( 'Link these translations to their default-language source templates.', 'translate-words' ) ),
			'untranslated' => array( __( 'Same texts as the original language', 'translate-words' ), __( 'These widgets still show the exact same words as the original language. Open each one in Elementor and translate the text — or ignore the warning if it should stay the same (for example a brand or product name).', 'translate-words' ) ),
			'repeater' => array( __( 'Repeater Row Mismatches', 'translate-words' ), __( 'A list, tab or accordion has a different number of rows in the translation. Match the rows to the original.', 'translate-words' ) ),
			'links' => array( __( 'Template Link Issues', 'translate-words' ), __( 'Links in the translated template go to a missing page or to a page in another language.', 'translate-words' ) ),
			'empty' => array( __( 'Empty Template Translations', 'translate-words' ), __( 'Copy the layout from the original template, then translate its text.', 'translate-words' ) ),
			'outdated' => array( __( 'Outdated Template Translations', 'translate-words' ), __( 'The original template changed after these translations. Review the changes and update the translated templates.', 'translate-words' ) ),
		);
		$results = array();
		foreach ( $buckets as $key => $details ) {
			$id = 'elementor_' . $key;
			if ( $this->row_keys ) {
				$details = array_values( array_filter( $details, function ( $row ) { return in_array( $row['ignore_key'], $this->row_keys, true ); } ) );
			}
			if ( ! $this->wants( $id ) || ! $checked ) {
				continue;
			}
			$result = $this->result( $id, $details ? Result::WARNING : Result::PASS, $labels[ $key ][0], $details ? $labels[ $key ][1] : __( 'No issues found in the scanned Elementor templates.', 'translate-words' ) );
			$results[] = $result->with_details( array_slice( $details, 0, self::DETAIL_LIMIT ), count( $details ) > self::DETAIL_LIMIT )->with_fix( admin_url( 'edit.php?post_type=elementor_library' ), __( 'Manage templates', 'translate-words' ) );
		}
		if ( ! $checked && $this->wants( 'elementor_none' ) ) {
			$results[] = $this->result( 'elementor_none', Result::INFO, __( 'No Elementor templates found', 'translate-words' ), __( 'No saved Elementor templates or components were found to scan.', 'translate-words' ) );
		}
		if ( $sampled && $this->wants( 'elementor_sampled' ) ) {
			$results[] = $this->result( 'elementor_sampled', Result::INFO, __( 'Elementor scan limit reached', 'translate-words' ), sprintf( __( 'Only the latest %d templates and components were scanned. Older documents have not been checked.', 'translate-words' ), self::DOCUMENT_LIMIT ) );
		}
		return $results;
	}

	private function scoped_document_ids(): array {
		if ( ! $this->row_keys ) {
			return array();
		}
		$ids = array();
		foreach ( $this->row_keys as $key ) {
			if ( ! preg_match( '/^elementor:([^:]+):(\d+):/', $key, $parts ) ) {
				continue;
			}
			$id = (int) $parts[2];
			$ids[] = $id;
			if ( in_array( $parts[1], array( 'missing', 'unpublished', 'type', 'empty', 'outdated', 'untranslated', 'repeater', 'links' ), true ) ) {
				$translations = $this->model->post->get_translations( $id );
				if ( ! empty( $translations[ $this->default_lang() ] ) ) {
					$ids[] = (int) $translations[ $this->default_lang() ];
				}
			}
		}
		return $ids ? array_values( array_unique( $ids ) ) : array( 0 );
	}

	/**
	 * Widget-level differences between a template and one translation: text still in the original language,
	 * different repeater row counts, and links that lead nowhere or to another language.
	 */
	private function compare_widgets( $source, $translated, $target, array &$buckets ): void {
		$source_map      = Elementor_Fields::element_map( Elementor_Fields::tree( $source->ID ) );
		$translation_map = Elementor_Fields::element_map( Elementor_Fields::tree( $translated->ID ) );

		foreach ( $source_map as $element_id => $element ) {
			if ( empty( $translation_map[ $element_id ] ) ) {
				continue;
			}
			$other  = $translation_map[ $element_id ];
			$widget      = Elementor_Fields::widget_label( $element['widget_type'] );
			$widget_type = $element['widget_type'];

			foreach ( $element['fields'] as $field_key => $original ) {
				if ( Elementor_Fields::is_untranslated( $field_key, $original, $other['fields'][ $field_key ] ?? '', $target->slug ) ) {
					$row                       = Elementor_Fields::same_text_row(
						$widget_type,
						$field_key,
						$original,
						(string) get_the_title( $translated->ID ),
						(string) $target->name,
						$this->detail( $translated, $target->slug, '', 'untranslated' )
					);
					$row['ignore_key']        .= ':' . $element_id . ':' . md5( $field_key );
					$row['action_url']         = '' !== $row['action_url'] ? add_query_arg( Frontend_Inspector::FOCUS_ARG, rawurlencode( (string) $element_id ), $row['action_url'] ) : '';
					$buckets['untranslated'][] = $row;
				}
			}

			foreach ( $element['repeaters'] as $repeater => $count ) {
				$other_count = (int) ( $other['repeaters'][ $repeater ] ?? 0 );
				if ( $count !== $other_count ) {
					$row = $this->detail( $translated, $target->slug, sprintf( /* translators: 1: widget name, 2: repeater name, 3: rows in the original, 4: rows in the translation */ __( '%1$s › "%2$s" has %3$d rows in the original but %4$d in the translation.', 'translate-words' ), $widget, $repeater, $count, $other_count ), 'repeater' );
					$row['ignore_key'] .= ':' . $element_id . ':' . md5( $repeater );
					$buckets['repeater'][] = $row;
				}
			}

			foreach ( $other['links'] as $field_key => $url ) {
				if ( ! $this->is_internal( $url ) || $this->is_asset( $url ) ) {
					continue;
				}
				$message = '';
				$dead    = $this->dead_target( $url );
				if ( $dead ) {
					$message = $dead['message'];
				} else {
					$post_id    = $this->resolve_post_id( $url );
					$link_lang  = $post_id > 0 ? $this->model->post->get_language( $post_id ) : null;
					$suggestion = $link_lang && $link_lang->slug !== $target->slug ? (int) $this->model->post->get_translation( $post_id, $target->slug ) : 0;
					if ( $suggestion > 0 && 'publish' === get_post_status( $suggestion ) ) {
						$message = sprintf( /* translators: 1: link path, 2: language name */ __( 'This link goes to "%1$s", which is in another language. Use the %2$s page instead.', 'translate-words' ), $this->readable( $url ), $target->name );
					}
				}
				if ( '' !== $message ) {
					$row = $this->detail( $translated, $target->slug, $widget . ' › ' . Elementor_Fields::field_label( $field_key ) . ': ' . $message, 'links' );
					$row['ignore_key'] .= ':' . $element_id . ':' . md5( $url );
					$buckets['links'][] = $row;
				}
			}
		}
	}

	private function short( string $text ): string {
		return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, 80 ) : substr( $text, 0, 80 );
	}

	/** Whether an Elementor document holds at least one widget. */
	private function has_widgets( int $post_id ): bool {
		$raw  = get_post_meta( $post_id, '_elementor_data', true );
		$data = is_array( $raw ) ? $raw : ( is_string( $raw ) ? json_decode( $raw, true, 128 ) : null );
		return is_array( $data ) && $this->tree_has_widget( $data, 0 );
	}

	private function tree_has_widget( array $elements, int $depth ): bool {
		if ( $depth > 50 ) {
			return false;
		}
		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}
			if ( ! empty( $element['widgetType'] ) || ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) && $this->tree_has_widget( $element['elements'], $depth + 1 ) ) ) {
				return true;
			}
		}
		return false;
	}

	private function detail( $document, string $slug, string $message, string $kind ): array {
		$edit = (string) get_edit_post_link( $document->ID, 'url' );
		$editor = $edit ? add_query_arg( 'action', 'elementor', $edit ) : '';
		return array(
			'label' => get_the_title( $document->ID ) ?: __( '(no title)', 'translate-words' ),
			'message' => $message, 'post_type' => $document->post_type, 'language' => $slug,
			'edit_url' => $editor, 'action_url' => $editor,
			'action_label' => __( 'Edit in Elementor', 'translate-words' ),
			'ignore_key' => 'elementor:' . $kind . ':' . $document->ID . ':' . $slug,
			'template_type' => (string) ( get_post_meta( $document->ID, '_elementor_template_type', true ) ?: $document->post_type ),
		);
	}
}
