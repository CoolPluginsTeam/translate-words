import { __ } from '@wordpress/i18n';

// Linguator Pro pricing page, linked from the locked Pro providers.
const PRO_PRODUCT_URL = 'https://linguator.com/pricing/';

/**
 * Link to the Pro product page for a locked provider.
 *
 * @param {string} providerKey Provider key.
 * @param {string} screen      Where the link is shown: 'settings' or 'wizard'.
 * @return {string}
 */
export const getProProviderUrl = (providerKey, screen = 'settings') =>
    `${PRO_PRODUCT_URL}?utm_source=twlmat_plugin&utm_medium=inside&utm_campaign=get_pro&utm_content=${screen}_provider_${providerKey}`;

/**
 * Providers added by Linguator Pro. Without Pro they are listed locked, linking to the Pro page.
 *
 * @return {Array<{key: string, label: string, description: string, docsUrl: string}>}
 */
export const getProProviders = () => [
    {
        key: 'chatgpt',
        label: 'ChatGPT',
        description: __('Translate with your ChatGPT account. No API key needed: usage counts against your ChatGPT plan.', 'translate-words'),
        docsUrl: 'https://linguator.com/docs/automatic-translation-via-chatgpt/',
    },
];

/**
 * Pro providers to show locked: none while Pro is active, and only those Pro has not registered.
 *
 * @param {Object}  localized         Localized page data (lmat_settings or lmat_setup).
 * @param {Object}  externalProviders Providers registered by other plugins, keyed by provider key.
 * @return {Array<{key: string, label: string, description: string, docsUrl: string, iconUrl: string}>}
 */
export const getLockedProProviders = (localized, externalProviders = {}) => {
    if (!localized || localized.pro_active) {
        return [];
    }

    return getProProviders()
        .filter((pro) => !externalProviders[pro.key])
        .map((pro) => ({ ...pro, iconUrl: localized?.pro_provider_icons?.[pro.key] || '' }));
};
