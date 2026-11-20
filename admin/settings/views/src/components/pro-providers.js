import { __ } from '@wordpress/i18n';

// Linguator Pro pricing page, linked from the locked Pro providers.
const PRO_PRODUCT_URL = 'https://linguator.com/pricing/';

/**
 * License tab URL while Linguator Pro is active but its license is not, '' otherwise.
 *
 * @return {string}
 */
const getProLicenseUrl = () =>
    window?.lmat_settings?.pro_license_url ||
    window?.lmat_setup?.pro_license_url ||
    window?.lmatPageTranslationGlobal?.pro_license_url ||
    window?.lmatBulkTranslationGlobal?.pro_license_url ||
    '';

/**
 * Link for a locked provider: the License tab when Pro only needs its license activated, else the Pro product page.
 *
 * @param {string} providerKey Provider key.
 * @param {string} screen      Where the link is shown: 'settings' or 'wizard'.
 * @return {string}
 */
export const getProProviderUrl = (providerKey, screen = 'settings') =>
    getProLicenseUrl() ||
    `${PRO_PRODUCT_URL}?utm_source=twlmat_plugin&utm_medium=inside&utm_campaign=get_pro&utm_content=${screen}_provider_${providerKey}`;

/**
 * Label of the locked provider link, matching getProProviderUrl().
 *
 * @return {string}
 */
export const getProActionLabel = () =>
    getProLicenseUrl() ? __('Activate License', 'translate-words') : __('Get Pro', 'translate-words');

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
    {
        key: 'openai',
        label: 'OpenAI',
        description: __('Translate with OpenAI GPT models using your own OpenAI API key. Usage is billed to your OpenAI account.', 'translate-words'),
        docsUrl: 'https://platform.openai.com/api-keys',
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
