import ModalStringScroll from "../../string-modal-scroll/index.js";
import { __ } from "@wordpress/i18n";
import { mapToGoogleLanguageCode } from "./google-language.js";

/**
 * Initializes Google Translate functionality on specific elements based on provided data.
 * @param {Object} data - The data containing source and target languages.
 */
const GoogleTranslater = async (data) => {

    const { sourceLang, targetLang, ID, translateStatusHandler, modalRenderId, destroyUpdateHandler } = data;

    const languageObject = lmatPageTranslationGlobal.languageObject || {};
    const lang = mapToGoogleLanguageCode(targetLang, languageObject);
    const srcLang = mapToGoogleLanguageCode(sourceLang, languageObject);

    let cancelled = false;
    let host;
    const onChange = () => ModalStringScroll(translateStatusHandler, 'google', modalRenderId, { sourceLang, targetLang });
    if (typeof destroyUpdateHandler === 'function') {
        destroyUpdateHandler(() => {
            cancelled = true;
            if (host) {
                host.removeEventListener('change', onChange);
                host.innerHTML = '';
            }
        });
    }

    const reportGoogleUnavailable = () => {
        document.dispatchEvent(
            new CustomEvent("lmat-page-translation:translation-error", {
                bubbles: true,
                detail: {
                    message: __(
                        "Google Translate could not be loaded. Check your internet connection, disable ad blockers for this site, and try again.",
                        "translate-words"
                    ),
                },
            })
        );
        translateStatusHandler(false);
    };

    try {
        if (window.lmatLoadGoogleTranslate) await window.lmatLoadGoogleTranslate();
        if (cancelled) return;
        const TranslateElementCtor = window.google?.translate?.TranslateElement;
        if (typeof TranslateElementCtor !== 'function') throw new Error('Google Translate is not ready.');
        host = document.getElementById(ID);
        if (!host) return;
        host.addEventListener('change', onChange);
        new TranslateElementCtor({
            pageLanguage: srcLang,
            includedLanguages: lang,
            defaultLanguage: srcLang,
            multilanguagePage: true,
            autoDisplay: false,
        }, ID);
    } catch (error) {
        if (!cancelled) {
            console.error('Google Translate initialization failed:', error);
            reportGoogleUnavailable();
        }
        return;
    }

}

export default GoogleTranslater;
