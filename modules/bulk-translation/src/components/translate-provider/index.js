// import YandexTranslater from "./yandex";
import localAiTranslator from "./local-ai/index.js";
import GoogleTranslater from "./google/index.js";
import AiLlmBulkTranslator from "./ai-llm/index.js";
import { sprintf, __ } from "@wordpress/i18n";
import { ChromeIcon } from "../../../../../assets/logo/chrome.js";
import { EdgeIcon } from "../../../../../assets/logo/edge.js";
import { GoogleIcon } from "../../../../../assets/logo/google.js";
import { GeminiIcon } from "../../../../../assets/logo/gemini.js";

/**
 * Provides translation services using Yandex Translate.
 */
export default (props) => {
    props=props || {};
    const { Service = false, openErrorModalHandler=()=>{}, prefix='' } = props;
    const adminUrl = window.lmatBulkTranslationGlobal.admin_url;
    const assetsUrl = window.lmatBulkTranslationGlobal.lmat_url+'admin/assets/images/';
    const errorIcon = assetsUrl + 'error-icon.svg';
    const providers=window.lmatBulkTranslationGlobal.providers;

    const Services = {
        // yandex: {
        google: {
            Provider: GoogleTranslater,
            title: "Google Translate",
            SettingBtnText: "Translate",
            serviceLabel: "Google Translate",
            Docs: "https://linguator.com/docs/automatic-translation-via-google-translate-widget/?utm_source=twlmat_plugin&utm_medium=inside&utm_campaign=docs&utm_content=bulk_translate_google",
            heading: __("Choose Language", 'translate-words'),
            BetaEnabled: false,
            ButtonDisabled: props.googleButtonDisabled,
            ErrorMessage: props.googleButtonDisabled ? <div className={`${prefix}-provider-error button button-primary`} onClick={() => openErrorModalHandler(props.googleButtonDisabled)}><img src={errorIcon} alt="error" /> {__('View Error.', 'translate-words')}</div> : <></>,
            Logo: <GoogleIcon className="icon-size" />,
            filterHtmlContent: true
        },
        localAiTranslator: {
            Provider: localAiTranslator,
            title: "Chrome Built-in AI",
            SettingBtnText: "Translate",
            serviceLabel: "Chrome AI Translator",
            heading: sprintf(__("Translate Using %s", 'translate-words'), "Chrome built-in API"),
            Docs: "https://linguator.com/docs/automatic-translation-via-chrome-ai/?utm_source=twlmat_plugin&utm_medium=inside&utm_campaign=docs&utm_content=bulk_translate_chrome",
            BetaEnabled: true,
            ButtonDisabled: props.localAiTranslatorButtonDisabled,
            ErrorMessage: props.localAiTranslatorButtonDisabled ? <div className={`${prefix}-provider-error button button-primary`} onClick={() => openErrorModalHandler(props.localAiTranslatorButtonDisabled)}><img src={errorIcon} alt="error" /> {__('View Error', 'translate-words')}</div> : <></>,
            Logo: <ChromeIcon className="icon-size"  />,
            filterHtmlContent: true
        },
        edgeLocalAiTranslator: {
            Provider: localAiTranslator,
            title: "Edge Built-in AI",
            SettingBtnText: "Translate",
            serviceLabel: "Edge AI Translator",
            heading: sprintf(__("Translate Using %s", 'translate-words'), "Edge built-in API"),
            Docs: "https://microsoftedge.github.io/Demos/built-in-ai/playgrounds/translator-api/",
            BetaEnabled: true,
            ButtonDisabled: props.edgeLocalAiTranslatorButtonDisabled,
            ErrorMessage: props.edgeLocalAiTranslatorButtonDisabled ? <div className={`${prefix}-provider-error button button-primary`} onClick={() => openErrorModalHandler(props.edgeLocalAiTranslatorButtonDisabled)}><img src={errorIcon} alt="error" /> {__('View Error', 'translate-words')}</div> : <></>,
            Logo: <EdgeIcon className="icon-size"  />,
            filterHtmlContent: true
        },
        gemini: {
            Provider: AiLlmBulkTranslator,
            title: "Google Gemini",
            SettingBtnText: window.lmatBulkTranslationGlobal.api_keys_status?.gemini ? "Translate" : "Add API Key",
            serviceLabel: "Gemini",
            heading: __("Translate Using Gemini", "translate-words"),
            Docs: "https://linguator.com/docs/automatic-translation-via-googe-gemini/?utm_source=twlmat_plugin&utm_medium=inside&utm_campaign=docs",
            BetaEnabled: false,
            ButtonDisabled: !window.lmatBulkTranslationGlobal.api_keys_status?.gemini,
            ErrorMessage: !window.lmatBulkTranslationGlobal.api_keys_status?.gemini ? <a href={`${window.lmatBulkTranslationGlobal.admin_url}admin.php?page=lmat_settings&tab=translation`} target="_blank" className={`${prefix}-provider-error button button-primary`}>{__('Add API Key', 'translate-words')}</a> : <></>,
            Logo: <GeminiIcon className="icon-size" />,
            filterHtmlContent: true
        }
    };

    const browserType = (() => {
        let type = 'Other';
        if (navigator && navigator.userAgentData && navigator.userAgentData.brands) {
            navigator.userAgentData.brands.forEach(data => {
                if (data.brand === 'Google Chrome') {
                    type = 'Chrome';
                } else if (data.brand === 'Microsoft Edge') {
                    type = 'Edge';
                }
            });
        } else {
            if (navigator.userAgent && navigator.userAgent.includes('Edg')) {
                type = 'Edge';
            } else if (window.hasOwnProperty('chrome')) {
                type = 'Chrome';
            }
        }
        return type;
    })();

    const validServices={};

    providers.forEach((provider) => {
        if (Services[provider]) {
            if (browserType === 'Chrome' && provider === 'edgeLocalAiTranslator') {
                return;
            }
            if (browserType === 'Edge' && provider === 'localAiTranslator') {
                return;
            }
            validServices[provider] = Services[provider];
        }
    });

    if (!Service) {
        return validServices;
    }

    return validServices[Service];
};
