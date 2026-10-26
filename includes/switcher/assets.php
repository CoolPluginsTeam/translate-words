<?php
/**
 * @package Linguator
 */

namespace Linguator\Includes\Switcher;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages the switcher's frontend CSS and JS handles.
 */
class Assets {
	public const FRONTEND_ASSET_HANDLE = 'lmat-language-switcher';

	/**
	 * Enqueues frontend CSS when registered.
	 *
	 * @return void
	 */
	public static function enqueue_frontend_styles(): void {
		if ( wp_style_is( self::FRONTEND_ASSET_HANDLE, 'registered' ) ) {
			wp_enqueue_style( self::FRONTEND_ASSET_HANDLE );
		}
	}

	/**
	 * Enqueues frontend JS when registered. Called on the fly by layouts that need it.
	 *
	 * @return void
	 */
	public static function enqueue_frontend_scripts(): void {
		if ( wp_script_is( self::FRONTEND_ASSET_HANDLE, 'registered' ) ) {
			wp_enqueue_script( self::FRONTEND_ASSET_HANDLE );
		}
	}

	/**
	 * Registers frontend CSS if the built file exists.
	 *
	 * @return void
	 */
	public static function register_styles(): void {
		$file = self::get_build_file( 'css', 'language-switcher' );

		if ( $file ) {
			wp_register_style( self::FRONTEND_ASSET_HANDLE, LINGUATOR_URL . $file, array(), LINGUATOR_VERSION );
		}
	}

	/**
	 * Registers frontend JS if the built file exists.
	 *
	 * @return void
	 */
	public static function register_scripts(): void {
		$file = self::get_build_file( 'js', 'language-switcher' );

		if ( $file ) {
			wp_register_script( self::FRONTEND_ASSET_HANDLE, LINGUATOR_URL . $file, array(), LINGUATOR_VERSION, array( 'in_footer' => true ) );
		}
	}

	/**
	 * Returns the relative path of a built frontend asset, minified unless SCRIPT_DEBUG is on.
	 *
	 * @param string $type Asset type, 'css' or 'js'.
	 * @param string $name Asset base name.
	 * @return string Relative path, empty if the file does not exist.
	 */
	private static function get_build_file( string $type, string $name ): string {
		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
		$file   = "/assets/frontend/{$type}/{$name}{$suffix}.{$type}";

		return file_exists( LINGUATOR_DIR . $file ) ? $file : '';
	}
}
