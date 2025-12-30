<?php
    /**
     * Admin page content.
     *
     * @package tww
     */

    // Mark this file as deprecated - only on specific admin pages
    if ( 
        is_admin() && 
        isset( $_GET['page'] ) && 
        ( $_GET['page'] === 'tww_settings' || $_GET['page'] === 'lmat_settings' )
    ) {
        _deprecated_file(
            basename(__FILE__),
            '2.0.0',
            'Linguator functionality (use the Linguator features instead of Translate Words)'
        );
    }

    /**
     * A temporary variable since we don't seem to be able to use function calls in
     * HEREDOC.
     */
    $tww_translation_lines = esc_attr(TWW_TRANSLATIONS_LINES);

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
    function tww_add_admin_menu()
    {

        // Check if Loco Translate is active
        if (! function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $translations = get_option(TWW_TRANSLATIONS_LINES);

        // If Loco Translate is active and there's no data, don't show the menu
        if (is_plugin_active('loco-translate/loco.php') && empty($translations)) {
            return;
        }

        add_options_page(
            esc_html__('Translate Words', 'translate-words'),
            esc_html__('Translate Words', 'translate-words'),
            'administrator',
            TWW_PAGE,
            'tww_setting_page'
        );

    }

    add_action('admin_menu', 'tww_add_admin_menu');

    /**
     * Enqueue Admin Scripts.
     *
     * @return void
     */
    function tww_admin_enqueue_scripts()
    {

        global $pagenow;

        if ('options-general.php' !== $pagenow) {
            return;
        }

        if (! isset($_REQUEST['page'])) {
            return;
        }

        if (isset($_REQUEST['page']) && 'tww_settings' !== $_REQUEST['page']) {
            return;
        }

        wp_enqueue_script(
            'TWW_TRANSLATIONS_ADMIN',
            TWW_PLUGINS_DIR . 'js/main.js',
            ['jquery'],
            '1.0.1',
            false
        );

        wp_localize_script(
            'TWW_TRANSLATIONS_ADMIN',
            'tww_properties',
            [
                'template'      => TWW_NEW_STRING_TEMPLATE,
                'ajax_url'      => admin_url('admin-ajax.php'),
                'dismiss_nonce' => wp_create_nonce('tww_dismiss_notice'),
            ]
        );

        // Add inline script for notice dismissal
        wp_add_inline_script(
            'TWW_TRANSLATIONS_ADMIN',
            "
		jQuery(document).ready(function($) {
			$(document).on('click', '.tww-deprecation-notice .notice-dismiss', function() {
				$.ajax({
					url: tww_properties.ajax_url,
					type: 'POST',
					data: {
						action: 'tww_dismiss_deprecation_notice',
						nonce: tww_properties.dismiss_nonce
					}
				});
			});
		});
		"
        );

    }

    add_action('admin_enqueue_scripts', 'tww_admin_enqueue_scripts');

    /**
     * Initialize the setting.
     *
     * @return void
     */
    function tww_settings_init()
    {

        register_setting(
            TWW_TRANSLATIONS,
            TWW_TRANSLATIONS_LINES,
            [
                'sanitize_callback' => 'tww_validate_translations_and_save',
                'type'              => 'array',
                'default'           => '',
            ]
        );

    }

    add_action('admin_init', 'tww_settings_init');

    /**
     * Validate the translations and save the settings.
     *
     * @param {array} $strings The translations strings to save.
     * @return {void}
     */
    function tww_validate_translations_and_save($strings)
    {

        $update_translations = [];

        if (
            ! empty($strings['original']) &&
            count($strings['original']) > 0
        ) {

            foreach ($strings['original'] as $key => $value) {

                if (! empty($value)) {
                    $update_translations[] = [
                        'original'  => $value,
                        'overwrite' => $strings['overwrite'][$key],
                    ];
                }
            }

        }
        // Check if Loco Translate is active and all data was removed
        if (empty($update_translations)) {
            if (! function_exists('is_plugin_active')) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }

            // If Loco Translate is active and all data is removed, redirect to Linguator settings
            if (is_plugin_active('loco-translate/loco.php')) {
                // Add a filter to change the redirect URL after settings save
                add_filter('wp_redirect', function ($location) {
                    // Check if this is a redirect from options.php (settings save)
                    if (strpos($location, 'settings-updated=true') !== false) {
                        // Redirect to Linguator settings instead
                        return admin_url('admin.php?page=lmat_settings');
                    }
                    return $location;
                }, 10, 1);
            }
        }
        return $update_translations;
    }

    /**
     * Display deprecation notice for Translate Words on the settings page.
     *
     * @return void
     */
    function tww_display_deprecation_notice()
    {
        // Only show on Translate Words settings page
        $screen = get_current_screen();
        if (! $screen || 'settings_page_' . TWW_PAGE !== $screen->id) {
            return;
        }
        // Don't show notice if Loco Translate is active
        if (! function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        if (is_plugin_active('loco-translate/loco.php')) {
            return;
        }

        // Check if notice has been dismissed site-wide
        if (get_option('tww_deprecation_notice_dismissed')) {
            return;
        }

        // Build notice message
        $message = '<h3 style="margin-top: 0;">' . esc_html__('⚠️ Important Update: Translate Words is Evolving to a New AI Multilingual Solution', 'translate-words') . '</h3>';
        $message .= '<p>' . sprintf(
            __('We are working on a new and more powerful %1$s solution called %2$s, and Translate Words will gradually transition to this new plugin.', 'translate-words'),
            '<strong>AI Multilingual</strong>',
            '<strong>Linguator</strong>'
        ) . '</p>';
        $message .= '<p><strong>' . esc_html__('The current Translate Words functionality will be deprecated and discontinued in approximately 31st December 2026.', 'translate-words') . '</strong><br>';
        $message .= esc_html__('Until then, you can continue using this plugin safely.', 'translate-words') . '</p>';
        $message .= '<p>' . sprintf(
            esc_html__('If you want to keep using a similar manual string translation workflow, please migrate to %s, which offers enhanced features and better performance.', 'translate-words'),
            '<a href="' . esc_url(admin_url('plugin-install.php?s=loco%2520translate&tab=search&type=term'))  . '" target="_blank">' . esc_html__('Loco Translate', 'linguator-multilingual-ai-translation') . '</a>'
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

    add_action('admin_notices', 'tww_display_deprecation_notice');

    /**
     * Handle AJAX request to dismiss deprecation notice.
     *
     * @return void
     */
    function tww_dismiss_deprecation_notice()
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

    add_action('wp_ajax_tww_dismiss_deprecation_notice', 'tww_dismiss_deprecation_notice');

    /**
     * Display the settings page.
     *
     * We don't need to generate a nonce because we're using settings fields which
     * does this for us.
     *
     * @return void
     */
    function tww_setting_page()
    {

        $translations = get_option(TWW_TRANSLATIONS_LINES);

        // Check if Loco Translate is active
        if (! function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        // If Loco Translate is active and there's no data, redirect to settings page
        if (is_plugin_active('loco-translate/loco.php') && empty($translations)) {
            wp_safe_redirect(admin_url('options-general.php'));
            exit;
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
		do_settings_sections(TWW_TRANSLATIONS);
		settings_fields(TWW_TRANSLATIONS);
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
							<input type="text" style="width:100%;" name="<?php echo esc_attr(TWW_TRANSLATIONS_LINES); ?>[original][]" value="<?php echo esc_textarea($original); ?>" />
							&rarr;
						</td>
						<td>
							<input type="text" style="width:100%;" name="<?php echo esc_attr(TWW_TRANSLATIONS_LINES); ?>[overwrite][]" value="<?php echo esc_textarea($value['overwrite']); ?>" />
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

			echo TWW_NEW_STRING_TEMPLATE;

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
    function tww_translate_gutenberg_string()
    {

        // Output translations as json array.
        $overrides = get_option(TWW_TRANSLATIONS_LINES);

        if (! is_array($overrides)) {
            return;
        }

        printf(
            '<script>var tww_translations = %s;</script>',
            wp_json_encode($overrides)
        );

        // Enqueue editor scripts.
        wp_enqueue_script(
            'TWW_TRANSLATIONS_JS',
            TWW_PLUGINS_DIR . 'js/gb_i18n.js',
            ['jquery'],
            '1.0.0',
            true
        );

    }

add_filter('admin_head', 'tww_translate_gutenberg_string');
