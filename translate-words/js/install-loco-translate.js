jQuery(document).ready(function ($) {
    /**
     * Install Loco Translate Plugin
     */
    $(document).on('click', '.loco-install-plugin', function (e) {
        e.preventDefault();

        let button = $(this);
        let plugin = button.data('plugin');
        let nonce = button.data('nonce');
        
        if (!plugin) return;

        button.text('Installing...').prop('disabled', true);

        $.post(ajaxurl, {
            action: 'tww_install_loco_translate',
            slug: plugin,
            _wpnonce: nonce
        }, function (response) {
            if (response.success) {
                // Redirect to the URL provided in the response, or plugins page by default
                let redirectUrl = response.data && response.data.redirect_url 
                    ? response.data.redirect_url 
                    : '/wp-admin/plugins.php';
                window.location.href = redirectUrl;
            } else {
                alert('Failed to install Loco Translate. Please try again.');
            }
        });
    });

});