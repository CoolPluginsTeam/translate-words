<?php
/**
 * Plain-English "why it matters" and "how to fix" text for scan issues.
 *
 * @package Linguator
 */

namespace Linguator\Modules\Scan;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Copy shown in "Fix These First".
 *
 * @since 2.3.0
 */
class Issue_Copy {

	/**
	 * Copy for a result id.
	 *
	 * @since 2.3.0
	 *
	 * @param string $id Result id.
	 * @return array{why:string,how:string}
	 */
	public static function for_id( string $id ): array {
		if ( 0 === strpos( $id, 'elementor_' ) ) {
			return array(
				'why' => __( 'Elementor templates need valid layouts and published, linked translations to display correctly in each language.', 'translate-words' ),
				'how' => __( 'Use the language flags to create missing translations, or Edit in Elementor to review an existing template. Linguator provides the template integration; no extra language-switcher plugin is required.', 'translate-words' ),
			);
		}
		if ( 0 === strpos( $id, 'menu_' ) ) {
			return array(
				'why' => __( 'Visitors may see missing or incomplete navigation when they change language.', 'translate-words' ),
				'how' => __( 'Open Manage menu locations and assign a menu for each language in the locations your theme uses.', 'translate-words' ),
			);
		}
		if ( 0 === strpos( $id, 'string_' ) ) {
			return array(
				'why' => __( 'Untranslated site text can appear in the original language on translated pages.', 'translate-words' ),
				'how' => __( 'Open Static strings, select the affected language and save a translation for each listed string.', 'translate-words' ),
			);
		}
		$map = array(
			'lang_switcher' => array(
				__( 'Visitors need a visible language switcher to find your translated content.', 'translate-words' ),
				__( 'Add a Linguator language switcher to a menu, widget area or block template used by your site.', 'translate-words' ),
			),
			'lang_default'        => array(
				__( 'Without a default language Linguator cannot decide which content is the original, so translations and redirects break.', 'translate-words' ),
				__( 'Open Manage Languages and set a default language.', 'translate-words' ),
			),
			'lang_secondary'      => array(
				__( 'With one language there is nothing to switch to, so visitors never see a translated site.', 'translate-words' ),
				__( 'Open Manage Languages and add at least one more language.', 'translate-words' ),
			),
			'lang_homepage'       => array(
				__( 'The homepage is the first page most visitors see. A missing translation sends them to the wrong language or a 404.', 'translate-words' ),
				__( 'Use the Translate button on each language below to create the homepage translation, then publish it.', 'translate-words' ),
			),
			'url_domains'         => array(
				__( 'In domain mode a language without a domain cannot be reached by visitors.', 'translate-words' ),
				__( 'Edit each listed language and enter its domain, or switch the URL modification mode in General Settings.', 'translate-words' ),
			),
			'content_source_updated' => array(
				__( 'The default-language text changed after this translation was made, so visitors may read outdated content in their language.', 'translate-words' ),
				__( 'Open See what changed, update the translation, then choose Mark as up to date. Choose it without editing when the change needs no translation.', 'translate-words' ),
			),
			'content_no_language' => array(
				__( 'Content with no language is hidden from translated pages and cannot be linked to translations.', 'translate-words' ),
				__( 'Use Assign default language to give every untagged post, page and term the default language in one step.', 'translate-words' ),
			),
		);

		if ( isset( $map[ $id ] ) ) {
			return array(
				'why' => $map[ $id ][0],
				'how' => $map[ $id ][1],
			);
		}

		return array(
			'why' => __( 'This check highlights a part of your multilingual setup that may need attention.', 'translate-words' ),
			'how' => __( 'Review the issue description and affected items, then use the available settings or edit link. Recheck after making changes.', 'translate-words' ),
		);
	}
}
