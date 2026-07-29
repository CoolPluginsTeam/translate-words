import Providers from "./providers.js";
import TranslateService from "../components/translate-provider/index.js";
import { __ } from "@wordpress/i18n";

const SettingModalBody = (props) => {
    const { prefix, localAiModalError, edgeLocalAiModalError } = props;
    const ServiceProviders = TranslateService();
    const hasProviders = Object.keys(ServiceProviders).length > 0;
    const settingsUrl = (window.lmatBulkTranslationGlobal.admin_url || '') + 'admin.php?page=lmat_settings&tab=translation';

    // Expose hasProviders upward so the header can switch its title
    if (props.onHasProviders) {
        props.onHasProviders(hasProviders);
    }

    return (
        <div className={`${prefix}-setting-modal-body`}>
            {hasProviders ? (
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Translate</th>
                            <th>Docs</th>
                        </tr>
                    </thead>
                    <tbody>
                        {Object.keys(ServiceProviders).map((provider) => (
                            <Providers
                                key={provider}
                                {...props}
                                localAiTranslatorDisabled={localAiModalError}
                                localAiModalError={localAiModalError}
                                edgeLocalAiTranslatorDisabled={edgeLocalAiModalError}
                                edgeLocalAiModalError={edgeLocalAiModalError}
                                openErrorModalHandler={props.errorModalHandler}
                                Service={provider}
                            />
                        ))}
                    </tbody>
                </table>
            ) : (
                <div className={`${prefix}-no-provider-message`}>
                    <p className={`${prefix}-no-provider-text`}>
                        {__('You have not enabled any translation provider. Please enable at least one service provider to use bulk translation. Go to the', 'translate-words')}{' '}
                        <a href={settingsUrl} target="_blank" rel="noopener noreferrer">
                            {__('Translation Settings', 'translate-words')}
                        </a>
                        {' '}{__('to configure a translation provider.', 'translate-words')}
                    </p>
                </div>
            )}
        </div>
    );
}

export default SettingModalBody;
