import GoogleTranslater from "./google/index.js";
import localAiTranslator from "./local-ai-translator/index.js";
import createAiLlmPageTranslator from "./ai-llm/index.js";
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
    const { Service = false, openErrorModalHandler=()=>{} } = props;
    const assetsUrl = window.lmatPageTranslationGlobal.lmat_url+'admin/assets/images/';
    const errorIcon = assetsUrl + 'error-icon.svg';
    const providers=window.lmatPageTranslationGlobal.providers;

    const Services = {
        google: {
            Provider: GoogleTranslater,
            title: "Google Translate",
            SettingBtnText: "Translate",
            serviceLabel: "Google Translate",
            Docs: "https://linguator.com/docs/automatic-translation-via-google-translate-widget/?utm_source=twlmat_plugin&utm_medium=inside&utm_campaign=docs&utm_content=popup_google_pro",
            heading: __("Choose Language", "translate-words"),
            BetaEnabled: false,
            ButtonDisabled: props.googleButtonDisabled,
            ErrorMessage: props.googleButtonDisabled ? <div className="lmat-page-translation-provider-error button button-primary" onClick={() => openErrorModalHandler("google")}><img src={errorIcon} alt="error" /> {__('View Error', 'translate-words')}</div> : <></>,
            Logo: <GoogleIcon className="icon-size"/>
        },
        localAiTranslator: {
            Provider: localAiTranslator,
            title: "Chrome Built-in AI",
            SettingBtnText: "Translate",
            serviceLabel: "Chrome AI Translator",
            heading: sprintf(__("Translate Using %s", "translate-words"), "Chrome built-in API"),
            Docs: "https://linguator.com/docs/automatic-translation-via-chrome-ai/?utm_source=twlmat_plugin&utm_medium=inside&utm_campaign=docs&utm_content=popup_chrome_pro",
            BetaEnabled: true,
            ButtonDisabled: props.localAiTranslatorButtonDisabled,
            ErrorMessage: props.localAiTranslatorButtonDisabled ? <div className="lmat-page-translation-provider-error button button-primary" onClick={() => openErrorModalHandler("localAiTranslator")}><img src={errorIcon} alt="error" /> {__('View Error', 'translate-words')}</div> : <></>,
            Logo: <ChromeIcon className="icon-size"/>
        },
        edgeLocalAiTranslator: {
            Provider: localAiTranslator,
            title: "Edge Built-in AI",
            SettingBtnText: "Translate",
            serviceLabel: "Edge AI Translator",
            heading: sprintf(__("Translate Using %s", "translate-words"), "Edge built-in API"),
            Docs: "https://microsoftedge.github.io/Demos/built-in-ai/playgrounds/translator-api/",
            BetaEnabled: true,
            ButtonDisabled: props.edgeLocalAiTranslatorButtonDisabled,
            ErrorMessage: props.edgeLocalAiTranslatorButtonDisabled ? <div className="lmat-page-translation-provider-error button button-primary" onClick={() => openErrorModalHandler("edgeLocalAiTranslator")}><img src={errorIcon} alt="error" /> {__('View Error', 'translate-words')}</div> : <></>,
            Logo: <EdgeIcon className="icon-size"/>
        },
        gemini: {
            Provider: createAiLlmPageTranslator("gemini"),
            title: "Google Gemini",
            SettingBtnText: window.lmatPageTranslationGlobal.api_keys_status?.gemini ? "Translate" : "Add API Key",
            serviceLabel: "Gemini",
            heading: __("Translate Using Gemini", "translate-words"),
            Docs: "https://linguator.com/docs/automatic-translation-via-googe-gemini/?utm_source=twlmat_plugin&utm_medium=inside&utm_campaign=docs",
            BetaEnabled: false,
            ButtonDisabled: !window.lmatPageTranslationGlobal.api_keys_status?.gemini,
            ErrorMessage: !window.lmatPageTranslationGlobal.api_keys_status?.gemini ? <a href={`${window.lmatPageTranslationGlobal.admin_url}admin.php?page=lmat_settings&tab=translation`} target="_blank" className="lmat-page-translation-provider-error button button-primary">{__('Add API Key', 'translate-words')}</a> : <></>,
            Logo: <GeminiIcon className="icon-size" />
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
