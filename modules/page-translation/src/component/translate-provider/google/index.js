import ModalStringScroll from "../../string-modal-scroll/index.js";
import { __ } from "@wordpress/i18n";

/**
 * Initializes Google Translate functionality on specific elements based on provided data.
 * @param {Object} data - The data containing source and target languages.
 */
const GoogleTranslater = (data) => {

    const { sourceLang, targetLang, ID, translateStatusHandler, modalRenderId, destroyUpdateHandler } = data;

    let lang=targetLang;
    let srcLang=sourceLang;
    
    if(lang === 'zh'){
        lang=lmatPageTranslationGlobal.languageObject['zh']?.locale.replace('_', '-');
    }

    if(srcLang === 'zh'){
        srcLang=lmatPageTranslationGlobal.languageObject['zh']?.locale.replace('_', '-');
    }

    const g = typeof window !== "undefined" ? window.google : undefined;
    const TranslateElementCtor = g?.translate?.TranslateElement;

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

    if (!TranslateElementCtor) {
        reportGoogleUnavailable();
        return;
    }

    try {
        new TranslateElementCtor({
            pageLanguage: srcLang,
            includedLanguages: lang,
            defaultLanguage: srcLang,
            multilanguagePage: true,
            autoDisplay: false,
        }, ID);
    } catch {
        reportGoogleUnavailable();
        return;
    }

    const element=document.querySelector(`#${ID}`);

    if(element){
        const translateElement=element.children;
        
        if(translateElement.length <= 0){
            Object.values(TranslateElementCtor()).map(item=>{
                if(item instanceof HTMLElement && item.id === 'lmat_page_translation_google_translate_element'){
                    element.replaceWith(item);
                }
            });
        }
    }

    const onChange = () => {
        ModalStringScroll(translateStatusHandler,'google', modalRenderId);
    };

    const host = document.querySelector(`#${ID}`);
    if (host) {
        host.addEventListener('change', onChange);
    }

    if (typeof destroyUpdateHandler === 'function') {
        destroyUpdateHandler(() => {
            const node = document.querySelector(`#${ID}`);
            if (node) {
                node.removeEventListener('change', onChange);
                node.innerHTML = '';
            }
        });
    }

}

export default GoogleTranslater;