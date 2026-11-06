<?php
/**
 * @package Linguator
 */

namespace Linguator\Integrations\woocommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Don't access directly.
}

/**
 * Manages the compatibility with WooCommerce.
 *
 * WooCommerce serves the configured shop page as the `product` post type archive, so Linguator
 * never reaches the `is_page()` branch of Linguator_Frontend_Links::get_translation_url() for it
 * and the language switcher falls back to the home page. This integration resolves the translation
 * of the shop page itself, which is an ordinary translated page.
 *
 * @since 2.2.1
 */
class Linguator_WooCommerce {

	/**
	 * Setups filters.
	 *
	 * @since 2.2.1
	 *
	 * @return void
	 */
	public function init() {
		// Priority 15: after the core handler of Linguator_Frontend_Static_Pages (10) and before its customizer override (20).
		add_filter( 'lmat_pre_translation_url', array( $this, 'shop_translation_url' ), 15, 2 );
	}

	/**
	 * Returns the URL of the translated shop page when the shop archive is displayed.
	 *
	 * The shop page is served as the `product` post type archive, so Linguator cannot reach its
	 * page translation on its own. Everything else, including the translated shop pages themselves,
	 * is left to Linguator's standard resolution.
	 *
	 * @since 2.2.1
	 *
	 * @param string $url      An empty string or the URL of the translation of the current page.
	 * @param object $language The language of the translation.
	 * @return string The translated shop page URL, or $url unchanged when this integration does not apply.
	 */
	public function shop_translation_url( $url, $language ) {
		if ( ! empty( $url ) ) {
			return $url; // Another handler already resolved the translation URL.
		}

		if ( ! is_object( $language ) || empty( $language->slug ) ) {
			return $url;
		}

		if ( ! $this->is_shop_archive() ) {
			return $url;
		}

		$shop_id = $this->get_shop_page_id();

		if ( $shop_id <= 0 ) {
			return $url;
		}

		if ( ! function_exists( 'linguator_get_post' ) ) {
			return $url;
		}

		$translated_id = linguator_get_post( $shop_id, $language->slug );
		$translated_id = is_numeric( $translated_id ) ? (int) $translated_id : 0;

		if ( $translated_id <= 0 || ! $this->can_read( $translated_id ) ) {
			return $url;
		}

		$permalink = get_permalink( $translated_id );

		return is_string( $permalink ) && '' !== $permalink ? $permalink : $url;
	}

	/**
	 * Tells whether the current request displays the WooCommerce shop archive.
	 *
	 * @since 2.2.1
	 *
	 * @return bool
	 */
	protected function is_shop_archive() {
		if ( ! function_exists( 'is_shop' ) ) {
			return false; // WooCommerce is not active.
		}

		if ( is_front_page() ) {
			return false; // Linguator already resolves the home URL of each language.
		}

		return (bool) is_shop();
	}

	/**
	 * Returns the ID of the page configured as the WooCommerce shop.
	 *
	 * @since 2.2.1
	 *
	 * @return int The page ID, 0 when it cannot be determined.
	 */
	protected function get_shop_page_id() {
		if ( ! function_exists( 'wc_get_page_id' ) ) {
			return 0;
		}

		$shop_id = (int) wc_get_page_id( 'shop' ); // WooCommerce returns -1 when the option is unset.

		return $shop_id > 0 ? $shop_id : 0;
	}

	/**
	 * Tells whether the current user can read the translated page.
	 *
	 * Mirrors the check Linguator applies to ordinary pages in Linguator_Frontend_Links::get_translation_url().
	 *
	 * @since 2.2.1
	 *
	 * @param int $post_id Translated page ID.
	 * @return bool
	 */
	protected function can_read( $post_id ) {
		if ( ! function_exists( 'LMAT' ) ) {
			return false;
		}

		$linguator = LMAT();

		if ( ! is_object( $linguator ) || ! isset( $linguator->model->post ) ) {
			return false;
		}

		return (bool) $linguator->model->post->current_user_can_read( $post_id );
	}
}
