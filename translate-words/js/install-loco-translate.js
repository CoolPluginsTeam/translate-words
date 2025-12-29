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
                window.location.reload();
            } else {
                alert('Failed to install Loco Translate. Please try again.');
            }
        });
    });

});