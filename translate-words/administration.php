<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

    /**
     * Admin page content.
     *
     * @package lmat
     */

    // Mark this file as deprecated - only on specific admin pages
    if (
        is_admin() &&
        isset( $_GET['page'] ) // phpcs:ignore WordPress.Security.NonceVerification
    ) {
        $page = sanitize_key( wp_unslash( $_GET['page'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
    
        if ( 'tww_settings' === $page || 'lmat_settings' === $page ) {
        _deprecated_file( 
            basename( __FILE__ ), 
            '2.0.0', 
            'Linguator functionality (use the Linguator features instead of Translate Words)' 
        );
        }
    }

    /**
     * Define a template pattern for reuse.
     * This covers the new translation input fields and is used in both PHP and JS.
     */
    define(
        'LMAT_NEW_STRING_TEMPLATE',
        '<tr valign="top">' .
        '<td style="white-space: nowrap">' .
        '<input type="text" style="width:100%;" name="' . esc_attr( LMAT_TRANSLATIONS_LINES ) . '[original][]" />' .
        '&rarr;' .
        '</td>' .
        '<td><input type="text" style="width:100%;" name="' . esc_attr( LMAT_TRANSLATIONS_LINES ) . '[overwrite][]" /></td>' .
        '<td></td>' .
        '</tr>'
    );

    /**
     * Add the admin menu.
     *
     * @return void
     */
    function linguator_add_admin_menu()
    {

        // Check if Loco Translate is active
        if (! function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $translations = get_option(LMAT_TRANSLATIONS_LINES);

        add_options_page(
            esc_html__('Translate Words', 'translate-words'),
            esc_html__('Translate Words', 'translate-words'),
            'administrator',
            LMAT_PAGE,
            'linguator_setting_page'
        );

    }

    add_action('admin_menu', 'linguator_add_admin_menu');

    /**
     * Enqueue Admin Scripts.
     *
     * @return void
     */
    function linguator_admin_enqueue_scripts()
    {

        global $pagenow;

        if ('options-general.php' !== $pagenow) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only checking page parameter to conditionally load scripts, not processing form data.
        if (! isset($_REQUEST['page'])) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only checking page parameter to conditionally load scripts, not processing form data.
        if (isset($_REQUEST['page']) && 'tww_settings' !== sanitize_key( wp_unslash( $_REQUEST['page'] ) ) ) {
            return;
        }

        wp_enqueue_script(
            'LMAT_TRANSLATIONS_ADMIN',
            LMAT_PLUGINS_DIR . 'js/main.js',
            ['jquery'],
            '1.0.1',
            false
        );

        wp_localize_script(
            'LMAT_TRANSLATIONS_ADMIN',
            'lmat_properties',
            [
                'template'      => LMAT_NEW_STRING_TEMPLATE,
                'ajax_url'      => admin_url('admin-ajax.php'),
                'dismiss_nonce' => wp_create_nonce('tww_dismiss_notice'),
            ]
        );

        // Add inline script for notice dismissal
        wp_add_inline_script(
            'LMAT_TRANSLATIONS_ADMIN',
            "
		jQuery(document).ready(function($) {
			$(document).on('click', '.tww-deprecation-notice .notice-dismiss', function() {
				$.ajax({
					url: lmat_properties.ajax_url,
					type: 'POST',
					data: {
						action: 'tww_dismiss_deprecation_notice',
						nonce: lmat_properties.dismiss_nonce
					}
				});
			});
		});
		"
        );

    }

    add_action('admin_enqueue_scripts', 'linguator_admin_enqueue_scripts');

    /**
     * Initialize the setting.
     *
     * @return void
     */
    function linguator_settings_init()
    {

        register_setting(
            LMAT_TRANSLATIONS,
            LMAT_TRANSLATIONS_LINES,
            [
                'sanitize_callback' => 'linguator_validate_translations_and_save',
                'type'              => 'array',
                'default'           => '',
            ]
        );

    }

    add_action('admin_init', 'linguator_settings_init');

    /**
     * Validate the translations and save the settings.
     *
     * @param {array} $strings The translations strings to save.
     * @return {void}
     */
    function linguator_validate_translations_and_save($strings)
    {

        $update_translations = [];

        if (
            ! empty($strings['original']) &&
            count($strings['original']) > 0
        ) {

            foreach ($strings['original'] as $key => $value) {

                if (! empty($value)) {
                    $update_translations[] = [
                        'original'  => sanitize_textarea_field($value),
                        'overwrite' => isset($strings['overwrite'][$key])
                            ? sanitize_textarea_field($strings['overwrite'][$key])
                            : '',
                    ];
                }
            }

        }
        // Check if Loco Translate is active and all data was removed
        if (empty($update_translations)) {
            if (! function_exists('is_plugin_active')) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
        }
        return $update_translations;
    }

    /**
     * Display deprecation notice for Translate Words on the settings page.
     *
     * @return void
     */
    function linguator_display_deprecation_notice()
    {
        // Only show on Translate Words settings page
        $screen = get_current_screen();
        if (! $screen || 'settings_page_' . LMAT_PAGE !== $screen->id) {
            return;
        }
        // Don't show notice if Loco Translate is active
        if (! function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        // Check if notice has been dismissed site-wide
        if (get_option('tww_deprecation_notice_dismissed')) {
            return;
        }

        // Build notice message
        $message = '<h3 style="margin-top: 0;">' . esc_html__('⚠️ Important Update: Translate Words is Evolving to a New AI Multilingual Solution', 'translate-words') . '</h3>';
        $message .= '<p>' . sprintf(
            // translators: %1$s: AI Multilingual, %2$s: Linguator
            __('We are working on a new and more powerful %1$s solution called %2$s, and Translate Words will gradually transition to this new plugin.', 'translate-words'),
            '<strong>AI Multilingual</strong>',
            '<strong>Linguator</strong>'
        ) . '</p>';
        $message .= '<p><strong>' . esc_html__('The current Translate Words functionality will be deprecated and discontinued in 6 months.', 'translate-words') . '</strong><br>';
        $message .= esc_html__('Until then, you can continue using this plugin safely.', 'translate-words') . '</p>';
        $message .= '<p>' . sprintf(
            // translators: %s: Loco Translate
            esc_html__('If you want to keep using a similar manual string translation workflow, please migrate to %s, which offers enhanced features and better performance.', 'translate-words'),
            '<a href="' . esc_url(admin_url('plugin-install.php?s=loco%2520translate&tab=search&type=term'))  . '" target="_blank">' . esc_html__('Loco Translate', 'translate-words') . '</a>'
        ) . '</p>';
        $message .= '<p style="margin-top: 15px;">';
        $message .= '<a href="' . esc_url('https://linguator.com/documentation/?utm_source=twlmat_plugin&utm_medium=inside&utm_campaign=docs&utm_content=tw_notice') . '" target="_blank" class="button button-secondary" style="margin-right: 10px;">' . esc_html__('Learn About Linguator', 'translate-words') . '</a>';
        $message .= '</p>';

        // Display notice using WordPress standards
        printf(
            '<div class="notice notice-warning is-dismissible tww-deprecation-notice" style="padding: 15px;">%s</div>',
            wp_kses_post($message)
        );
    }

    add_action('admin_notices', 'linguator_display_deprecation_notice');

    /**
     * Handle AJAX request to dismiss deprecation notice.
     *
     * @return void
     */
    function linguator_dismiss_deprecation_notice()
    {
        check_ajax_referer('tww_dismiss_notice', 'nonce');

        // Check if user has capability to manage options (admin only)
        if (! current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }

        // Store dismissal with timestamp for tracking purposes
        $dismissal_data = [
            'dismissed'    => true,
            'timestamp'    => current_time('timestamp'),
            'dismissed_by' => get_current_user_id(),
        ];

        update_option('tww_deprecation_notice_dismissed', $dismissal_data);

        wp_send_json_success(['message' => 'Notice dismissed successfully']);
    }

    add_action('wp_ajax_tww_dismiss_deprecation_notice', 'linguator_dismiss_deprecation_notice');

    /**
     * Display the settings page.
     *
     * We don't need to generate a nonce because we're using settings fields which
     * does this for us.
     *
     * @return void
     */
    function linguator_setting_page()
    {

        $translations = get_option(LMAT_TRANSLATIONS_LINES);

        // Check if Loco Translate is active
        if (! function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

    ?>
	<style>
	.translation-table {
		margin-top: 15px;
	}
	</style>
	<div class="wrap">

		<h1 class="wp-heading-inline"><?php esc_html_e('Translate Words', 'translate-words'); ?></h1>

		<form method="POST" action="options.php">

	<?php
		do_settings_sections(LMAT_TRANSLATIONS);
		settings_fields(LMAT_TRANSLATIONS);
	?>
		<table class="translation-table wp-list-table widefat fixed striped">
			<thead>
				<tr valign="top">
					<th scope="column" class="column-current"><?php esc_html_e('Current', 'translate-words'); ?></th>
					<th scope="column" class="column-new"><?php esc_html_e('New', 'translate-words'); ?></th>
					<th scope="column"></th>
				</tr>
			</thead>
			<tbody id="rowsTranslations">
	<?php
		if (! empty($translations)) {
				foreach ($translations as $key => $value) {

					$original  = isset($value['original']) ? $value['original'] : '';
					$overwrite = isset($value['overwrite']) ? $value['overwrite'] : '';

				?>
					<tr valign="top" id="row_id_<?php echo esc_attr($key); ?>_translate">
						<td style="white-space: nowrap">
							<input type="text" style="width:100%;" name="<?php echo esc_attr(LMAT_TRANSLATIONS_LINES); ?>[original][]" value="<?php echo esc_textarea($original); ?>" />
							&rarr;
						</td>
						<td>
							<input type="text" style="width:100%;" name="<?php echo esc_attr(LMAT_TRANSLATIONS_LINES); ?>[overwrite][]" value="<?php echo esc_textarea($value['overwrite']); ?>" />
						</td>
						<td class="action">
							<span class="trash">
								<a
									href="#"
									class="submitdelete submitDeleteTranslation"
									aria-lable="<?php esc_attr_e('Remove this translation', 'translate-words'); ?>"
									id="row_id_<?php echo esc_attr($key); ?>"><?php esc_html_e('Remove', 'translate-words'); ?></span>
							</span>
						</td>
					</tr>
	<?php
		}
			}

			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Template constant contains safe HTML structure with pre-defined input fields
			echo LMAT_NEW_STRING_TEMPLATE;

		?>

				</tbody>
			</table>

			<p class="submit">
				<button class="button-secondary" style="margin:5px 0;" id="addTranslation"><?php esc_html_e('Add Translation +', 'translate-words'); ?></button>
				<input type="submit" class="button-primary" style="margin:5px 0;" value="<?php esc_attr_e('Save', 'translate-words'); ?>" />
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
    function lmat_translate_gutenberg_string()
    {

        // Output translations as json array.
        $overrides = get_option(LMAT_TRANSLATIONS_LINES);

        if (! is_array($overrides)) {
            return;
        }

        printf(
            '<script>var lmat_translations = %s;</script>',
            wp_json_encode($overrides)
        );

        // Enqueue editor scripts.
        wp_enqueue_script(
            'LMAT_TRANSLATIONS_JS',
            LMAT_PLUGINS_DIR . 'js/gb_i18n.js',
            ['jquery'],
            '1.0.0',
            true
        );

    }

    add_filter('admin_head', 'lmat_translate_gutenberg_string');

