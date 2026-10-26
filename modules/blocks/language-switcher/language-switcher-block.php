<?php
/**
 * @package Linguator
 */

namespace Linguator\Modules\Blocks;

/**
 * Language switcher block.
 *
 */
class Linguator_Language_Switcher_Block extends Linguator_Abstract_Language_Switcher_Block {

	/**
	 * Returns the language switcher block name with the Linguator's namespace.
	 *
	 *
	 * @return string The block name.
	 */
	protected function get_block_name() {
		return 'linguator/language-switcher';
	}

	/**
	 * Renders the `linguator/language-switcher` block on server.
	 *
	 *
	 * @param array    $attributes The block attributes.
	 * @param string   $content The saved content. Unused.
	 * @param \WP_Block $block The parsed block. Unused.
	 * @return string Returns the language switcher.
	 */
	public function render( $attributes, $content, $block ) { //phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		static $block_id = 0;
		++$block_id;

		$attributes = $this->set_attributes_for_block( $attributes );

		$attributes['raw']          = false;
		$attributes['echo']         = false;
		$attributes['show_wrapper'] = true;
		$attributes['unique_id']    = 'lmat-switcher-block-' . $block_id;

		// Legacy block attributes are converted from their `dropdown` key.
		if ( ! isset( $attributes['layout'] ) ) {
			$attributes['layout'] = empty( $attributes['dropdown'] ) ? 'vertical' : 'select';
		}

		$switcher        = new \Linguator\Includes\Controllers\Linguator_Switcher();
		$switcher_output = $switcher->the_languages( $this->links, $attributes );

		if ( empty( $switcher_output ) ) {
			return '';
		}

		// The switcher markup is escaped by its elements.
		return sprintf( '<div %1$s>%2$s</div>', get_block_wrapper_attributes(), $switcher_output );
	}
}

