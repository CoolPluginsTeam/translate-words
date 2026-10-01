<?php
/**
 * Media translation service.
 *
 * @package Linguator
 */

namespace Linguator\Includes\Services\Media;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Linguator\Includes\Base\Linguator_Base;
use WP_Post;

/**
 * Extracts media strings and applies translated attachment metadata.
 *
 * This class intentionally registers no hooks. Translation entry points can
 * share it without changing existing media behavior until they opt in.
 */
class Media_Translation_Service {

	/**
	 * Main Linguator model.
	 *
	 * @var \Linguator\Includes\Other\Linguator_Model
	 */
	private $model;

	/**
	 * Linguator options.
	 *
	 * @var array|\ArrayAccess
	 */
	private $options;

	/**
	 * Constructor.
	 *
	 * @param Linguator_Base $linguator Main Linguator object.
	 */
	public function __construct( Linguator_Base $linguator ) {
		$this->model   = $linguator->model;
		$this->options = $linguator->options;
	}

	/**
	 * Whether media translation is enabled.
	 *
	 * @return bool
	 */
	public function is_enabled() {
		return ! empty( $this->options['media_support'] );
	}

	/**
	 * Gets the translatable strings stored on an attachment.
	 *
	 * Empty fields are omitted so they are not sent to an AI provider.
	 *
	 * @param int $attachment_id Attachment post ID.
	 * @return array{id?: int, title?: string, alt?: string, caption?: string, description?: string}
	 */
	public function get_attachment_strings( $attachment_id ) {
		$attachment_id = absint( $attachment_id );
		if ( 0 === $attachment_id ) {
			return array();
		}

		$attachment    = get_post( $attachment_id );

		if ( ! $attachment instanceof WP_Post || 'attachment' !== $attachment->post_type ) {
			return array();
		}

		$strings = array( 'id' => $attachment_id );
		$fields  = array(
			'title'       => $attachment->post_title,
			'caption'     => $attachment->post_excerpt,
			'description' => $attachment->post_content,
			'alt'         => get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
		);

		foreach ( $fields as $name => $value ) {
			if ( is_string( $value ) && '' !== trim( $value ) ) {
				$strings[ $name ] = $value;
			}
		}

		return 1 === count( $strings ) ? array() : $strings;
	}

	/**
	 * Gets the featured-image strings for a post.
	 *
	 * @param int $post_id Source post ID.
	 * @return array{id?: int, title?: string, alt?: string, caption?: string, description?: string}
	 */
	public function get_featured_image_strings( $post_id ) {
		$post_id = absint( $post_id );

		if ( 0 === $post_id || ! $this->is_enabled() ) {
			return array();
		}

		return $this->get_attachment_strings( get_post_thumbnail_id( $post_id ) );
	}

	/**
	 * Collects attachment IDs referenced by Gutenberg blocks or classic HTML.
	 *
	 * @param string $content Post content.
	 * @return int[] Unique attachment IDs.
	 */
	public function collect_content_attachment_ids( $content ) {
		$ids = array();

		if ( ! is_string( $content ) || '' === trim( $content ) ) {
			return array();
		}

		if ( has_blocks( $content ) ) {
			$blocks = parse_blocks( $content );
			$this->collect_block_attachment_ids( $blocks, $ids );
		}

		if ( preg_match_all( '/wp-image-(\d+)/', $content, $matches ) ) {
			$this->add_attachment_ids( $matches[1], $ids );
		}

		if ( preg_match_all( '/\b(?:data-id|data-media-id)=["\'](\d+)/', $content, $matches ) ) {
			$this->add_attachment_ids( $matches[1], $ids );
		}

		return array_map( 'intval', array_keys( $ids ) );
	}

	/**
	 * Gets strings for attachments referenced inside post content.
	 *
	 * The featured image is excluded because it is translated separately.
	 *
	 * @param int $post_id Source post ID.
	 * @return array<int, array{id: int, title?: string, alt?: string, caption?: string, description?: string}>
	 */
	public function get_content_media_strings( $post_id ) {
		$post_id = absint( $post_id );

		if ( 0 === $post_id || ! $this->is_enabled() ) {
			return array();
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return array();
		}

		return $this->get_media_strings(
			$this->collect_content_attachment_ids( $post->post_content ),
			(int) get_post_thumbnail_id( $post_id )
		);
	}

	/**
	 * Collects attachment IDs from Elementor media-control values.
	 *
	 * Elementor media values use an array containing a numeric ID and URL.
	 *
	 * @param mixed $data Elementor elements or settings tree.
	 * @return int[] Unique attachment IDs.
	 */
	public function collect_elementor_attachment_ids( $data ) {
		$ids = array();
		$this->walk_elementor_attachment_ids( $data, $ids );

		return array_values( $ids );
	}

	/**
	 * Gets strings for attachments referenced by Elementor.
	 *
	 * @param int $post_id Source post ID.
	 * @return array<int, array{id: int, title?: string, alt?: string, caption?: string, description?: string}>
	 */
	public function get_elementor_media_strings( $post_id ) {
		$post_id = absint( $post_id );

		if ( 0 === $post_id || ! $this->is_enabled() ) {
			return array();
		}

		if ( ! get_post( $post_id ) instanceof WP_Post ) {
			return array();
		}

		$elements = array();

		if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->documents ) ) {
			$document = \Elementor\Plugin::$instance->documents->get( $post_id, false );
			if ( $document && method_exists( $document, 'get_elements_data' ) ) {
				$elements = $document->get_elements_data();
			}
		}

		if ( empty( $elements ) ) {
			$elements = get_post_meta( $post_id, '_elementor_data', true );

			if ( is_string( $elements ) && '' !== $elements ) {
				$elements = json_decode( $elements, true );
			}
		}

		if ( ! is_array( $elements ) || empty( $elements ) ) {
			return array();
		}

		return $this->get_media_strings(
			$this->collect_elementor_attachment_ids( $elements ),
			(int) get_post_thumbnail_id( $post_id )
		);
	}

	/**
	 * Normalizes translated media strings from either a list or ID-keyed map.
	 *
	 * @param mixed $translations Decoded translation payload.
	 * @return array<int, array{title?: string, alt?: string, caption?: string, description?: string}>
	 */
	public function normalize_translations( $translations ) {
		if ( ! is_array( $translations ) || empty( $translations ) ) {
			return array();
		}

		$normalized = array();
		$is_list    = array_keys( $translations ) === range( 0, count( $translations ) - 1 );

		foreach ( $translations as $key => $translation ) {
			if ( ! is_array( $translation ) ) {
				continue;
			}

			$attachment_id = $is_list && isset( $translation['id'] ) ? absint( $translation['id'] ) : absint( $key );
			if ( 0 === $attachment_id ) {
				continue;
			}

			$fields = array();
			foreach ( array( 'title', 'alt', 'caption', 'description' ) as $field ) {
				if (
					isset( $translation[ $field ] )
					&& is_string( $translation[ $field ] )
					&& '' !== trim( $translation[ $field ] )
				) {
					$fields[ $field ] = $translation[ $field ];
				}
			}

			if ( ! empty( $fields ) ) {
				$normalized[ $attachment_id ] = $fields;
			}
		}

		return $normalized;
	}

	/**
	 * Resolves or creates an attachment translation.
	 *
	 * The source attachment is never returned because writing translated
	 * metadata to a shared attachment would overwrite the source language.
	 *
	 * @param int                                            $source_attachment_id Source attachment ID.
	 * @param \Linguator\Includes\Other\Linguator_Language|string $language             Target language.
	 * @return int Translated attachment ID, or 0 on failure.
	 */
	public function resolve_translated_attachment( $source_attachment_id, $language ) {
		$source_attachment_id = absint( $source_attachment_id );
		if ( 0 === $source_attachment_id ) {
			return 0;
		}

		$source_attachment    = get_post( $source_attachment_id );

		if (
			! $this->is_enabled()
			|| ! $source_attachment instanceof WP_Post
			|| 'attachment' !== $source_attachment->post_type
			|| empty( $language )
		) {
			return 0;
		}

		$translated_id = (int) $this->model->post->get_translation( $source_attachment_id, $language );
		$translated_attachment = $translated_id ? get_post( $translated_id ) : null;

		// Translation links can outlive deleted attachments. Replace stale links
		// instead of treating a missing media record as an existing translation.
		if ( 0 === $translated_id || ! $translated_attachment instanceof WP_Post || 'attachment' !== $translated_attachment->post_type ) {
			$translated_id = (int) $this->model->post->create_media_translation( $source_attachment_id, $language );
		}

		if ( 0 === $translated_id || $source_attachment_id === $translated_id ) {
			return 0;
		}

		$translated_attachment = get_post( $translated_id );

		if ( ! $translated_attachment instanceof WP_Post || 'attachment' !== $translated_attachment->post_type ) {
			return 0;
		}

		return $translated_id;
	}

	/**
	 * Writes translated strings to a translated attachment.
	 *
	 * Empty values are ignored. The method refuses to update the source
	 * attachment when no separate language-specific record exists.
	 *
	 * @param int   $target_attachment_id Target attachment ID.
	 * @param array $translations         Translated attachment strings.
	 * @param int   $source_attachment_id Source attachment ID.
	 * @return bool Whether at least one field was written.
	 */
	public function write_attachment_translations( $target_attachment_id, $translations, $source_attachment_id = 0 ) {
		$target_attachment_id = absint( $target_attachment_id );
		$source_attachment_id = absint( $source_attachment_id );

		if ( 0 === $target_attachment_id ) {
			return false;
		}

		$target_attachment    = get_post( $target_attachment_id );
		$source_attachment    = 0 < $source_attachment_id ? get_post( $source_attachment_id ) : null;

		if (
			! is_array( $translations )
			|| empty( $translations )
			|| ! $target_attachment instanceof WP_Post
			|| 'attachment' !== $target_attachment->post_type
			|| ( 0 < $source_attachment_id && $source_attachment_id === $target_attachment_id )
			|| (
				0 < $source_attachment_id
				&& ( ! $source_attachment instanceof WP_Post || 'attachment' !== $source_attachment->post_type )
			)
		) {
			return false;
		}

		$post_update = array( 'ID' => $target_attachment_id );

		if (
			isset( $translations['title'] )
			&& is_string( $translations['title'] )
			&& '' !== trim( $translations['title'] )
		) {
			$title = sanitize_text_field( $translations['title'] );
			if ( '' !== trim( $title ) ) {
				$post_update['post_title'] = $title;
			}
		}

		if (
			isset( $translations['caption'] )
			&& is_string( $translations['caption'] )
			&& '' !== trim( $translations['caption'] )
		) {
			$caption = wp_kses_post( $translations['caption'] );
			if ( '' !== trim( $caption ) ) {
				$post_update['post_excerpt'] = $caption;
			}
		}

		if (
			isset( $translations['description'] )
			&& is_string( $translations['description'] )
			&& '' !== trim( $translations['description'] )
		) {
			$description = wp_kses_post( $translations['description'] );
			if ( '' !== trim( $description ) ) {
				$post_update['post_content'] = $description;
			}
		}

		$updated = false;

		if ( 1 < count( $post_update ) ) {
			$result = wp_update_post( wp_slash( $post_update ), true );
			if ( is_wp_error( $result ) ) {
				return false;
			}
			$updated = true;
		}

		if ( isset( $translations['alt'] ) && is_string( $translations['alt'] ) && '' !== trim( $translations['alt'] ) ) {
			$alt = sanitize_text_field( $translations['alt'] );
			if ( '' !== trim( $alt ) ) {
				update_post_meta( $target_attachment_id, '_wp_attachment_image_alt', $alt );
				$updated = true;
			}
		}

		return $updated;
	}

	/**
	 * Applies translations to attachments referenced by a translated post.
	 *
	 * @param int   $target_post_id Target post ID.
	 * @param mixed $translations   Decoded translation payload.
	 * @return bool Whether at least one attachment was updated.
	 */
	public function apply_content_media_translations( $target_post_id, $translations ) {
		$target_post_id = absint( $target_post_id );

		if ( 0 === $target_post_id || ! $this->is_enabled() ) {
			return false;
		}

		$language = $this->model->post->get_language( $target_post_id );
		$items    = $this->normalize_translations( $translations );

		if ( empty( $language ) || empty( $items ) ) {
			return false;
		}

		$updated = false;

		foreach ( $items as $source_attachment_id => $fields ) {
			$translated_id = $this->resolve_translated_attachment( $source_attachment_id, $language );

			if ( 0 < $translated_id && $this->write_attachment_translations( $translated_id, $fields, $source_attachment_id ) ) {
				$updated = true;
			}
		}

		return $updated;
	}

	/**
	 * Applies translated metadata to a translated post's featured image.
	 *
	 * @param int   $source_post_id Source post ID.
	 * @param int   $target_post_id Target post ID.
	 * @param array $translations   Translated attachment strings.
	 * @return bool Whether attachment metadata was updated.
	 */
	public function apply_featured_image_translations( $source_post_id, $target_post_id, $translations ) {
		$source_post_id = absint( $source_post_id );
		$target_post_id = absint( $target_post_id );

		if (
			0 === $source_post_id
			|| 0 === $target_post_id
			|| ! is_array( $translations )
			|| empty( $translations )
			|| ! $this->is_enabled()
		) {
			return false;
		}

		$source_attachment_id = (int) get_post_thumbnail_id( $source_post_id );
		$language             = $this->model->post->get_language( $target_post_id );

		if ( 0 === $source_attachment_id || empty( $language ) ) {
			return false;
		}

		$translated_id = $this->resolve_translated_attachment( $source_attachment_id, $language );
		if ( 0 === $translated_id ) {
			return false;
		}

		if ( (int) get_post_thumbnail_id( $target_post_id ) !== $translated_id ) {
			set_post_thumbnail( $target_post_id, $translated_id );
		}

		return $this->write_attachment_translations( $translated_id, $translations, $source_attachment_id );
	}

	/**
	 * Remaps Elementor media controls to translated attachments.
	 *
	 * @param array                                          $elements     Elementor elements tree.
	 * @param \Linguator\Includes\Other\Linguator_Language|string $language     Target language.
	 * @param mixed                                          $translations Optional translated strings.
	 * @return array Remapped Elementor elements.
	 */
	public function remap_elementor_media( $elements, $language, $translations = array() ) {
		if ( ! is_array( $elements ) || empty( $elements ) || empty( $language ) || ! $this->is_enabled() ) {
			return is_array( $elements ) ? $elements : array();
		}

		$translations = $this->normalize_translations( $translations );
		$this->walk_remap_elementor_media( $elements, $language, $translations );

		return $elements;
	}

	/**
	 * Gets strings for a list of attachment IDs.
	 *
	 * @param int[] $attachment_ids Attachment IDs.
	 * @param int   $excluded_id    Attachment ID to omit.
	 * @return array<int, array{id: int, title?: string, alt?: string, caption?: string, description?: string}>
	 */
	private function get_media_strings( $attachment_ids, $excluded_id = 0 ) {
		$strings = array();

		foreach ( array_unique( array_map( 'absint', $attachment_ids ) ) as $attachment_id ) {
			if ( 0 === $attachment_id || $attachment_id === $excluded_id ) {
				continue;
			}

			$attachment_strings = $this->get_attachment_strings( $attachment_id );
			if ( ! empty( $attachment_strings ) ) {
				$strings[] = $attachment_strings;
			}
		}

		return $strings;
	}

	/**
	 * Adds valid IDs to an ID-keyed collection.
	 *
	 * @param array $attachment_ids Candidate attachment IDs.
	 * @param array $ids            Collected IDs.
	 * @return void
	 */
	private function add_attachment_ids( $attachment_ids, array &$ids ) {
		foreach ( $attachment_ids as $attachment_id ) {
			$attachment_id = absint( $attachment_id );
			if ( 0 < $attachment_id ) {
				$ids[ $attachment_id ] = true;
			}
		}
	}

	/**
	 * Recursively collects attachment IDs from parsed blocks.
	 *
	 * @param array $blocks Parsed blocks.
	 * @param array $ids    Collected IDs.
	 * @return void
	 */
	private function collect_block_attachment_ids( $blocks, array &$ids ) {
		if ( ! is_array( $blocks ) ) {
			return;
		}

		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			$attributes = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();
			$block_name = isset( $block['blockName'] ) ? (string) $block['blockName'] : '';

			switch ( $block_name ) {
				case 'core/image':
				case 'core/cover':
				case 'core/audio':
				case 'core/video':
				case 'core/file':
					if ( isset( $attributes['id'] ) ) {
						$this->add_attachment_ids( array( $attributes['id'] ), $ids );
					}
					break;

				case 'core/gallery':
					if ( isset( $attributes['ids'] ) && is_array( $attributes['ids'] ) ) {
						$this->add_attachment_ids( $attributes['ids'], $ids );
					}
					break;

				case 'core/media-text':
					if ( isset( $attributes['mediaId'] ) ) {
						$this->add_attachment_ids( array( $attributes['mediaId'] ), $ids );
					}
					break;
			}

			if (
				isset( $block['innerHTML'] )
				&& is_string( $block['innerHTML'] )
				&& preg_match_all( '/wp-image-(\d+)/', $block['innerHTML'], $matches )
			) {
				$this->add_attachment_ids( $matches[1], $ids );
			}

			if ( isset( $block['innerBlocks'] ) ) {
				$this->collect_block_attachment_ids( $block['innerBlocks'], $ids );
			}
		}
	}

	/**
	 * Recursively collects Elementor media-control attachment IDs.
	 *
	 * @param mixed $data Elementor tree node.
	 * @param array $ids  Collected IDs.
	 * @return void
	 */
	private function walk_elementor_attachment_ids( $data, array &$ids ) {
		if ( ! is_array( $data ) ) {
			return;
		}

		if (
			isset( $data['id'], $data['url'] )
			&& is_numeric( $data['id'] )
			&& is_string( $data['url'] )
			&& '' !== $data['url']
		) {
			$attachment_id = absint( $data['id'] );
			if ( 0 < $attachment_id ) {
				$ids[ $attachment_id ] = $attachment_id;
			}
		}

		foreach ( $data as $value ) {
			if ( is_array( $value ) ) {
				$this->walk_elementor_attachment_ids( $value, $ids );
			}
		}
	}

	/**
	 * Recursively remaps Elementor media-control attachment IDs and URLs.
	 *
	 * @param array                                          $data         Elementor tree node.
	 * @param \Linguator\Includes\Other\Linguator_Language|string $language     Target language.
	 * @param array                                          $translations Translations keyed by source attachment ID.
	 * @return void
	 */
	private function walk_remap_elementor_media( array &$data, $language, array $translations ) {
		if (
			isset( $data['id'], $data['url'] )
			&& is_numeric( $data['id'] )
			&& is_string( $data['url'] )
			&& '' !== $data['url']
		) {
			$source_attachment_id = absint( $data['id'] );
			$translated_id        = $this->resolve_translated_attachment( $source_attachment_id, $language );

			if ( 0 < $translated_id ) {
				$data['id'] = $translated_id;
				$url        = wp_get_attachment_url( $translated_id );

				if ( is_string( $url ) && '' !== $url ) {
					$data['url'] = $url;
				}

				if ( isset( $translations[ $source_attachment_id ] ) ) {
					$this->write_attachment_translations(
						$translated_id,
						$translations[ $source_attachment_id ],
						$source_attachment_id
					);
				}
			}
		}

		foreach ( $data as &$value ) {
			if ( is_array( $value ) ) {
				$this->walk_remap_elementor_media( $value, $language, $translations );
			}
		}
		unset( $value );
	}
}
