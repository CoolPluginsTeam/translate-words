<?php
namespace Linguator\Supported_Blocks;

use Linguator\Settings\Header\Header;
use WP_Block_Type_Registry;

/**
 * Do not access the page directly
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Supported_Blocks' ) ) {
	/**
	 * Class Supported_Blocks
	 *
	 * This class handles the supported blocks for the Linguator plugin.
	 *
	 * @package Linguator
	 */
	class Supported_Blocks {
		/**
		 * Singleton instance.
		 *
		 * @var Supported_Blocks
		 */
		private static $instance = null;


		/**
		 * Stores custom block data for processing and retrieval.
		 *
		 * @var array
		 */
		private $custom_block_data_array = array();

		/**
		 * Linguator plugin category.
		 *
		 * @var array
		 */
		private $linguator_plugin_category = array();

		/**
		 * Get the singleton instance of the class.
		 *
		 * @return Supported_Blocks
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
		 * @return void
		 */
		public function __construct(){
			add_filter('lmat_frontend_settings_assets', array($this, 'stop_frontend_setting_assets'), 10, 3);
			add_filter('lmat_admin_settings_assets', array($this, 'linguator_supported_blocks_assets'), 10, 3);
			add_filter('lmat_render_languages_page', array($this, 'linguator_render_supported_blocks_page'), 10, 3);
		}

		/*
		Filter to enqueue the admin supported blocks assets
		@param bool $status
		@param string $tab
		@param bool $is_settings_tab
		@return bool
		*/
		public function linguator_supported_blocks_assets($status, $tab, $is_settings_tab){
			if($is_settings_tab && $tab === 'supported-blocks' && function_exists('LMAT')){

				$header = Header::get_instance('supported-blocks', LMAT()->model);
				$header->header_assets();

				linguator_enqueue_datatable_assets();
				
				return false;
			}

			return $status;
		}

		/*
		Filter to stop the admin assets on frontend settings page
		@param bool $status
		@param string $tab
		@param bool $is_settings_tab
		@return bool
		*/
		public function stop_frontend_setting_assets($status, $tab, $is_settings_tab){
			if($is_settings_tab && $tab === 'supported-blocks'){
				return false;
			}

			return $status;
		}

		/*
		Filter to render the supported blocks page
		@param bool $status
		@param string $selected_tab
		@param string $active_tab
		@return bool
		*/
		public function linguator_render_supported_blocks_page($status, $selected_tab, $active_tab) {
			if($selected_tab === 'supported-blocks' && $active_tab === 'settings'){

				$header = Header::get_instance('supported-blocks', LMAT()->model);

				$header->header();

				$this->render_supported_blocks_page();
				return false;
			}

			return $status;
		}

		/**
		 * Render the support blocks page.
		 */
		public function render_supported_blocks_page() {
			?>
		<div class="lmat-custom-data-table-wrapper">
			<h3><?php echo esc_html__('Supported Blocks Translation Settings', 'translate-words'); ?>
			<br><p>
			<?php 
				// translators: %s: Linguator.
				printf( esc_html__( 'Manage Gutenberg blocks to make them translation-ready with %s.', 'translate-words' ), 'Linguator' ); 
			?></p>
			</h3>
			<div class="lmat-custom-data-table-filters">
				<div class="lmat-filter-tab" data-column="1" data-default="all">
					<label for="lmat-blocks-category"><?php esc_html_e( 'Block Type Category:', 'translate-words' ); ?></label>
					<select id="lmat-blocks-category" name="lmat_blocks_category">
						<option value="all"><?php esc_html_e( 'All', 'translate-words' ); ?></option>
						<option value="core">Core</option>
						<?php $this->linguator_get_blocks_category(); ?>
					</select>
				</div>
				<div class="lmat-filter-tab" data-column="3" data-default="all">
					<label for="lmat-blocks-filter"><?php esc_html_e( 'Show Blocks:', 'translate-words' ); ?></label>
					<select id="lmat-blocks-filter" name="lmat_blocks_filter">
						<option value="all"><?php esc_html_e( 'All', 'translate-words' ); ?></option>
						<option value="supported"><?php esc_html_e( 'Supported Blocks', 'translate-words' ); ?></option>
						<option value="unsupported"><?php esc_html_e( 'Unsupported Blocks', 'translate-words' ); ?></option>
					</select>
				</div>
			</div>
			<div class="lmat-custom-table-section">
				<div class="lmat-custom-table-lists">
					<table class="lmat-custom-data-table-table" id="lmat-custom-datatable">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Sr.No', 'translate-words' ); ?></th>
								<th><?php esc_html_e( 'Block Name', 'translate-words' ); ?></th>
								<th><?php esc_html_e( 'Block Title', 'translate-words' ); ?></th>
								<th><?php esc_html_e( 'Status', 'translate-words' ); ?></th>
								<th><?php esc_html_e( 'Modify', 'translate-words' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php 
								$this->linguator_get_supported_blocks_table()
							?>
						</tbody>
					</table>
				</div>
			</div>
		</div>
			<?php
		}

		/**
		 * Get the blocks category.
		 */
		public function linguator_get_blocks_category() {
			$blocks_data                 = WP_Block_Type_Registry::get_instance()->get_all_registered();

			$filter_blocks_data = array_filter( $blocks_data, function( $block ) {
				return !in_array($block->category, array( 'media', 'reusable' ));
			} );
			foreach ( $filter_blocks_data as $block ) {
				$plugin_name = explode('/', $block->name);
				$plugin_name = isset($plugin_name[0]) ? $plugin_name[0] : '';

				if(!empty($plugin_name)){
					$filter_plugin_name = $this->linguator_supported_block_name($plugin_name);
					$filter_plugin_name=str_replace('-',' ',$filter_plugin_name);
					$filter_plugin_name=ucwords($filter_plugin_name);

					if(in_array($plugin_name, $this->linguator_plugin_category) || $plugin_name === 'core'){
						continue;
					}

					$this->linguator_plugin_category[] = $plugin_name;
					echo '<option value="' . esc_attr( $plugin_name ) . '">' . esc_html( $filter_plugin_name ) . '</option>';
				}
			}
		}

		/**
		 * Get the supported blocks.
		 */
		public function linguator_get_supported_blocks_table() {
			if ( class_exists( WP_Block_Type_Registry::class ) && method_exists( WP_Block_Type_Registry::class, 'get_all_registered' ) ) {
				$linguator_block_parse_rules       = $this->block_parsing_rules();

				$blocks_data                 = WP_Block_Type_Registry::get_instance()->get_all_registered();

				$linguator_supported_blocks       = isset($linguator_block_parse_rules['LmatBlockParseRules']) ? $linguator_block_parse_rules['LmatBlockParseRules'] : array();
				$linguator_supported_blocks_names = array_keys( $linguator_supported_blocks );
				$s_no                        = 1;
				$linguator_post_id                = self::linguator_get_custom_block_post_id();

				$filter_blocks_data=$blocks_data;

				foreach ( $filter_blocks_data as $block ) {

					$block_name  = esc_html( $block->name );
					$block_title = esc_html( $block->title );

					$status      = ! in_array( $block_name, $linguator_supported_blocks_names ) ? 'Unsupported' : 'Supported'; // You can modify this logic based on your requirements
					$modify_text = ! in_array( $block_name, $linguator_supported_blocks_names ) ? esc_html__( 'Add', 'translate-words' ) : esc_html__( 'Edit', 'translate-words' );
					$modify_link = '<a href="' . esc_url( admin_url( 'post.php?post=' . esc_attr( $linguator_post_id ) . '&action=edit&lmat_new_block=' ) . esc_attr( $block_name ) ) . '">' . $modify_text . '</a>'; // Modify link

					echo '<tr data-block-name="' . esc_attr( strtolower( $block_name ) ) . '" data-block-status="' . esc_attr( strtolower( $status ) ) . '" >';
					echo '<td>' . esc_html($s_no++) . '</td>';
					echo '<td>' . esc_html($block_name) . '</td>';
					echo '<td>' . esc_html($block_title) . '</td>';
					echo '<td>' . esc_html($status) . '</td>';
					echo '<td>' . wp_kses($modify_link, array('a' => array('href' => array(), 'target' => array(), 'rel' => array()))) . '</td>';
					echo '</tr>';
				}
			}

		}

		private function linguator_supported_block_name($block_name){
			$predfined_blocks = array(
				'ub' => 'Ultimate Blocks',
				'uagb' => 'Spectra',
				'themeisle-blocks' => 'Otter Blocks'
			);
			
			if(array_key_exists($block_name, $predfined_blocks)){
				return $predfined_blocks[$block_name];
			}

			return $block_name;
		}

		private static function linguator_get_custom_block_post_id()
		{
			$first_post_id = null;

			$query = new \WP_Query(
				array(
					'post_type'      => 'lmat_add_blocks',
					'posts_per_page' => 1,
					'orderby'        => 'date',
					'order'          => 'ASC',
				)
			);

			$existing_post = $query->posts ? $query->posts[0] : null;

			if (! $existing_post) {
				$post_title    = esc_html__('Add More Gutenberg Blocks', 'translate-words');
				$first_post_id = wp_insert_post(
					array(
						'post_title'   => $post_title,
						'post_content' => '',
						'post_status'  => 'publish',
						'post_type'    => 'lmat_add_blocks',
					)
				);
			} elseif ($query->have_posts()) {
				$query->the_post();
				$first_post_id = get_the_ID();
			}

			return $first_post_id;
		}

		/**
		 * Block Parsing Rules
		 *
		 * Handles the block parsing rules AJAX request.
		 */
		public function block_parsing_rules() {
			$this->custom_block_data_array = array();
			$block_parse_rules = $this->linguator_get_block_parse_rules();
			
			return $block_parse_rules;
		}

		/**
		 * Builds the block translation rules from the bundled wpml-config.xml and merges saved custom block rules on top.
		 *
		 * @return array Rules keyed as ['LmatBlockParseRules' => [ block_name => [ 'attributes' => [...] ] ] ], or an empty array when the config cannot be read.
		 */
		public function linguator_get_block_parse_rules() {
			$config_path = trailingslashit( plugin_dir_path( LINGUATOR_ROOT_FILE ) ) . 'modules/page-translation/block-translation-rules/wpml-config.xml';
			$xml_rules   = $this->linguator_parse_single_wpml_config( $config_path );

			if ( empty( $xml_rules ) ) {
				return array();
			}

			$block_translation_rules = array(
				'LmatBlockParseRules' => $this->linguator_prepare_xml_block_rules( $xml_rules ),
			);

			$this->custom_block_data_array = $block_translation_rules['LmatBlockParseRules'];

			$custom_block_translation = get_option( 'lmat_custom_block_translation', false );

			if ( ! empty( $custom_block_translation ) && is_array( $custom_block_translation ) ) {
				foreach ( $custom_block_translation as $key => $block_data ) {
					$block_rules = isset( $block_translation_rules['LmatBlockParseRules'][ $key ] ) ? $block_translation_rules['LmatBlockParseRules'][ $key ] : null;
					$this->linguator_filter_custom_block_rules( array( $key ), $block_data, $block_rules );
				}
			}

			$block_translation_rules['LmatBlockParseRules'] = $this->custom_block_data_array ? $this->custom_block_data_array : array();

			return $block_translation_rules;
		}

		/**
		 * Converts parsed XML block rules into the attribute rule format used by the translation scripts.
		 *
		 * Drops XPath-only rules, regex-style keys and url keys, and skips blocks left without translatable attributes.
		 *
		 * @param array $xml_rules Block rules keyed by block name.
		 * @return array Rules keyed by block name, each wrapped in an 'attributes' entry.
		 */
		private function linguator_prepare_xml_block_rules( array $xml_rules ) {
			$prepared = array();

			foreach ( $xml_rules as $block_name => $rules ) {
				if ( ! is_array( $rules ) ) {
					continue;
				}

				unset( $rules['xpaths'] );
				$attributes = $this->linguator_clean_xml_attribute_rules( $rules );

				if ( ! empty( $attributes ) ) {
					$prepared[ $block_name ] = array( 'attributes' => $attributes );
				}
			}

			return $prepared;
		}

		/**
		 * Recursively removes regex-style keys and url keys from attribute rules, pruning branches left empty.
		 *
		 * @param array $rules Attribute rules for a block or a nested attribute.
		 * @return array Cleaned attribute rules.
		 */
		private function linguator_clean_xml_attribute_rules( array $rules ) {
			$cleaned = array();

			foreach ( $rules as $key => $value ) {
				if ( is_string( $key ) && ( 'url' === strtolower( $key ) || ( strlen( $key ) > 1 && '/' === $key[0] && '/' === substr( $key, -1 ) ) ) ) {
					continue;
				}

				if ( is_array( $value ) ) {
					$value = $this->linguator_clean_xml_attribute_rules( $value );
					if ( empty( $value ) ) {
						continue;
					}
				} elseif ( true !== $value ) {
					continue;
				}

				$cleaned[ $key ] = $value;
			}

			return $cleaned;
		}

		private function linguator_filter_custom_block_rules( array $id_keys, $value, $block_rules, $attr_key = false ) {
			$block_rules = is_object( $block_rules ) ? json_decode( json_encode( $block_rules ) ) : $block_rules;

			if ( ! isset( $block_rules ) ) {
				return $this->linguator_merge_nested_attribute( $id_keys, $value );
			}
			if ( is_object( $value ) && isset( $block_rules ) ) {
				foreach ( $value as $key => $item ) {
					if ( isset( $block_rules[ $key ] ) && is_object( $item ) ) {
						$this->linguator_filter_custom_block_rules( array_merge( $id_keys, array( $key ) ), $item, $block_rules[ $key ], false );
						continue;
					} elseif ( ! isset( $block_rules[ $key ] ) && true === $item ) {
						$this->linguator_merge_nested_attribute( array_merge( $id_keys, array( $key ) ), true );
						continue;
					} elseif ( ! isset( $block_rules[ $key ] ) && is_object( $item ) ) {
						$this->linguator_merge_nested_attribute( array_merge( $id_keys, array( $key ) ), $item );
						continue;
					}
				}
			}
		}

		private function linguator_merge_nested_attribute( array $id_keys, $value ) {
			$value = is_object( $value ) ? json_decode( json_encode( $value ), true ) : $value;

			$current_array = &$this->custom_block_data_array;

			foreach ( $id_keys as $index => $id ) {
				if ( ! isset( $current_array[ $id ] ) ) {
					$current_array[ $id ] = array();
				}
				$current_array = &$current_array[ $id ];
			}

			$current_array = $value;
		}

		/**
		 * Parses the <gutenberg-blocks> section of a WPML-format config file into block rules keyed by block name.
		 *
		 * @param string $file_path Absolute path to the wpml-config.xml file.
		 * @return array Extracted block translation rules.
		 */
		private function linguator_parse_single_wpml_config( $file_path ) {
			$rules = array();

			if ( ! function_exists( 'simplexml_load_string' ) ) {
				return $rules;
			}

			if ( ! function_exists( 'WP_Filesystem' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}

			global $wp_filesystem;
			WP_Filesystem();

			$xml_contents = $wp_filesystem && $wp_filesystem->is_readable( $file_path ) ? $wp_filesystem->get_contents( $file_path ) : false;

			if ( false === $xml_contents || '' === trim( $xml_contents ) ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Genuine failure diagnostic, mirrors the parse-failure log below.
				error_log( sprintf( 'Linguator Error: Failed to read XML file at %s', $file_path ) );
				return $rules;
			}

			// Prevent XXE attacks by disabling external entities (deprecated and unnecessary on PHP 8+).
			$old_disable_entity_loader = null;
			if ( \PHP_VERSION_ID < 80000 && function_exists( 'libxml_disable_entity_loader' ) ) {
				$old_disable_entity_loader = libxml_disable_entity_loader( true ); // phpcs:ignore Generic.PHP.DeprecatedFunctions.Deprecated -- Only called on PHP < 8.
			}

			// Collect libxml errors internally for malformed XML and prevent network entity loading.
			$old_internal_errors = libxml_use_internal_errors( true );
			$xml                 = simplexml_load_string( $xml_contents, 'SimpleXMLElement', LIBXML_NONET );
			libxml_clear_errors();
			libxml_use_internal_errors( $old_internal_errors );

			if ( null !== $old_disable_entity_loader && function_exists( 'libxml_disable_entity_loader' ) ) {
				libxml_disable_entity_loader( $old_disable_entity_loader ); // phpcs:ignore Generic.PHP.DeprecatedFunctions.Deprecated -- Only called on PHP < 8.
			}

			if ( ! $xml ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Genuine failure diagnostic for malformed XML.
				error_log( sprintf( 'Linguator Error: Failed to parse XML file at %s', $file_path ) );
				return $rules;
			}

			if ( ! isset( $xml->{'gutenberg-blocks'} ) ) {
				return $rules;
			}

			if ( isset( $xml->{'gutenberg-blocks'}->{'gutenberg-block'} ) ) {
				foreach ( $xml->{'gutenberg-blocks'}->{'gutenberg-block'} as $block ) {
					$block_type = (string) $block['type'];
					if ( empty( $block_type ) ) {
						continue;
					}

					$translate = (string) $block['translate'];
					if ( '0' === $translate ) {
						continue;
					}

					$attributes           = $this->linguator_parse_wpml_keys( $block );
					$rules[ $block_type ] = ! empty( $attributes ) ? $attributes : array();

					if ( isset( $block->xpath ) ) {
						$xpaths = array();
						foreach ( $block->xpath as $xpath_node ) {
							$xpaths[] = (string) $xpath_node;
						}
						if ( ! empty( $xpaths ) ) {
							$rules[ $block_type ]['xpaths'] = $xpaths;
						}
					}
				}
			}

			return $rules;
		}

		/**
		 * Recursively converts <key> nodes into a nested rules array; a key named * becomes a list rule.
		 *
		 * @param \SimpleXMLElement $xml_node The XML node containing key elements.
		 * @return array Extracted attributes and their translation settings.
		 */
		private function linguator_parse_wpml_keys( $xml_node ) {
			$attributes = array();
			if ( isset( $xml_node->key ) ) {
				foreach ( $xml_node->key as $key_node ) {
					$key_name = (string) $key_node['name'];
					if ( empty( $key_name ) ) {
						continue;
					}

					if ( isset( $key_node->key ) && count( $key_node->key ) > 0 ) {
						$nested = $this->linguator_parse_wpml_keys( $key_node );
						if ( '*' === $key_name ) {
							return array( $nested );
						}
						$attributes[ $key_name ] = $nested;
					} else {
						if ( '*' === $key_name ) {
							return array( true );
						}
						$attributes[ $key_name ] = true;
					}
				}
			}
			return $attributes;
		}

		/**
		 * Update the custom blocks content.
		 *
		 * @param array $updated_blocks_data The updated blocks data.
		 */
		public function update_custom_blocks_content($updated_blocks_data){

			$this->custom_block_data_array = array();

			if ( $updated_blocks_data ) {
				$block_parse_rules = $this->block_parsing_rules();

				if ( isset( $block_parse_rules['LmatBlockParseRules'] ) ) {
					$previous_translate_data = get_option( 'lmat_custom_block_translation', false );
					if ( $previous_translate_data && ! empty( $previous_translate_data ) ) {
						$this->custom_block_data_array = $previous_translate_data;
					}

					foreach ( $updated_blocks_data as $key => $block_data ) {
						$this->linguator_filter_custom_block_rules( array( $key ), $block_data, $block_parse_rules['LmatBlockParseRules'][ $key ] );
					}

					if ( count( $this->custom_block_data_array ) > 0 ) {
						update_option( 'lmat_custom_block_translation', $this->custom_block_data_array );
					}

					delete_option( 'lmat_custom_block_data' );
				}
			}
		}
	}
}
