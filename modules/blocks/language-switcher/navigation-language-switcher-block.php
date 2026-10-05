<?php

namespace Linguator\Modules\Blocks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @package Linguator
 */

/**
 * Language switcher block for navigation.
 *
 */
class Linguator_Navigation_Language_Switcher_Block extends Linguator_Abstract_Language_Switcher_Block {
	/**
	 * Placeholder used to add language name or flag after WordPress renders the link labels.
	 *
	 * @var string
	 */
	const PLACEHOLDER = '%lmat%';

	/**
	 * Trusted labels and locales for navigation blocks created by this switcher.
	 * Saved blocks are different instances and cannot use these values.
	 *
	 * @var \SplObjectStorage<\WP_Block, array{locale: string, label: string}>
	 */
	private $internal_blocks;

	/**
	 * @param object $linguator Linguator instance.
	 */
	public function __construct( &$linguator ) {
		parent::__construct( $linguator );
		$this->internal_blocks = new \SplObjectStorage();
	}

	/**
	 * Adds the required hooks specific to the navigation language switcher.
	 *
	 *
	 * @return self
	 */
	public function init() {
		parent::init();

		add_action( 'rest_api_init', array( $this, 'linguator_register_switcher_menu_item_options_meta_rest_field' ) );
		add_filter( 'render_block_core/navigation-link', array( $this, 'linguator_render_custom_attributes' ), 10, 3 );
		add_filter( 'render_block_core/navigation-submenu', array( $this, 'linguator_render_custom_attributes' ), 10, 3 );

		return $this;
	}

	/**
	 * Returns the navigation language switcher block name with the Linguator's namespace.
	 *
	 *
	 * @return string The block name.
	 */
	protected function get_block_name() {
		return 'linguator/navigation-language-switcher';
	}

	/**
	 * Returns the supported pieces of context for the 'linguator/navigation-language-switcher' block.
	 * This context will be inherited from the 'core/navigation' block.
	 *
	 *
	 * @return string[]
	 */
	protected function get_context() {
		return array(
			'textColor',
			'customTextColor',
			'backgroundColor',
			'customBackgroundColor',
			'overlayTextColor',
			'customOverlayTextColor',
			'overlayBackgroundColor',
			'customOverlayBackgroundColor',
			'fontSize',
			'customFontSize',
			'showSubmenuIcon',
			'maxNestingLevel',
			'openSubmenusOnClick',
			'style',
			'isResponsive', // Backward compatibility.
		);
	}

	/**
	 * Renders the `linguator/navigation-language-switcher` block on server.
	 *

	 *
	 * @param array    $attributes The block attributes.
	 * @param string   $content The saved content. Unused.
	 * @param \WP_Block $block The parsed block.
	 * @return string The HTML string output to serve.
	 */
	public function render( $attributes, $content, $block ) {
		$attributes        = $this->set_attributes_for_block( $attributes );
		$switcher          = new \Linguator\Includes\Controllers\Linguator_Switcher();
		$switcher_elements = (array) $switcher->the_languages( $this->links, array_merge( $attributes, array( 'raw' => true ) ) );

		if ( empty( $switcher_elements ) ) {
			return '';
		}

		if ( $attributes['dropdown'] ) {
			$inner_nav_link_blocks = array();
			$top_level_lang        = reset( $switcher_elements );
			foreach ( $switcher_elements as $switcher_element ) {
				if ( $switcher_element['current_lang'] && ! $attributes['hide_current'] ) {
					$top_level_lang = $switcher_element;
				}
			}

			foreach ( $switcher_elements as $switcher_element ) {
				if ( $switcher_element['slug'] === $top_level_lang['slug'] ) {
					continue; // Skip the active language so it's not repeated in the dropdown list
				}

				$inner_nav_link_blocks[] = $this->create_inner_block( 'core/navigation-link', $attributes, $switcher_element, $block->context );
			}

			$generated_class = wp_apply_generated_classname_support( $block->block_type )['class'];
			$submenu_block   = $this->create_inner_block( 'core/navigation-submenu', $attributes, $top_level_lang, $block->context, $inner_nav_link_blocks, $generated_class );
			$output        = $submenu_block->render();
		} else {
			$output = '';
			$generated_class = wp_apply_generated_classname_support( $block->block_type )['class'];

			foreach ( $switcher_elements as $switcher_element ) {
				$output .= $this->create_inner_block( 'core/navigation-link', $attributes, $switcher_element, $block->context, array(), $generated_class )->render();
			}
		}

		// Final sanitization to ensure flag/name/output content is safe.
		$output = wp_kses( (string) $output, $this->get_allowed_switcher_html() );

		if ( version_compare( $GLOBALS['wp_version'], '6.5-alpha', '<' ) ) {
			/*
			 * Backward compatibility with WordPress < 6.5.
			 * Since WordPress 6.5, our block is rendered automatically inside the auto-generated `<ul>` wrapper.
			 */
			return wp_kses(
				sprintf(
				'<ul class="wp-block-navigation__container wp-block-navigation">%s</ul>',
				$output
				),
				$this->get_allowed_switcher_html()
			);
		}

		return $output;
	}

	/**
	 * Register switcher menu item meta options as a REST API field.
	 *
	 *
	 * @return void
	 */
	public function linguator_register_switcher_menu_item_options_meta_rest_field() {
		register_post_meta(
			'nav_menu_item',
			'_lmat_menu_item',
			array(
				'object_subtype' => 'nav_menu_item',
				'description'    => __( 'Language switcher settings', 'translate-words' ),
				'single'         => true,
				'auth_callback'  => function( $allowed, $meta_key, $post_id ) {
					return current_user_can( 'edit_theme_options' );
				},
				'show_in_rest'   => array(
					'schema' => array(
						'type'                 => 'object',
						'additionalProperties' => array(
							'type' => 'boolean',
						),
					),
				),
			)
		);
	}

	/**
	 * Renders a core/naviagation-link or core/naviagation-submenu block by adding hreflang and lang attributes to the <a> tag
	 * and also the language flag if required.
	 *
	 *
	 * @param string   $block_content The block content.
	 * @param array    $block         The full block, including name and attributes.
	 * @param \WP_Block $instance      The block instance.
	 *
	 * @return string A formatted HTML string representing the core/navigation-link or core/navigation-submenu block.
	 */
	public function linguator_render_custom_attributes( $block_content, $block, $instance ) {
		if ( ! $this->internal_blocks->offsetExists( $instance ) ) {
			return $block_content;
		}

		$snapshot = $this->internal_blocks->offsetGet( $instance );
		$this->internal_blocks->offsetUnset( $instance );

		$content_tags = new \WP_HTML_Tag_Processor( $block_content );

		if ( 'core/navigation-submenu' === $instance->name ) {
			// If `openSubmenusOnClick`, the submenu is rendered as a button, so there are no `<a>` to process.
			if ( empty( $instance->context['openSubmenusOnClick'] ) && $content_tags->next_tag( array( 'tag_name' => 'a' ) ) ) {
				$content_tags->set_attribute( 'hreflang', $snapshot['locale'] );
				$content_tags->set_attribute( 'lang', $snapshot['locale'] );
			}
			if ( $content_tags->next_tag( array( 'tag_name' => 'button' ) ) ) {
				$content_tags->set_attribute(
					'aria-label',
					str_replace(
						static::PLACEHOLDER,
						__( 'Languages', 'translate-words' ),
						(string) $content_tags->get_attribute( 'aria-label' )
					)
				);
			}
		} elseif ( $content_tags->next_tag( array( 'tag_name' => 'a' ) ) ) {
			$content_tags->set_attribute( 'hreflang', $snapshot['locale'] );
			$content_tags->set_attribute( 'lang', $snapshot['locale'] );
		}

		$overridden_block_content = $content_tags->get_updated_html();

		return wp_kses(
			str_replace(
			static::PLACEHOLDER,
				$snapshot['label'],
			$overridden_block_content
			),
			$this->get_allowed_switcher_html()
		);
	}

	/**
	 * Creates a navigation block and keeps its generated label outside block attributes.
	 *
	 * @param string     $block_name     Core block name.
	 * @param array      $attributes     Language switcher settings.
	 * @param array      $switcher_item  Data for one language.
	 * @param array      $context        Parent block context.
	 * @param \WP_Block[] $inner_blocks   Optional child blocks.
	 * @param string     $generated_class Additional class for an outer block.
	 * @return \WP_Block
	 */
	private function create_inner_block( $block_name, $attributes, $switcher_item, $context, $inner_blocks = array(), $generated_class = '' ) {
		$core_attributes = $this->linguator_get_core_block_attributes( $switcher_item );
		if ( '' !== $generated_class ) {
			$core_attributes['className'] .= ' ' . $generated_class;
		}

		$block = new \WP_Block(
			array(
				'blockName'   => $block_name,
				'attrs'       => $core_attributes,
				'innerBlocks' => $inner_blocks,
			),
			$context
		);

		$label = '';
		if ( $attributes['show_flags'] ) {
			$label .= wp_kses( (string) $switcher_item['flag'], $this->get_allowed_switcher_html() );
		}
		if ( $attributes['show_names'] ) {
			$name = esc_html( (string) $switcher_item['name'] );
			$label .= $attributes['show_flags'] ? ' ' . $name : $name;
		}

		$this->internal_blocks->offsetSet(
			$block,
			array(
				'locale' => sanitize_text_field( (string) $switcher_item['locale'] ),
				'label'  => $label,
			)
		);

		return $block;
	}

	/**
	 * Returns attributes for a core navigation block created by the switcher.
	 * @param array $switcher_item Array of a switcher item data.
	 * @return array Attributes to be rendered by core.
	 */
	private function linguator_get_core_block_attributes( $switcher_item ) {
		return array(
			'label'     => static::PLACEHOLDER,
			'url'       => esc_url_raw( (string) $switcher_item['url'] ),
			'className' => trim( implode( ' ', array_map( 'sanitize_html_class', (array) $switcher_item['classes'] ) ) ),
		);
	}
}

