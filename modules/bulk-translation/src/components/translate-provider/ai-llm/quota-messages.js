import { __ } from '@wordpress/i18n';

/** Quota recovery instructions for account-based and API-key providers. */
export function getQuotaMessages(providerId) {
    if (providerId === 'chatgpt') {
        return {
            intro: __('To continue, please check your ChatGPT plan and upgrade it or wait for your usage limit to reset.', 'translate-words'),
            retry: __('Click "Translate" after upgrading your ChatGPT plan or once your usage limit resets to try again.', 'translate-words'),
            retryRemaining: __('Click "Translate" after upgrading your ChatGPT plan or once your usage limit resets to re-translate the remaining strings.', 'translate-words'),
        };
    }

    return {
        intro: __('To continue, please check your plan details and update your API key.', 'translate-words'),
        retry: __('Click "Translate" after updating your API key to try again.', 'translate-words'),
        retryRemaining: __('Click "Translate" after updating your API key to re-translate the remaining strings.', 'translate-words'),
    };
}
