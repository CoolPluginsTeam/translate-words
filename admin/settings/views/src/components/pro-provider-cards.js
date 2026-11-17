import { __, sprintf } from '@wordpress/i18n';
import { getLockedProProviders, getProProviderUrl } from './pro-providers.js';

const badgeStyle = {
    background: '#eff6ff',
    borderRadius: '10px',
    color: '#1d4ed8',
    fontSize: '11px',
    fontWeight: 600,
    lineHeight: '16px',
    marginLeft: 'auto',
    padding: '1px 8px',
};

/**
 * Linguator Pro providers shown locked in a translation popup while Pro is not active.
 *
 * Uses the popup's own provider card classes so the cards match the real ones. A locked card cannot be selected;
 * its only action is the link to the Pro page.
 *
 * @param {Object} props
 * @param {string} props.prefix    Class prefix of the popup's provider cards.
 * @param {Object} props.localized Localized popup data: wp_ai_client_available, pro_active, pro_provider_icons.
 * @param {string} props.screen    Where the cards are shown, for the link's tracking: 'bulk' or 'page'.
 * @return {JSX.Element[]|null}
 */
const ProProviderCards = ({ prefix, localized, screen }) => {
    // Pro's providers translate through the WordPress AI Client, like Gemini.
    if (!localized?.wp_ai_client_available) {
        return null;
    }

    return getLockedProProviders(localized).map((pro) => {
        const lockedText = sprintf(
            /* translators: %s: translation provider name */
            __('%s is available in Linguator Pro.', 'translate-words'),
            pro.label
        );

        return (
            <div
                key={`pro-${pro.key}`}
                className={`${prefix}-provider-card ${prefix}-provider-card-disabled lmat-pro-provider-card`}
                data-service={pro.key}
                id={`${prefix}-provider-card-${pro.key}-locked`}
                aria-disabled="true"
                title={lockedText}
            >
                <div className={`${prefix}-provider-card-body`}>
                    <span className={`${prefix}-provider-card-icon`} aria-hidden="true">
                        {pro.iconUrl ? <img src={pro.iconUrl} alt="" /> : null}
                    </span>
                    <span className={`${prefix}-provider-card-name`}>{pro.label}</span>
                    <span style={badgeStyle}>{__('Pro', 'translate-words')}</span>
                </div>
                <div className={`${prefix}-provider-card-actions`}>
                    <a
                        href={getProProviderUrl(pro.key, screen)}
                        target="_blank"
                        rel="noopener noreferrer"
                        className={`${prefix}-provider-card-docs`}
                        aria-label={sprintf(
                            /* translators: %s: translation provider name */
                            __('Get Linguator Pro to translate with %s', 'translate-words'),
                            pro.label
                        )}
                    >
                        {__('Get Pro', 'translate-words')}
                    </a>
                </div>
            </div>
        );
    });
};

export default ProProviderCards;
