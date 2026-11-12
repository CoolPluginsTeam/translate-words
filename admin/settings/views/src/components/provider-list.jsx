import React, { useEffect, useRef, useState } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { ChromeIcon } from '../../../../../assets/js/src/icons/chrome';
import { EdgeIcon } from '../../../../../assets/js/src/icons/edge';
import { GoogleIcon } from '../../../../../assets/js/src/icons/google';
import { GeminiIcon } from '../../../../../assets/js/src/icons/gemini';
import ExternalProviderPanel from './external-provider-panel.jsx';
import { getLockedProProviders, getProProviderUrl } from './pro-providers.js';

const DOCS_UTM = '?utm_source=twlmat_plugin&utm_medium=inside&utm_campaign=docs&utm_content=settings_provider';
const getChromeAiSetupData = () => window?.lmat_settings?.chrome_ai_setup || {};

const getAvailableLanguages = () => Object.entries(getChromeAiSetupData().all_languages || {}).map(([code, language]) => ({
    code,
    name: language?.name || code,
}));

/**
 * Whether the browser can run this built-in AI provider and, if so, whether it is set up.
 *
 * @param {'chrome'|'edge'} type        Built-in AI provider.
 * @param {string}          browserType Chrome | Edge | Other.
 * @return {'unsupported'|'checking'|'ready'|'setup'}
 */
const useBuiltInAiStatus = (type, browserType) => {
    const requiredBrowser = 'edge' === type ? 'Edge' : 'Chrome';
    const unsupported = requiredBrowser !== browserType;
    const [status, setStatus] = useState(unsupported ? 'unsupported' : 'checking');

    useEffect(() => {
        if (unsupported) {
            setStatus('unsupported');
            return undefined;
        }

        let cancelled = false;
        const Framework = window.ChromeAISetupFramework;

        if (!Framework) {
            setStatus('setup');
            return undefined;
        }

        setStatus('checking');
        Framework.checkSystemAvailability({
            sourceLanguage: getChromeAiSetupData().source_language || 'en',
            availableLanguages: getAvailableLanguages(),
            // A phone-sized device toolbar still runs desktop Chrome, so follow the real API check.
            bypassDesktopCheck: true,
        })
            .then((result) => {
                if (!cancelled) {
                    setStatus(result.hasError ? 'setup' : 'ready');
                }
            })
            .catch(() => {
                if (!cancelled) {
                    setStatus('setup');
                }
            });

        return () => {
            cancelled = true;
        };
    }, [unsupported]);

    return status;
};

/**
 * Chrome / Edge AI setup card (detects what the browser needs and offers a live preview).
 */
export const ChromeAiSetupCard = ({ browserType, chromeEnabled, edgeEnabled }) => {
    const containerRef = useRef(null);
    let forceProvider = 'chrome';

    if ('Edge' === browserType) {
        forceProvider = 'edge';
    } else if ('Other' === browserType && !chromeEnabled && edgeEnabled) {
        forceProvider = 'edge';
    }

    useEffect(() => {
        const Framework = window.ChromeAISetupFramework;
        const container = containerRef.current;

        if (!Framework || !container) {
            return undefined;
        }

        const data = getChromeAiSetupData();

        new Framework(container, {
            sourceLanguage: data.source_language || 'en',
            sourceLanguageLabel: data.source_language_label || 'English',
            availableLanguages: getAvailableLanguages(),
            chromeIconUrl: data.chrome_icon_url || '',
            edgeIconUrl: data.edge_icon_url || '',
            texts: data.texts || {},
            alternativeUrl: data.alternative_url || '',
            defaultProvider: forceProvider,
            forceProvider,
            primaryBtnClass: 'lmat-engine-btn primary',
            secondaryBtnClass: 'lmat-engine-btn',
        });

        return () => {
            container.innerHTML = '';
        };
    }, [forceProvider]);

    return <div className="lmat-chrome-ai-setup" ref={containerRef} />;
};

const ProviderRow = ({
    icon,
    name,
    docsUrl,
    enabled,
    onToggle,
    isDefault,
    onDefault,
    status,
    statusLabel,
    unsupportedMessage,
    actionLabel,
    onAction,
    actionHref,
    extra,
    defaultDisabledReason,
    locked = false,
}) => {
    const unsupported = 'unsupported' === status;
    const defaultDisabled = !unsupported && Boolean(defaultDisabledReason);
    const rowClasses = ['lmat-engine-row'];

    if (isDefault) {
        rowClasses.push('is-default');
    }

    if (unsupported) {
        rowClasses.push('is-unsupported');
    }

    if (locked) {
        rowClasses.push('is-locked');
    }

    const statusClass = {
        setup: 'is-setup',
        'missing-key': 'is-setup',
        checking: 'is-checking',
        unsupported: 'is-unsupported',
        pro: 'is-pro',
    }[status] || '';

    return (
        <li className={rowClasses.join(' ')}>
            <div className="lmat-engine-meta">
                <span className="lmat-engine-logo" aria-hidden="true">{icon}</span>
                <span className="lmat-engine-name">{name}</span>
                <span className={`lmat-engine-status ${statusClass}`.trim()}>{statusLabel}</span>
            </div>

            {unsupported && unsupportedMessage && (
                <p className="lmat-engine-unsupported-msg">{unsupportedMessage}</p>
            )}

            <label
                className={`lmat-engine-default${defaultDisabled ? ' is-disabled' : ''}`}
                title={defaultDisabled ? defaultDisabledReason : ''}
            >
                <input
                    type="radio"
                    name="lmat-default-provider"
                    className="lmat-engine-default-input"
                    checked={isDefault}
                    disabled={defaultDisabled}
                    onChange={onDefault}
                />
                <span className="lmat-engine-default-mark" aria-hidden="true"></span>
                <span className="lmat-engine-default-text">{__('Set as default', 'translate-words')}</span>
                <span className="lmat-engine-default-active">{__('Default provider', 'translate-words')}</span>
            </label>

            <div className="lmat-engine-actions">
                {docsUrl ? (
                    <a className="lmat-engine-docs" href={docsUrl} target="_blank" rel="noopener noreferrer">
                        {__('Docs', 'translate-words')}
                    </a>
                ) : null}
                <span className="lmat-engine-action-slot">
                    {actionLabel && actionHref && (
                        <a className="lmat-engine-btn primary" href={actionHref} target="_blank" rel="noopener noreferrer">
                            {actionLabel}
                        </a>
                    )}
                    {actionLabel && !actionHref && (
                        <button type="button" className="lmat-engine-btn primary" onClick={onAction}>
                            {actionLabel}
                        </button>
                    )}
                </span>
                <label className="lmat-provider-switch">
                    <input
                        type="checkbox"
                        className="lmat-input-toggle"
                        checked={enabled}
                        disabled={isDefault || locked}
                        onChange={onToggle}
                        title={isDefault ? __('The default provider stays enabled. Pick another default to turn this off.', 'translate-words') : ''}
                    />
                    <span className="lmat-switch-slider"></span>
                    <span className="screen-reader-text">{sprintf(__('Enable %s', 'translate-words'), name)}</span>
                </label>
            </div>

            {extra && <div className="lmat-engine-extra">{extra}</div>}
        </li>
    );
};

/**
 * Provider list of the AI Translation tab.
 *
 * @param {Object} props
 * @param {string} props.browserType       Chrome | Edge | Other.
 * @param {string} props.defaultProvider   Saved key of the default provider ('' for none).
 * @param {Object} props.providers         Per provider key: { enabled, setEnabled }.
 * @param {boolean} props.geminiAvailable  Whether the WordPress AI client allows Gemini.
 * @param {boolean} props.geminiConfigured Whether a Gemini API key is saved.
 * @param {Function} props.onDefaultChange Receives the provider key picked as default.
 * @param {*} props.geminiExtra            Content rendered under the Gemini row (API key form).
 * @param {Object} props.externalProviders Providers added by other plugins, keyed by provider key (see lmat_external_ai_providers).
 * @param {Object} props.externalConfigured Per external key: whether it is ready to translate.
 * @param {Function} props.onExternalStatus Receives (key, configured) when an account connects or disconnects.
 */
const ProviderList = ({
    browserType,
    defaultProvider,
    providers,
    geminiAvailable,
    geminiConfigured,
    onDefaultChange,
    geminiExtra,
    externalProviders = {},
    externalConfigured = {},
    onExternalStatus = () => {},
}) => {
    const chromeStatus = useBuiltInAiStatus('chrome', browserType);
    const edgeStatus = useBuiltInAiStatus('edge', browserType);
    const showChromeRow = 'Edge' !== browserType;
    const showEdgeRow = 'Chrome' !== browserType;
    const chromeEnabled = Boolean(providers.chrome_local_ai.enabled);
    const edgeEnabled = Boolean(providers.edge_local_ai.enabled);
    const showSetupCard = (showChromeRow && 'Chrome' === browserType && chromeEnabled)
        || (showEdgeRow && 'Edge' === browserType && edgeEnabled)
        || ('Other' === browserType && (chromeEnabled || edgeEnabled));

    const scrollToSetupCard = () => {
        const card = document.querySelector('.lmat-chrome-ai-setup');

        if (card && card.scrollIntoView) {
            card.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    };

    const scrollToExternalCard = (key) => {
        const card = document.querySelector(`.lmat-external-setup-card[data-provider="${key}"]`);

        if (card && card.scrollIntoView) {
            card.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    };

    const builtInLabels = {
        ready: __('Ready', 'translate-words'),
        setup: __('Not Configured', 'translate-words'),
        checking: __('Checking...', 'translate-words'),
        unsupported: __('Not supported', 'translate-words'),
    };

    const unsupportedMessage = (providerName, browserName) => sprintf(
        /* translators: 1: translation provider name, 2: required browser name */
        __('%1$s Translation provider is not supported in your current browser. Please use %2$s browser to use this translation provider.', 'translate-words'),
        providerName,
        browserName
    );

    const builtInDefaultReason = (status) => {
        if ('ready' === status) {
            return '';
        }

        return __('Configure this provider before setting it as default.', 'translate-words');
    };

    const toggle = (key) => () => providers[key].setEnabled(!providers[key].enabled);

    return (
        <div className="lmat-engines">
            {showSetupCard && (
                <ChromeAiSetupCard browserType={browserType} chromeEnabled={chromeEnabled} edgeEnabled={edgeEnabled} />
            )}

            {/* Setup cards of external providers (such as an account connection), shown while the provider is on. */}
            {Object.entries(externalProviders).map(([key, external]) => (
                providers[key]?.enabled && external?.settings_panel ? (
                    <div key={key} className="lmat-external-setup-card" data-provider={key}>
                        <ExternalProviderPanel
                            providerKey={key}
                            onStatusChange={(isConfigured) => onExternalStatus(key, isConfigured)}
                        />
                    </div>
                ) : null
            ))}

            <div className="lmat-engine-note">
                <span className="lmat-engine-note-icon" aria-hidden="true">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="12" cy="12" r="10" fill="currentColor" />
                        <path d="M12 11v6" stroke="#fff" strokeWidth="2" strokeLinecap="round" />
                        <circle cx="12" cy="7.8" r="1.2" fill="#fff" />
                    </svg>
                </span>
                <p>{__('Select your default translation provider. It is pre-selected when the translation popup opens, and you can switch providers anytime from the popup.', 'translate-words')}</p>
            </div>

            <ul className="lmat-engine-list">
                <ProviderRow
                    icon={<GoogleIcon className="icon-size" />}
                    name={__('Google Translate', 'translate-words')}
                    docsUrl={`https://linguator.com/docs/automatic-translation-via-google-translate-widget/${DOCS_UTM}_google`}
                    enabled={Boolean(providers.google.enabled)}
                    onToggle={toggle('google')}
                    isDefault={'google' === defaultProvider}
                    onDefault={() => onDefaultChange('google')}
                    status="ready"
                    statusLabel={builtInLabels.ready}
                />

                {showChromeRow && (
                    <ProviderRow
                        icon={<ChromeIcon className="icon-size" />}
                        name={__('Chrome Built-in AI', 'translate-words')}
                        docsUrl={`https://linguator.com/docs/automatic-translation-via-chrome-ai/${DOCS_UTM}_chrome`}
                        enabled={chromeEnabled}
                        onToggle={toggle('chrome_local_ai')}
                        isDefault={'chrome_local_ai' === defaultProvider}
                        onDefault={() => onDefaultChange('chrome_local_ai')}
                        status={chromeStatus}
                        statusLabel={builtInLabels[chromeStatus]}
                        unsupportedMessage={unsupportedMessage(__('Chrome Built-in AI', 'translate-words'), 'Chrome')}
                        defaultDisabledReason={builtInDefaultReason(chromeStatus)}
                        actionLabel={'setup' === chromeStatus && chromeEnabled ? __('Configure', 'translate-words') : ''}
                        onAction={scrollToSetupCard}
                    />
                )}

                {showEdgeRow && (
                    <ProviderRow
                        icon={<EdgeIcon className="icon-size" />}
                        name={__('Edge Built-in AI', 'translate-words')}
                        docsUrl="https://microsoftedge.github.io/Demos/built-in-ai/playgrounds/translator-api/"
                        enabled={edgeEnabled}
                        onToggle={toggle('edge_local_ai')}
                        isDefault={'edge_local_ai' === defaultProvider}
                        onDefault={() => onDefaultChange('edge_local_ai')}
                        status={edgeStatus}
                        statusLabel={builtInLabels[edgeStatus]}
                        unsupportedMessage={unsupportedMessage(__('Edge Built-in AI', 'translate-words'), 'Edge')}
                        defaultDisabledReason={builtInDefaultReason(edgeStatus)}
                        actionLabel={'setup' === edgeStatus && edgeEnabled ? __('Configure', 'translate-words') : ''}
                        onAction={scrollToSetupCard}
                    />
                )}

                {geminiAvailable && (
                    <ProviderRow
                        icon={<GeminiIcon className="icon-size" />}
                        name={__('Google Gemini', 'translate-words')}
                        docsUrl={`https://linguator.com/docs/automatic-translation-via-googe-gemini/${DOCS_UTM}_gemini`}
                        enabled={Boolean(providers.gemini.enabled)}
                        onToggle={toggle('gemini')}
                        isDefault={'gemini' === defaultProvider}
                        onDefault={() => onDefaultChange('gemini')}
                        status={geminiConfigured ? 'ready' : 'missing-key'}
                        statusLabel={geminiConfigured ? __('Ready', 'translate-words') : __('Missing API Key', 'translate-words')}
                        defaultDisabledReason={geminiConfigured ? '' : __('Add the API key before setting this provider as default.', 'translate-words')}
                        actionLabel={geminiConfigured ? '' : __('Add API Key', 'translate-words')}
                        onAction={() => {
                            if (!providers.gemini.enabled) {
                                providers.gemini.setEnabled(true);
                            }
                        }}
                        extra={providers.gemini.enabled ? geminiExtra : null}
                    />
                )}

                {Object.entries(externalProviders).map(([key, external]) => {
                    const state = providers[key]
                    if (!state) {
                        return null
                    }
                    const configured = Boolean(externalConfigured[key])
                    const hasPanel = Boolean(external?.settings_panel)

                    return (
                        <ProviderRow
                            key={key}
                            icon={external.icon_url ? <img src={external.icon_url} alt="" className="icon-size" /> : null}
                            name={external.label}
                            docsUrl={external.docs_url || ''}
                            enabled={Boolean(state.enabled)}
                            onToggle={toggle(key)}
                            isDefault={key === defaultProvider}
                            onDefault={() => onDefaultChange(key)}
                            status={configured ? 'ready' : 'setup'}
                            statusLabel={configured
                                ? __('Ready', 'translate-words')
                                // The page-load label described a working provider; after a disconnect it is out of date.
                                : (!external.configured && external.status_label ? external.status_label : __('Not connected', 'translate-words'))}
                            defaultDisabledReason={configured ? '' : sprintf(__('Set up %s before making it the default provider.', 'translate-words'), external.label)}
                            // Like Chrome AI: the setup button shows only while the provider is on and not set up yet.
                            actionLabel={state.enabled && !configured && hasPanel ? (external.action_label || __('Configure', 'translate-words')) : ''}
                            onAction={() => scrollToExternalCard(key)}
                        />
                    )
                })}

                {/* Linguator Pro providers, locked while Pro is not active. They need the WordPress AI Client, like Gemini. */}
                {geminiAvailable && getLockedProProviders(window?.lmat_settings, externalProviders)
                    .map((pro) => {
                        const iconUrl = pro.iconUrl;
                        const lockedReason = sprintf(
                            /* translators: %s: translation provider name */
                            __('%s is available in Linguator Pro.', 'translate-words'),
                            pro.label
                        );

                        return (
                            <ProviderRow
                                key={`pro-${pro.key}`}
                                locked
                                icon={iconUrl ? <img src={iconUrl} alt="" className="icon-size" /> : null}
                                name={pro.label}
                                enabled={false}
                                onToggle={() => {}}
                                isDefault={false}
                                onDefault={() => {}}
                                status="pro"
                                statusLabel={__('Pro', 'translate-words')}
                                defaultDisabledReason={lockedReason}
                                actionLabel={__('Get Pro', 'translate-words')}
                                actionHref={getProProviderUrl(pro.key, 'settings')}
                                extra={<p className="lmat-engine-pro-msg">{pro.description}</p>}
                            />
                        );
                    })}
            </ul>
        </div>
    );
};

export default ProviderList;
