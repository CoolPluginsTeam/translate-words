<?php
namespace Linguator\Modules\Page_Translation;

/**
 * LMAT Page Translation Ajax Handler
 *
 * @package Linguator
 */

/**
 * Do not access the page directly
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Linguator\Includes\Other\Linguator_Translation_Dashboard;
use Linguator\Custom_Fields\Custom_Fields;

/**
 * Handle LMAT Page Translation ajax requests
 */
if ( ! class_exists( 'Linguator_Page_Translation_Helper' ) ) {
	class Linguator_Page_Translation_Helper {
		/**
		 * Member Variable
		 *
		 * @var instance
		 */
		private static $instance;
		/**
		 * Stores custom block data for processing and retrieval.
		 *
		 * This static array holds the data related to custom blocks that are
		 * used within the plugin. It can be utilized to manage and manipulate
		 * the custom block information as needed during AJAX requests.
		 *
		 * @var array
		 */
		private $custom_block_data_array = array();

		/**
		 * Gets an instance of our plugin.
		 *
		 * @param object $settings_obj timeline settings.
		 */
		public static function get_instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Constructor.
		 *
		 * @param object $settings_obj Plugin settings.
		 */
		public function __construct() {
			if ( is_admin() ) {
				add_action( 'wp_ajax_lmat_update_translate_data', array( $this, 'linguator_update_translate_data' ) );
				add_action( 'wp_ajax_lmat_update_translated_slug', array( $this, 'update_translated_slug' ) );
			}
		}

		/**
		 * Persist the translated original slug directly on the translated post.
		 *
		 * Editor state changes alone are not visible to Quick Edit until the post
		 * is saved. This endpoint writes only post_name and avoids triggering the
		 * multilingual reverse-sync hooks used by a full wp_update_post() call.
		 *
		 * @return void
		 */
		public function update_translated_slug(): void {
			if ( ! check_ajax_referer( 'lmat_page_translation_admin', 'lmat_page_translation_nonce', false ) ) {
				wp_send_json_error( __( 'Invalid security token sent.', 'translate-words' ), 403 );
			}

			$post_id         = isset( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;
			$translated_slug = isset( $_POST['post_name'] ) ? sanitize_title( wp_unslash( $_POST['post_name'] ) ) : '';

			if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
				wp_send_json_error( __( 'You are not authorized to edit this post.', 'translate-words' ), 403 );
			}

			$slug_translation_option = 'title_translate';
			if ( property_exists( LMAT(), 'options' ) && isset( LMAT()->options['ai_translation_configuration']['slug_translation_option'] ) ) {
				$slug_translation_option = LMAT()->options['ai_translation_configuration']['slug_translation_option'];
			}
			if ( 'slug_translate' !== $slug_translation_option ) {
				wp_send_json_error( __( 'Original slug translation is not enabled.', 'translate-words' ), 400 );
			}
			if ( '' === $translated_slug ) {
				wp_send_json_error( __( 'The translated slug is missing.', 'translate-words' ), 422 );
			}

			$post = get_post( $post_id );
			if ( ! $post instanceof \WP_Post ) {
				wp_send_json_error( __( 'The translated post could not be found.', 'translate-words' ), 404 );
			}

			$translated_slug = wp_unique_post_slug(
				$translated_slug,
				$post_id,
				$post->post_status,
				$post->post_type,
				$post->post_parent
			);

			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$updated = $wpdb->update(
				$wpdb->posts,
				array( 'post_name' => $translated_slug ),
				array( 'ID' => $post_id ),
				array( '%s' ),
				array( '%d' )
			);

			if ( false === $updated ) {
				wp_send_json_error( __( 'The translated slug could not be saved.', 'translate-words' ), 500 );
			}

			clean_post_cache( $post_id );
			wp_send_json_success( array( 'post_name' => get_post_field( 'post_name', $post_id ) ) );
		}

		/**
		 * Fetches post meta fields via AJAX request.
		 */
		public function fetch_post_meta_fields() {
			if ( ! check_ajax_referer( 'lmat_fetch_post_meta_fields', 'meta_fields_key', false ) ) {
				wp_send_json_error( __( 'Invalid security token sent.', 'translate-words' ) );
				wp_die( '0', 400 );
			}

			$post_id = isset( $_POST['postId']) ? absint(sanitize_text_field(wp_unslash($_POST['postId']))) : false;

			if(!isset($post_id) || false === $post_id){
				wp_send_json_error( __( 'Invalid Post ID.', 'translate-words' ) );
				wp_die( '0', 400 );
			}

			if(!current_user_can('edit_post', $post_id)){
				wp_send_json_error( __( 'Unauthorized', 'translate-words' ), 403 );
				wp_die( '0', 403 );
			}

			$post_meta_sync = true;

            if (!isset(LMAT()->options['sync']) || (isset(LMAT()->options['sync']) && !in_array('post_meta', LMAT()->options['sync']))) {
                $post_meta_sync = false;
            }

			if($post_meta_sync){
				wp_send_json_success( __( 'Post meta sync is enabled. Please disable post meta sync in Linguator settings.', 'translate-words' ) );
			}

			$allowed_meta_fields=Custom_Fields::get_allowed_custom_fields();
			$post_meta_fields=get_post_meta($post_id);

			$existed_meta_fields=array_intersect(array_keys($post_meta_fields), array_keys($allowed_meta_fields));
			$filtered_meta_fields=array();

			foreach($existed_meta_fields as $key){
				if(isset($post_meta_fields[$key]) && !empty($post_meta_fields[$key]) && isset($allowed_meta_fields[$key]['status']) && true === $allowed_meta_fields[$key]['status']){
					$value=$allowed_meta_fields[$key]['type'] && is_array($post_meta_fields[$key]) ? maybe_unserialize($post_meta_fields[$key][0]) : maybe_unserialize($post_meta_fields[$key]);
					$filtered_meta_fields[$key]=$value;
				}
			}

			wp_send_json_success( array( 'metaFields' => $filtered_meta_fields, 'allowedMetaFields' => $allowed_meta_fields ) );
			exit;
		}

		/**
		 * Fetches post content via AJAX request.
		 */
		public function fetch_post_content() {
			if ( ! check_ajax_referer( 'lmat_page_translation_admin', 'lmat_page_translation_nonce', false ) ) {
				wp_send_json_error( __( 'Invalid security token sent.', 'translate-words' ) );
				wp_die( '0', 400 );
			}

			$post_id = absint( isset( $_POST['postId'] ) ? absint( sanitize_text_field( wp_unslash( $_POST['postId'] ) ) ) : false );

			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				wp_send_json_error( __( 'Unauthorized', 'translate-words' ), 403 );
				wp_die( '0', 403 );
			}

			if ( false !== $post_id ) {
				$post_data               = get_post( absint( $post_id ) );
			$locale                  = isset( $_POST['local'] ) ? sanitize_text_field( wp_unslash( $_POST['local'] ) ) : 'en';
			$current_locale          = isset( $_POST['current_local'] ) ? sanitize_text_field( wp_unslash( $_POST['current_local'] ) ) : 'en';

				$slug_translation_option = 'title_translate';

				if(property_exists(LMAT(), 'options') && isset(LMAT()->options['ai_translation_configuration']['slug_translation_option'])){
					$slug_translation_option = LMAT()->options['ai_translation_configuration']['slug_translation_option'];
				}

				$content = $post_data->post_content;
				
				/**
				 * Filter the post content for translation.
				 * 
				 * @since 1.0.4
				 * @param string $content Post content.
				 * @param int    $post_id Post ID.
				 */
				$content = apply_filters( 'lmat_post_content_for_translation', $content, $post_id );

				if ( function_exists( 'linguator_replace_links_with_translations' ) ) {
					$content = linguator_replace_links_with_translations( $content, $locale, $current_locale );
				}

				$meta_fields = get_post_meta( $post_id );

				$data = array(
					'title'      => $post_data->post_title,
					'excerpt'    => $post_data->post_excerpt,
					'content'    => $content,
				);

				if ( $slug_translation_option === 'slug_translate' || $slug_translation_option === 'slug_keep' ) {
					$data['slug_name'] = urldecode( get_post_field( 'post_name', $post_id ) );
				}

				return wp_send_json_success( $data );
			} else {
				wp_send_json_error( __( 'Invalid Post ID.', 'translate-words' ) );
				wp_die( '0', 400 );
			}

			exit;
		}

		public function linguator_update_translate_data() {
			if ( ! check_ajax_referer( 'lmat_update_translate_data_nonce', 'update_translation_key', false ) ) {
				wp_send_json_error( __( 'Invalid security token sent.', 'translate-words' ) );
				wp_die( '0', 400 );
			}

		$post_id     = isset( $_POST['post_id'] ) ? absint( sanitize_text_field( wp_unslash( $_POST['post_id'] ) ) ) : 0;
		$editor_type = isset( $_POST['editorType'] ) ? sanitize_text_field( wp_unslash( $_POST['editorType'] ) ) : '';

		$extra_data = array();
		if ( isset( $_POST['extraData'] ) ) {
			$extra_data_raw = wp_unslash( $_POST['extraData'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON is decoded then sanitized key/value before use.
			$decoded        = json_decode( is_string( $extra_data_raw ) ? $extra_data_raw : '', true );
			$extra_data     = is_array( $decoded ) ? $decoded : array();
		}

			// Require capability based on context
			if ( $post_id > 0 ) {
				if ( ! current_user_can( 'edit_post', $post_id ) && $editor_type !== 'taxonomy' ) {
					wp_send_json_error( __( 'Unauthorized to edit post', 'translate-words' ), 403 );
					wp_die( '0', 403 );
				}
				
				if ( $editor_type === 'taxonomy' ) {
					if ( ! current_user_can( 'edit_posts' ) ) {
						wp_send_json_error( __( 'Unauthorized to edit terms', 'translate-words' ), 403 );
						wp_die( '0', 403 );
					}
				}
			} elseif ( ! current_user_can( 'edit_posts' ) ) {
					wp_send_json_error( __( 'Unauthorized', 'translate-words' ), 403 );
					wp_die( '0', 403 );
			}

		$provider            = isset( $_POST['provider'] ) ? sanitize_text_field( wp_unslash( $_POST['provider'] ) ) : '';
		$total_string_count  = isset( $_POST['totalStringCount'] ) ? absint( wp_unslash( $_POST['totalStringCount'] ) ) : 0;
		$total_word_count    = isset( $_POST['totalWordCount'] ) ? absint( wp_unslash( $_POST['totalWordCount'] ) ) : 0;
		$total_char_count    = isset( $_POST['totalCharacterCount'] ) ? absint( wp_unslash( $_POST['totalCharacterCount'] ) ) : 0;
		$date                = isset( $_POST['date'] ) ? gmdate( 'Y-m-d H:i:s', strtotime( sanitize_text_field( wp_unslash( $_POST['date'] ) ) ) ) : '';
		$source_string_count = isset( $_POST['sourceStringCount'] ) ? absint( wp_unslash( $_POST['sourceStringCount'] ) ) : 0;
		$source_word_count   = isset( $_POST['sourceWordCount'] ) ? absint( wp_unslash( $_POST['sourceWordCount'] ) ) : 0;
		$source_char_count   = isset( $_POST['sourceCharacterCount'] ) ? absint( wp_unslash( $_POST['sourceCharacterCount'] ) ) : 0;
		$source_lang         = isset( $_POST['sourceLang'] ) ? sanitize_text_field( wp_unslash( $_POST['sourceLang'] ) ) : '';
		$target_lang         = isset( $_POST['targetLang'] ) ? sanitize_text_field( wp_unslash( $_POST['targetLang'] ) ) : '';
			$time_taken          = isset( $_POST['timeTaken'] ) ? absint( wp_unslash( $_POST['timeTaken'] ) ) : 0;

			if ( class_exists( Linguator_Translation_Dashboard::class ) ) {
				$translation_data = array(
					'post_id'                => $post_id,
					'service_provider'       => $provider,
					'source_language'        => $source_lang,
					'target_language'        => $target_lang,
					'time_taken'             => $time_taken,
					'string_count'           => $total_string_count,
					'word_count'             => $total_word_count,
					'character_count'        => $total_char_count,
					'source_string_count'    => $source_string_count,
					'source_word_count'      => $source_word_count,
					'source_character_count' => $source_char_count,
					'editor_type'            => $editor_type,
					'date_time'              => $date,
					'version_type'           => 'free',
				);

				if ( ! empty( $extra_data ) && is_array( $extra_data ) && count( $extra_data ) > 0 ) {
					foreach ( $extra_data as $key => $value ) {
						if ( ! isset( $translation_data[ $key ] ) && ! empty( $value ) && ! empty( $key ) ) {
							$translation_data[ sanitize_text_field( $key ) ] = sanitize_text_field( $value );
						}
					}
				}

				Linguator_Translation_Dashboard::store_options(
					'lmat',
					'post_id',
					'update',
					$translation_data
				);

				wp_send_json_success(
					array(
						'message' => __( 'Translation data updated successfully', 'translate-words' ),
					)
				);
			} else {
				wp_send_json_error(
					array(
						'message' => __( 'Lmat_Dashboard class not found', 'translate-words' ),
					)
				);
			}
			exit;
		}

		/**
		 * Handle AJAX request to update Elementor data.
		 */
		public function update_elementor_data() {
			if ( ! check_ajax_referer( 'lmat_page_translation_admin', 'lmat_page_translation_nonce', false ) ) {
				wp_send_json_error( __( 'Invalid security token sent.', 'translate-words' ) );
				wp_die( '0', 400 );
			}
			$post_id = isset( $_POST['post_id'] ) ? absint( sanitize_text_field( wp_unslash( $_POST['post_id'] ) ) ) : 0;
			if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
				wp_send_json_error( __( 'Unauthorized', 'translate-words' ), 403 );
				wp_die( '0', 403 );
			}

			$raw_elementor_data = isset( $_POST['elementor_data'] ) && is_string( $_POST['elementor_data'] )
				? wp_unslash( $_POST['elementor_data'] )
				: '';
			$elementor_data = json_decode( $raw_elementor_data, true );

			if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $elementor_data ) ) {
				wp_send_json_error( __( 'Invalid Elementor data.', 'translate-words' ), 400 );
			}

			$parent_post_id          = isset( $_POST['parent_post_id'] ) ? intval( sanitize_text_field( wp_unslash( $_POST['parent_post_id'] ) ) ) : 0;

			$current_slug            = get_post_field( 'post_name', $post_id );
			$new_post_name           = false;
			$translated_slug         = isset( $_POST['post_name'] ) ? sanitize_text_field( wp_unslash( $_POST['post_name'] ) ) : '';
			
			$slug_translation_option = 'title_translate';
			if(property_exists(LMAT(), 'options') && isset(LMAT()->options['ai_translation_configuration']['slug_translation_option'])){
				$slug_translation_option = LMAT()->options['ai_translation_configuration']['slug_translation_option'];
			}

			if ( 'slug_translate' === $slug_translation_option && '' === $translated_slug ) {
				wp_send_json_error( __( 'The translated slug is missing.', 'translate-words' ), 422 );
			}

			if ( 'slug_translate' === $slug_translation_option ) {
				$new_post_name = sanitize_title( $translated_slug );
			} elseif ( '' === $current_slug && 'slug_keep' === $slug_translation_option ) {
				$new_post_name = sanitize_text_field( get_post_field( 'post_name', $parent_post_id ) );
			}

			if ( ! class_exists( 'Elementor\Plugin' ) ) {
				wp_send_json_error( __( 'Elementor is not available.', 'translate-words' ), 500 );
			}

			$plugin   = \Elementor\Plugin::$instance;
			$document = $plugin->documents->get( $post_id );

			if ( ! $document || false === $document->save( array( 'elements' => $elementor_data ) ) ) {
				wp_send_json_error( __( 'Elementor could not save the translated page.', 'translate-words' ), 500 );
			}

			$post_update = array( 'ID' => $post_id );
			if ( $new_post_name && '' !== $new_post_name ) {
				$post_update['post_name'] = $new_post_name;
			}
			if ( isset( $_POST['post_title'] ) ) {
				$post_title = sanitize_text_field( wp_unslash( $_POST['post_title'] ) );
				if ( '' !== $post_title ) {
					$post_update['post_title'] = $post_title;
				}
			}

			// Updating the post also advances post_modified, preventing Elementor from
			// preferring an older source-language autosave over the translated data.
			if ( count( $post_update ) > 1 ) {
				$updated_post_id = wp_update_post( wp_slash( $post_update ), true );
				if ( is_wp_error( $updated_post_id ) ) {
					wp_send_json_error( array( 'message' => $updated_post_id->get_error_message() ), 500 );
				}
			}

			// Keep the current user's Elementor autosave in step with the main draft.
			// Otherwise Elementor can reload the copied English autosave on the next
			// editor or preview request even though the main document was translated.
			$autosave = wp_get_post_autosave( $post_id, get_current_user_id() );
			if ( $autosave instanceof \WP_Post ) {
				$saved_elementor_data = get_post_meta( $post_id, '_elementor_data', true );
				if ( is_string( $saved_elementor_data ) && '' !== $saved_elementor_data ) {
					update_metadata( 'post', $autosave->ID, '_elementor_data', wp_slash( $saved_elementor_data ) );
				}
			}

			update_post_meta( $post_id, '_lmat_elementor_translated', 'true' );
			$plugin->files_manager->clear_cache();
			clean_post_cache( $post_id );

			wp_send_json_success(
				array(
					'message'     => __( 'Elementor data updated.', 'translate-words' ),
					'preview_url' => esc_url_raw( (string) get_preview_post_link( $post_id ) ),
				)
			);
			exit;
		}
	}
}

