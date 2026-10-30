const { __, sprintf } = wp.i18n;

/**
 * AI translation popup shown on a new translation whose source page was built with Elementor.
 * "Translate Now" stores a one-time intent flag and opens the page in Elementor, where the
 * AI provider modal starts automatically.
 */
const lmatElementorConfirmBox = {
    init: function() {
        this.pageTitleEvent = false;
        if (window.lmatElementorConfirmBoxData) {
            this.createConfirmBox();
        }
    },

    createConfirmBox: function() {
        const data = window.lmatElementorConfirmBoxData;
        const wand = data.magic_wand_url || '';
        const confirmBox = jQuery(`<div class="lmat-elementor-translate-confirm-box modal-container" style="display:flex">
            <div class="modal-content">
                <div class="modal-header">
                    <div class="lmat-modal-header-left">
                        <img src="${wand}" style="width:20px;height:20px;margin-right:5px;filter:brightness(0) invert(0);" alt="${__("AI", "translate-words")}">
                        <h3>${__("AI Translation", "translate-words")}</h3>
                    </div>
                    <span class="lmat-modal-close dashicons dashicons-no-alt"></span>
                </div>
                <div class="lmat-modal-body">
                    <div class="lmat-main-section">
                        <p>${sprintf(__("Use AI to translate this page from %1$s to %2$s inside %3$s while keeping the same layout and design.", "translate-words"), data.sourceLangName, data.targetLangName, '<strong>Elementor</strong>')}</p>
                        <button type="button" class="lmat-translate-button button" data-value="yes">
                            <img src="${wand}" style="width:20px;height:20px;margin-right:5px;filter:brightness(0) invert(1);" alt="${__("AI", "translate-words")}">
                            ${__("Translate Now", "translate-words")}
                        </button>
                    </div>
                </div>
                <div class="modal-footer-notice">
                    <span class="dashicons dashicons-warning"></span>
                    <p><em>${__("Note: close this popup if you do not want AI translation.", "translate-words")}</em></p>
                </div>
            </div>
        </div>`);

        confirmBox.appendTo(jQuery('body'));

        confirmBox.find('button.lmat-translate-button').on('click', (e) => { this.confirmTranslation(e); });
        confirmBox.find('span.lmat-modal-close').on('click', (e) => { e.preventDefault(); this.closeConfirmBox(); });
    },

    closeConfirmBox: function() {
        this.setPageTitle();
        jQuery('.lmat-elementor-translate-confirm-box.modal-container').remove();
    },

    // Store the translate intent, then let Elementor's own button save the draft and open the editor.
    confirmTranslation: function(e) {
        e.preventDefault();
        this.setPageTitle();

        const button = e.currentTarget;
        if (button && (button.disabled || button.classList.contains('loading'))) {
            return;
        }

        const postId = window.lmatElementorConfirmBoxData.postId;
        const targetLangSlug = window.lmatElementorConfirmBoxData.targetLangSlug;

        if (!postId || !targetLangSlug) {
            return;
        }

        try {
            let data = {};
            const oldData = localStorage.getItem('lmatElementorConfirmBox');
            if (oldData && 'string' === typeof oldData && '' !== oldData) {
                data = JSON.parse(oldData) || {};
            }
            data[postId + '_' + targetLangSlug] = true;
            localStorage.setItem('lmatElementorConfirmBox', JSON.stringify(data));
        } catch (err) {}

        this.loadingText(button);

        const elementorButton = document.getElementById('elementor-editor-button');
        const elementorEditModeButton = document.getElementById('elementor-edit-mode-button');

        if (elementorEditModeButton) {
            elementorEditModeButton.click();
        } else if (elementorButton) {
            elementorButton.click();
        }
        // Keep the popup (with loading state) open until the browser navigates to Elementor.
    },

    loadingText: function(ele) {
        if (!ele) {
            return;
        }
        ele.disabled = true;
        ele.classList.add('loading');
        ele.innerHTML = `${__("Loading", "translate-words")}<span class="dot" style="--i: 0"></span><span class="dot" style="--i: 1"></span><span class="dot" style="--i: 2"></span>`;
    },

    // Classic editor: make sure the draft is saved (with a title) before redirecting to Elementor.
    setPageTitle: function() {
        if (window.lmatElementorConfirmBoxData.editorType !== 'classic' || this.pageTitleEvent) {
            return;
        }
        this.pageTitleEvent = true;

        document.querySelectorAll('#elementor-editor-button, #elementor-edit-mode-button').forEach((button) => {
            button.addEventListener('click', (e) => {
                e.preventDefault();
                if (window.wp && window.elementorAdmin && window.elementorAdmin.getDefaultElements) {
                    const defaultElements = window.elementorAdmin.getDefaultElements();
                    const $goToEditLink = defaultElements ? defaultElements.$goToEditLink : null;
                    if ($goToEditLink) {
                        const $wpTitle = jQuery('#title');
                        if (!$wpTitle.val()) {
                            $wpTitle.val('Elementor #' + jQuery('#post_ID').val());
                        }
                        if (wp.autosave) {
                            wp.autosave.server.triggerSave();
                        }
                        jQuery(document).on('heartbeat-tick.autosave', function() {
                            window.elementorCommon.elements.$window.off('beforeunload.edit-post');
                            location.href = $goToEditLink.attr('href');
                        });
                    }
                }
            });
        });
    }
};

jQuery(document).ready(function() {
    lmatElementorConfirmBox.init();
});