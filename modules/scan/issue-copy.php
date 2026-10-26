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
		if ( 0 === strpos( $id, 'url_route_' ) ) {
			return array(
				'why' => __( 'A language URL that returns an error or redirects to another language cannot be reached by visitors or search engines.', 'translate-words' ),
				'how' => __( 'Open the URL in your browser to confirm it. If it fails, check the URL settings, flush the permalinks, and make sure the page is published.', 'translate-words' ),
			);
		}
		if ( 'translated_permalink_review' === $id ) {
			return array(
				'why' => __( 'A translation whose slug is the source slug plus a number looks like a duplicate address and is easy to mistype or share wrongly.', 'translate-words' ),
				'how' => __( 'Edit the translation and give it a slug in its own language.', 'translate-words' ),
			);
		}
		if ( 0 === strpos( $id, 'url_config_' ) ) {
			return array(
				'why' => __( 'URL settings decide how visitors and search engines reach each language. A wrong setting can send them to a missing page or the wrong language.', 'translate-words' ),
				'how' => __( 'Open the linked settings page, correct the setting named in the row, then rescan.', 'translate-words' ),
			);
		}
		if ( 0 === strpos( $id, 'elementor_' ) ) {
			return array(
				'why' => __( 'Elementor templates need valid layouts and published, linked translations to display correctly in each language.', 'translate-words' ),
				'how' => __( 'Use the language flags to create missing translations, or Edit in Elementor to review an existing template. Linguator provides the template integration; no extra language-switcher plugin is required.', 'translate-words' ),
			);
		}
		if ( 0 === strpos( $id, 'nav_' ) ) {
			return array(
				'why' => __( 'A block theme header shared between languages shows visitors menu labels and links in the wrong language.', 'translate-words' ),
				'how' => __( 'Open the Site Editor, give each language its own header and navigation, and fix the links listed in the rows.', 'translate-words' ),
			);
		}
		if ( 0 === strpos( $id, 'menu_' ) && 'menu_dead_links' !== $id ) {
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
			'content_identical' => array(
				__( 'A translation that still reads exactly like the default language shows visitors untranslated text.', 'translate-words' ),
				__( 'Open the translation, replace the original text with the translated text, then rescan.', 'translate-words' ),
			),
			'term_missing' => array(
				__( 'A category or tag without a translation falls back to the default language in archives and filters, so visitors see mixed languages.', 'translate-words' ),
				__( 'Use Translate on each language chip to create the missing term translation.', 'translate-words' ),
			),
			'lang_untranslated_types' => array(
				__( 'Content in a post type or taxonomy that is not translatable stays in one language, whatever language the visitor picks.', 'translate-words' ),
				__( 'Open Linguator settings and switch on the post types and taxonomies you want to translate.', 'translate-words' ),
			),
			'menu_dead_links' => array(
				__( 'A menu item that goes to a missing or unpublished page shows visitors a page-not-found error.', 'translate-words' ),
				__( 'Open the menu, then remove the item or point it to a published page.', 'translate-words' ),
			),
			'url_dupe_slugs' => array(
				__( 'When posts in different languages share a slug, visitors and search engines can land on the wrong language or a 404.', 'translate-words' ),
				__( 'Open each post listed in the row and give one of them a different slug.', 'translate-words' ),
			),
			'content_acf' => array(
				__( 'A custom field that still holds the original text shows visitors untranslated text, such as a subtitle or button label.', 'translate-words' ),
				__( 'Open the translation, replace the field text with the translated text, then rescan. Fields Linguator copies between translations are not reported.', 'translate-words' ),
			),
			'content_placeholder' => array(
				__( 'A shortcode missing from a translation usually means a form, button or widget disappeared from that page, and a changed placeholder shows raw codes to visitors.', 'translate-words' ),
				__( 'Open the translation and restore the missing shortcodes or placeholders so they match the original, keeping the surrounding text translated.', 'translate-words' ),
			),
			'content_links' => array(
				__( 'A link to a missing, unpublished or wrong-language page sends visitors to an error or to text they cannot read.', 'translate-words' ),
				__( 'Open the page that holds the link and replace it with the working page in the right language.', 'translate-words' ),
			),
		);

		if ( 0 === strpos( $id, 'content_identical_' ) ) {
			$id = 'content_identical';
		} elseif ( 0 === strpos( $id, 'content_link_' ) ) {
			$id = 'content_links';
		} elseif ( 0 === strpos( $id, 'content_placeholder_' ) ) {
			$id = 'content_placeholder';
		} elseif ( 0 === strpos( $id, 'content_acf_' ) ) {
			$id = 'content_acf';
		} elseif ( 0 === strpos( $id, 'term_missing_' ) ) {
			$id = 'term_missing';
		}

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
