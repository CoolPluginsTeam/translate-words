<?php
/**
 * Admin page content.
 *
 * @package tww
 */

/**
 * A temporary variable since we don't seem to be able to use function calls in
 * HEREDOC.
 */
$tww_translation_lines = esc_attr( TWW_TRANSLATIONS_LINES );

/**
 * Define a template pattern for reuse.
 * This covers the new translation input fields and is used in both PHP and JS.
 */
define(
	'TWW_NEW_STRING_TEMPLATE',
<<<TEMPLATE
<tr valign="top">
<td style="white-space: nowrap">
	<input type="text" style="width:100%;" name="{$tww_translation_lines}[original][]" />
	&rarr;
</td>
<td><input type="text" style="width:100%;" name="{$tww_translation_lines}[overwrite][]" /></td>
<td></td>
</tr>
TEMPLATE
);


/**
 * Add the admin menu.
 *
 * @return void
 */
function tww_add_admin_menu() {

	add_options_page(
		esc_html__( 'Translate Words', 'translate-words' ),
		esc_html__( 'Translate Words', 'translate-words' ),
		'administrator',
		TWW_PAGE,
		'tww_setting_page'
	);

}

add_action( 'admin_menu', 'tww_add_admin_menu' );


/**
 * Enqueue Admin Scripts.
 *
 * @return void
 */
function tww_admin_enqueue_scripts() {

	global $pagenow;

	if ( 'options-general.php' !== $pagenow ) {
		return;
	}

	if ( ! isset( $_REQUEST['page'] ) ) {
		return;
	}

	if ( isset( $_REQUEST['page'] ) && 'tww_settings' !== $_REQUEST['page'] ) {
		return;
	}

	wp_enqueue_script(
		'TWW_TRANSLATIONS_ADMIN',
		TWW_PLUGINS_DIR . 'js/main.js',
		array( 'jquery' ),
		'1.0.1',
		false
	);

	wp_localize_script(
		'TWW_TRANSLATIONS_ADMIN',
		'tww_properties',
		array(
			'template' => TWW_NEW_STRING_TEMPLATE,
		)
	);

}

add_action( 'admin_enqueue_scripts', 'tww_admin_enqueue_scripts' );


/**
 * Initialize the setting.
 *
 * @return void
 */
function tww_settings_init() {

	register_setting(
		TWW_TRANSLATIONS,
		TWW_TRANSLATIONS_LINES,
		array(
			'sanitize_callback' => 'tww_validate_translations_and_save',
			'type' => 'array',
			'default' => '',
		)
	);

}

add_action( 'admin_init', 'tww_settings_init' );


/**
 * Validate the translations and save the settings.
 *
 * @param {array} $strings The translations strings to save.
 * @return {void}
 */
function tww_validate_translations_and_save( $strings ) {

	$update_translations = array();

	if (
		! empty( $strings['original'] ) &&
		count( $strings['original'] ) > 0
	) {

		foreach ( $strings['original'] as $key => $value ) {

			if ( ! empty( $value ) ) {
				$update_translations[] = array(
					'original' => $value,
					'overwrite' => $strings['overwrite'][ $key ],
				);
			}
		}

	}

	return $update_translations;

}


/**
 * Display the settings page.
 *
 * We don't need to generate a nonce because we're using settings fields which
 * does this for us.
 *
 * @return void
 */
function tww_setting_page() {

	$translations = get_option( TWW_TRANSLATIONS_LINES );

?>
<style>
.translation-table {
	margin-top: 15px;
}
</style>
<div class="wrap">

	<h1 class="wp-heading-inline"><?php esc_html_e( 'Translate Words', 'translate-words' ); ?></h1>

	<form method="POST" action="options.php">

<?php
	do_settings_sections( TWW_TRANSLATIONS );
	settings_fields( TWW_TRANSLATIONS );
?>
		<table class="translation-table wp-list-table widefat fixed striped">
			<thead>
				<tr valign="top">
					<th scope="column" class="column-current"><?php esc_html_e( 'Current', 'translate-words' ); ?></th>
					<th scope="column" class="column-new"><?php esc_html_e( 'New', 'translate-words' ); ?></th>
					<th scope="column"></th>
				</tr>
			</thead>
			<tbody id="rowsTranslations">
<?php
	if ( ! empty( $translations ) ) {
		foreach ( $translations as $key => $value ) {

			$original = isset( $value['original'] ) ? $value['original'] : '';
			$overwrite = isset( $value['overwrite'] ) ? $value['overwrite'] : '';

?>
				<tr valign="top" id="row_id_<?php echo esc_attr( $key ); ?>_translate">
					<td style="white-space: nowrap">
						<input type="text" style="width:100%;" name="<?php echo esc_attr( TWW_TRANSLATIONS_LINES ); ?>[original][]" value="<?php echo esc_textarea( $original ); ?>" />
						&rarr;
					</td>
					<td>
						<input type="text" style="width:100%;" name="<?php echo esc_attr( TWW_TRANSLATIONS_LINES ); ?>[overwrite][]" value="<?php echo esc_textarea( $value['overwrite'] ); ?>" />
					</td>
					<td class="action">
						<span class="trash">
							<a
								href="#"
								class="submitdelete submitDeleteTranslation"
								aria-lable="<?php esc_attr_e( 'Remove this translation', 'translate-words' ); ?>"
								id="row_id_<?php echo esc_attr( $key ); ?>"><?php esc_html_e( 'Remove', 'translate-words' ); ?></span>
						</span>
					</td>
				</tr>
<?php
		}
	}

	echo TWW_NEW_STRING_TEMPLATE;

?>

			</tbody>
		</table>

		<p class="submit">
			<button class="button-secondary" style="margin:5px 0;" id="addTranslation"><?php esc_html_e( 'Add Translation +', 'translate-words' ); ?></button>
			<input type="submit" class="button-primary" style="margin:5px 0;" value="<?php esc_attr_e( 'Save', 'translate-words' ); ?>" />
		</p>

	</form>
</div>

<?php

}


/**
 * Output scripts and variables for translating Gutenberg editor strings.
 *
 * @return {void}
 */
function tww_translate_gutenberg_string() {

	// Output translations as json array.
	$overrides = get_option( TWW_TRANSLATIONS_LINES );

	if ( ! is_array( $overrides ) ) {
		return;
	}

	printf(
		'<script>var tww_translations = %s;</script>',
		wp_json_encode( $overrides )
	);

	// Enqueue editor scripts.
	wp_enqueue_script(
		'TWW_TRANSLATIONS_JS',
		TWW_PLUGINS_DIR . 'js/gb_i18n.js',
		array( 'jquery' ),
		'1.0.0',
		true
	);

}

add_filter( 'admin_head', 'tww_translate_gutenberg_string' );




