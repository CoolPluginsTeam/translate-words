<?php
/** Shared accordion wording ported from Toolkit for Polylang. */
namespace Linguator\Modules\Scan;
if ( ! defined( 'ABSPATH' ) ) exit;
final class Toolkit_Copy {
 public static function for_key( string $key ): ?array {
  $copy = array(
   'content_missing' => array(
    'display_label' => __( 'Missing Translations', 'translate-words' ),
    'display_message' => __( 'Some pages or posts are missing translations in one or more languages.', 'translate-words' ),
    'why' => __( 'Visitors switching languages on these pages will miss out on content or see empty sections.', 'translate-words' ),
    'how' => __( 'Click the "+" (Translate) button in the table below to add the missing language version for each page.', 'translate-words' ),
   ),
   'content_identical' => array(
    'display_label' => __( 'Identical Content', 'translate-words' ),
    'display_message' => __( 'Some translated pages still contain the exact same title or body text as the original language.', 'translate-words' ),
    'why' => __( 'Visitors on your translated pages will see untranslated text from the original language.', 'translate-words' ),
    'how' => __( 'Open the translated page in WordPress and replace the copied original text with your translated copy.', 'translate-words' ),
   ),
   'content_acf' => array(
    'display_label' => __( 'ACF & Custom Fields', 'translate-words' ),
    'display_message' => __( 'Some translated pages contain ACF or custom fields that still match the original language.', 'translate-words' ),
    'why' => __( 'Visitors on your translated pages will see untranslated text in ACF and custom fields from the original language.', 'translate-words' ),
    'how' => __( 'Open the translated page in WordPress and translate the custom fields into that language.', 'translate-words' ),
   ),
   'content_placeholder' => array(
    'display_label' => __( 'Shortcode & Placeholder Mismatch', 'translate-words' ),
    'display_message' => __( 'Some translated pages or posts have shortcodes or placeholders that do not match the original: one is missing, extra, or used a different number of times.', 'translate-words' ),
    'why' => __( 'A missing shortcode usually means a form, button or widget disappeared from the translated page. A changed placeholder, such as a merge tag or a number code, can show raw codes to visitors.', 'translate-words' ),
    'how' => __( 'Open the translated page, compare it with the original, and restore the shortcode or placeholder. If the difference is intended, use Ignore.', 'translate-words' ),
   ),
   'content_links' => array(
    'display_label' => __( 'Content Links', 'translate-words' ),
    'display_message' => __( 'Some links inside translated pages still point to original default-language URLs.', 'translate-words' ),
    'why' => __( 'Clicking links on a translated page can accidentally send visitors back to your default language.', 'translate-words' ),
    'how' => __( 'Edit the translated page and update each link so it opens the matching page in that language.', 'translate-words' ),
   ),
   'content_draft' => array(
    'display_label' => __( 'Unpublished Page/Post', 'translate-words' ),
    'display_message' => __( 'Some translations exist but are saved as Draft or Pending Review instead of Published.', 'translate-words' ),
    'why' => __( 'Unpublished page/post translations are hidden from live site visitors.', 'translate-words' ),
    'how' => __( 'Open each draft translation in the WordPress editor and click Publish.', 'translate-words' ),
   ),
   'content_source_updated' => array(
    'display_label' => __( 'Default Language Updated', 'translate-words' ),
    'display_message' => __( 'The default language was updated. Linked translations may need a matching update.', 'translate-words' ),
    'why' => __( 'Visitors can see newer content in the default language while other languages still show the older version.', 'translate-words' ),
    'how' => __( 'Open each translation below and update it (or use AI Translation), then rescan the issue.', 'translate-words' ),
   ),
   'term_missing' => array(
    'display_label' => __( 'Categories & Tags', 'translate-words' ),
    'display_message' => __( 'Some categories or tags are missing translations.', 'translate-words' ),
    'why' => __( 'Category and tag archive pages will show default language names when translations are missing.', 'translate-words' ),
    'how' => __( 'Go to Posts → Categories (or Tags) and click "+" to add translations for each category.', 'translate-words' ),
   ),
   'url_configuration' => array(
    'display_label' => __( 'URL Settings', 'translate-words' ),
    'display_message' => __( 'Linguator or WordPress URL settings need attention.', 'translate-words' ),
    'why' => __( 'Incorrect URL settings prevent Linguator from routing visitors to the right language, making whole language sections unreachable.', 'translate-words' ),
    'how' => __( 'Follow the fix link for each item below to update the specific Linguator or WordPress permalink setting.', 'translate-words' ),
   ),
   'translated_permalinks' => array(
    'display_label' => __( 'Translated URL Slugs', 'translate-words' ),
    'display_message' => __( 'Some translated pages use automatic numbered URLs (like /about-2/).', 'translate-words' ),
    'why' => __( 'Numbered URLs work fine, but custom translated address slugs (like /a-propos/) look cleaner and perform better for SEO.', 'translate-words' ),
    'how' => __( 'Click Review permalink to edit the page slug in WordPress, or click Ignore if the current address is acceptable.', 'translate-words' ),
   ),
   'routing_health' => array(
    'display_label' => __( 'Language URLs', 'translate-words' ),
    'display_message' => __( 'Some language URLs returned a broken 404 page error or timeout.', 'translate-words' ),
    'why' => __( 'Broken language URLs hide content from visitors. (Note: Timeouts on local testing sites are usually temporary testing limits — verify in browser first).', 'translate-words' ),
    'how' => __( 'Go to Settings → Permalinks and click Save Changes to rebuild URL rules. If testing on localhost and the page works in your browser, click Ignore.', 'translate-words' ),
   ),
   'homepage_translations' => array(
    'display_label' => __( 'Homepage', 'translate-words' ),
    'display_message' => __( 'The front page is missing a translation or has a blank layout in one or more languages.', 'translate-words' ),
    'why' => __( 'Visitors switching languages on your homepage will see a blank page or get redirected back to the default homepage.', 'translate-words' ),
    'how' => __( 'Open the homepage, click Translate for each missing language, and copy the page layout before translating.', 'translate-words' ),
   ),
   'menu_assignments' => array(
    'display_label' => __( 'Navigation Menus', 'translate-words' ),
    'display_message' => __( 'Navigation menus are not assigned to menu locations for all languages.', 'translate-words' ),
    'why' => __( 'Visitors browsing in secondary languages will see the default language menu or an empty header.', 'translate-words' ),
    'how' => __( 'Go to Appearance → Menus → Manage Locations, and assign a menu for each active language.', 'translate-words' ),
   ),
   'menu_dead_links' => array(
    'display_label' => __( 'Menu Links', 'translate-words' ),
    'display_message' => __( 'Some menu items link to pages that are missing or kept as Draft/Private.', 'translate-words' ),
    'why' => __( 'Visitors who click these menu items will land on a 404 Page Not Found error.', 'translate-words' ),
    'how' => __( 'Go to Appearance → Menus, and edit or remove links pointing to missing or unpublished pages.', 'translate-words' ),
   ),
   'navigation' => array(
    'display_label' => __( 'Navigation', 'translate-words' ),
    'display_message' => __( 'Block theme detected. Header and Navigation links in the Site Editor need review.', 'translate-words' ),
    'why' => __( 'Block themes use Site Editor navigation. Missing translations or wrong-language links can confuse visitors after switching languages.', 'translate-words' ),
    'how' => __( 'Open Appearance → Editor (Site Editor), select your Header template, and update navigation links for each language.', 'translate-words' ),
   ),
   'strings_missing' => array(
    'display_label' => __( 'Text String Translation', 'translate-words' ),
    'display_message' => __( 'Some site-wide text labels registered in Linguator are missing translations.', 'translate-words' ),
    'why' => __( 'Global labels (like site taglines, button text, or widget headers) remain in the original language on translated pages.', 'translate-words' ),
    'how' => __( 'Go to Languages → String translation in Linguator and fill in the translated text for each string.', 'translate-words' ),
   ),
   'lang_permalinks' => array(
    'display_label' => __( 'Language Setup', 'translate-words' ),
    'display_message' => __( 'WordPress is set to Plain permalinks, so language address prefixes (like /fr/) cannot work.', 'translate-words' ),
    'why' => __( 'Linguator adds language codes to URLs. With Plain permalinks enabled, language codes cannot be used.', 'translate-words' ),
    'how' => __( 'Go to Settings → Permalinks, select "Post name", and click Save Changes.', 'translate-words' ),
   ),
   'lang_default' => array(
    'display_label' => __( 'Language Setup', 'translate-words' ),
    'display_message' => __( 'No default main language is set in Linguator.', 'translate-words' ),
    'why' => __( 'Every translation links back to a primary default language. Without a main language set, translations cannot be linked.', 'translate-words' ),
    'how' => __( 'Go to Linguator → Manage Languages, and star/set your primary default language.', 'translate-words' ),
   ),
   'lang_secondary' => array(
    'display_label' => __( 'Language Setup', 'translate-words' ),
    'display_message' => __( 'Only one language is currently set up in Linguator.', 'translate-words' ),
    'why' => __( 'Translations only work when at least two languages exist on your site.', 'translate-words' ),
    'how' => __( 'Go to Linguator → Manage Languages and add a second language.', 'translate-words' ),
   ),
   'elementor_fields' => array(
    'display_label' => __( 'Empty translated fields', 'translate-words' ),
    'display_message' => __( 'Translatable text fields are empty in translations while the original has content.', 'translate-words' ),
    'why' => __( '', 'translate-words' ),
    'how' => __( 'Open the translation in Elementor and fill in the empty text fields.', 'translate-words' ),
   ),
   'elementor_orphan' => array(
    'display_label' => __( 'Translations not linked to an original', 'translate-words' ),
    'display_message' => __( 'These layouts exist in another language, but Linguator has not connected them to an original (default-language) version. Visitors may not get the right layout when switching languages.', 'translate-words' ),
    'why' => __( '', 'translate-words' ),
    'how' => __( 'Open each layout in Linguator and link it to its original-language version, or create that original if it is missing. Delete the layout only if it is leftover and not needed.', 'translate-words' ),
   ),
   'elementor_untranslated' => array(
    'display_label' => __( 'Same texts as the original language', 'translate-words' ),
    'display_message' => __( 'These widgets still show the exact same words as the original language. Open each one in Elementor and translate the text — or ignore the warning if it should stay the same (for example a brand or product name).', 'translate-words' ),
    'why' => __( '', 'translate-words' ),
    'how' => __( 'Open the widget in Elementor and replace the copied original text with a translation. If the text is a brand name that should stay the same in every language, you can ignore that item.', 'translate-words' ),
   ),
   'elementor_empty' => array(
    'display_label' => __( 'Empty Elementor translations', 'translate-words' ),
    'display_message' => __( 'These translated pages have no Elementor widgets. The original has widgets in its page layout, but the translation is blank — visitors may see an empty page. Open each one in Elementor and copy the layout from the original, then translate the text. (Header and footer from Theme Builder are checked separately.)', 'translate-words' ),
    'why' => __( '', 'translate-words' ),
    'how' => __( 'Open the translation in Elementor, copy the layout from the original language, then translate the text.', 'translate-words' ),
   ),
   'elementor_repeater' => array(
    'display_label' => __( 'Repeater row mismatches', 'translate-words' ),
    'display_message' => __( 'Repeater rows in translations do not match the original document.', 'translate-words' ),
    'why' => __( '', 'translate-words' ),
    'how' => __( 'Open the translation in Elementor and match the number of repeater rows to the original.', 'translate-words' ),
   ),
  );
  return $copy[$key] ?? null;
 }
}
