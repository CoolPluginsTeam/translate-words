<?php
/**
 * @package Linguator
 */

namespace Linguator\Modules\Blocks;

use Linguator\Includes\Switcher\Assets;
use Linguator\Includes\Switcher\Settings\Settings;
use Linguator\Includes\Switcher\Switcher;

/**
 * Language switcher block.
 */
class Linguator_Language_Switcher_Block extends Linguator_Abstract_Language_Switcher_Block {
	public const EDITOR_STYLE_HANDLE = 'lmat-language-switcher-editor';

	/**
	 * Adds the required hooks.
	 *
	 * @return self
	 */
	public function init() {
		parent::init();

		add_action( 'init', array( $this, 'register_styles' ), 5 );
		add_action( 'init', array( Assets::class, 'register_scripts' ), 5 );

		return $this;
	}

	/**
	 * Registers the frontend and editor styles of the block.
	 *
	 * @return void
	 */
	public function register_styles(): void {
		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		Assets::register_styles();
		wp_register_style(
			self::EDITOR_STYLE_HANDLE,
			plugins_url( "admin/assets/css/build/language-switcher-editor{$suffix}.css", LINGUATOR_ROOT_FILE ),
			array( Assets::FRONTEND_ASSET_HANDLE ),
			LINGUATOR_VERSION
		);
	}

	/**
	 * Returns the language switcher block name with the Linguator's namespace.
	 *
	 * @return string The block name.
	 */
	protected function get_block_name() {
		return 'linguator/language-switcher';
	}

	/**
	 * Returns the block attributes.
	 *
	 * @return array
	 */
	protected function get_attributes() {
		return array(
			'layout'                 => array(
				'type'    => 'string',
				'enum'    => array( 'vertical', 'horizontal', 'dropdown', 'select' ),
				'default' => 'vertical',
			),
			'show_labels'            => array(
				'type'    => 'string',
				'enum'    => array( 'names', 'codes', '' ),
				'default' => 'names',
			),
			'show_flags'             => array(
				'type'    => 'boolean',
				'default' => false,
			),
			'force_home'             => array(
				'type'    => 'boolean',
				'default' => false,
			),
			'hide_current'           => array(
				'type'    => 'boolean',
				'default' => false,
			),
			'hide_if_no_translation' => array(
				'type'    => 'boolean',
				'default' => false,
			),
			'flag_aspect_ratio'      => array(
				'type'    => 'string',
				'enum'    => array( '3:2', '1:1' ),
				'default' => '3:2',
			),
			'flag_border_radius'     => array(
				'type'    => 'number',
				'default' => 0,
			),
			'flag_width'             => array(
				'type'    => 'string',
				'default' => '18px',
			),
			'flag_label_spacing'     => array(
				'type'    => 'string',
				'default' => '0.3em',
			),
			'className'              => array(
				'type'    => 'string',
				'default' => '',
			),
			'lmatLang'               => array(
				'type'    => 'string',
				'default' => '',
			),
		);
	}

	/**
	 * Returns the block supports and styles.
	 *
	 * @return array
	 */
	protected function get_block_args() {
		return array(
			'supports'      => array(
				'spacing'    => array(
					'margin'   => true,
					'padding'  => true,
					'blockGap' => true,
				),
				'align'      => true,
				'color'      => array(
					'background' => true,
				),
				'typography' => array(
					'fontSize'   => true,
					'lineHeight' => true,
					'textAlign'  => true,
				),
			),
			'style'         => Assets::FRONTEND_ASSET_HANDLE,
			'editor_style'  => self::EDITOR_STYLE_HANDLE,
			'view_script'   => Assets::FRONTEND_ASSET_HANDLE,
		);
	}

	/**
	 * Converts the attributes of blocks saved before the layout settings, when not migrated in the editor yet.
	 *
	 * @param array $attributes The block attributes.
	 * @return array
	 */
	protected function migrate_legacy_attributes( array $attributes ): array {
		if ( ! array_key_exists( 'dropdown', $attributes ) && ! array_key_exists( 'show_names', $attributes ) ) {
			return $attributes;
		}

		$show_names = ! isset( $attributes['show_names'] ) || ! empty( $attributes['show_names'] );

		$attributes['layout']      = empty( $attributes['dropdown'] ) ? 'vertical' : 'select';
		$attributes['show_labels'] = $show_names ? 'names' : '';
		$attributes['show_flags']  = $show_names ? ! empty( $attributes['show_flags'] ) : true;

		unset( $attributes['dropdown'], $attributes['show_names'] );

		return $attributes;
	}

	/**
	 * Renders the `linguator/language-switcher` block on server.
	 *
	 * @param array     $attributes The block attributes.
	 * @param string    $content The saved content. Unused.
	 * @param \WP_Block $block The parsed block. Unused.
	 * @return string Returns the language switcher.
	 */
	public function render( $attributes, $content, $block ) { //phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		static $block_id = 0;
		++$block_id;

		$attributes = $this->migrate_legacy_attributes( (array) $attributes );
		$attributes = $this->set_attributes_for_block( $attributes );

		$attributes['show_wrapper'] = true;
		$attributes['unique_id']    = 'select' === $attributes['layout'] ? 'lang_choice_' . $block_id : 'lmat-switcher-block-' . $block_id;

		if ( ! empty( $attributes['style']['typography']['textAlign'] ) ) {
			$attributes['alignment'] = $attributes['style']['typography']['textAlign'];
		}

		if ( empty( $this->links ) ) {
			return '';
		}

		$settings        = new Settings( $attributes );
		$switcher_output = ( new Switcher( $settings, $this->links ) )->get();

		if ( empty( $switcher_output ) ) {
			return '';
		}

		Assets::enqueue_frontend_styles();

		$switcher_output = $this->apply_flag_styles_to_markup( $switcher_output, $attributes );

		return $this->apply_block_wrapper_attributes( $switcher_output, get_block_wrapper_attributes() );
	}

	/**
	 * Merges block wrapper attributes onto the switcher root element.
	 *
	 * @param string $html               Switcher HTML.
	 * @param string $wrapper_attributes Attributes from `get_block_wrapper_attributes()`.
	 * @return string
	 */
	private function apply_block_wrapper_attributes( string $html, string $wrapper_attributes ): string {
		if ( '' === trim( $wrapper_attributes ) ) {
			return $html;
		}

		if ( ! class_exists( 'WP_HTML_Tag_Processor' ) ) {
			return sprintf( '<div %1$s>%2$s</div>', $wrapper_attributes, $html );
		}

		$processor = new \WP_HTML_Tag_Processor( $html );

		if ( ! $processor->next_tag() ) {
			return sprintf( '<div %1$s>%2$s</div>', $wrapper_attributes, $html );
		}

		foreach ( $this->parse_html_attributes( $wrapper_attributes ) as $name => $value ) {
			if ( 'class' === $name ) {
				$existing = $processor->get_attribute( 'class' ) ?? '';
				$value    = trim( $existing . ' ' . $value );
			} elseif ( 'style' === $name ) {
				$existing = $processor->get_attribute( 'style' ) ?? '';
				$value    = '' === $existing ? $value : rtrim( $existing, ';' ) . ';' . $value;
			}

			$processor->set_attribute( $name, $value );
		}

		return $processor->get_updated_html();
	}

	/**
	 * Parses an HTML attributes string.
	 *
	 * @param string $attributes_string HTML attributes.
	 * @return array
	 */
	private function parse_html_attributes( string $attributes_string ): array {
		$attributes = array();
		$processor  = new \WP_HTML_Tag_Processor( '<div ' . $attributes_string . '></div>' );

		if ( ! $processor->next_tag() ) {
			return $attributes;
		}

		foreach ( array( 'class', 'style', 'id' ) as $name ) {
			$value = $processor->get_attribute( $name );

			if ( null !== $value && '' !== $value ) {
				$attributes[ $name ] = $value;
			}
		}

		return $attributes;
	}
}
