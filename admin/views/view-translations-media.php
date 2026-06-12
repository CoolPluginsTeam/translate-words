<?php
namespace Linguator\Admin\Views;
/**
 * Displays the translations fields for media
 * Needs WP 3.5+
 *
 * @package Linguator
 *
 * @var Linguator_Admin_Classic_Editor $this    Linguator_Admin_Classic_Editor object.
 * @var Linguator_Language             $lang    The media language. Default language if no language assigned yet.
 * @var int                      $post_ID The media Id.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


?>
<p><strong><?php esc_html_e( 'Translations', 'translate-words' ); ?></strong></p>
<table>
	<?php
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
	foreach ( $this->model->get_languages_list() as $language ) {
		if ( $language->term_id === $lang->term_id ) {
			continue;
		}
		?>
		<tr>
			<td class = "lmat-media-language-column"><span class = "lmat-translation-flag"><?php echo $language->get_admin_flag_kses( 'aria-hidden' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><?php echo esc_html( $language->name ); ?></td>
			<td class = "lmat-media-edit-column">
				<?php
				// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
				$translation_id = $this->model->post->get_translation( $post_ID, $language );
				if ( ! empty( $translation_id ) && $translation_id !== $post_ID ) {
					// The translation exists
					printf(
						'<input type="hidden" name="media_tr_lang[%s]" value="%d" />',
						esc_attr( $language->slug ),
						(int) $translation_id
					);
					echo wp_kses_post( $this->links->edit_post_translation_link( $translation_id ) );
				} else {
					// No translation
					echo wp_kses_post( $this->links->new_post_translation_link( $post_ID, $language ) );
				}
				?>
			</td>
		</tr>
		<?php
	} // End foreach
	?>
</table>
