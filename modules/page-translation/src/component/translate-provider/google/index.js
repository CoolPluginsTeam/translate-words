import ModalStringScroll from "../../string-modal-scroll/index.js";
import { __ } from "@wordpress/i18n";
import { mapToGoogleLanguageCode } from "./google-language.js";

/**
 * Clears the `googtrans` cookie that the Google Translate Element uses to
 * remember the previously selected language.  Without this, reopening the
 * translation panel for a *different* page causes the widget to auto-select
 * the cached language and auto-translate without firing a `change` event,
 * which leaves the "Update Content" button permanently disabled.
 *
 * The cookie is set on both the current domain and `.google.com`, so we
 * attempt to expire it on every path / domain combination the browser
 * might recognise.
 */
const clearGoogTransCookie = () => {
    const cookieName = 'googtrans';
    const expiry = 'expires=Thu, 01 Jan 1970 00:00:00 UTC';

    // Current hostname (e.g. "mysite.local") and its dot-prefixed variant.
    const host = window.location.hostname;
    const domains = ['', host, `.${host}`];

    // Clear on root path for each domain variant.
    domains.forEach((domain) => {
        const domainPart = domain ? `; domain=${domain}` : '';
        document.cookie = `${cookieName}=; ${expiry}; path=/${domainPart}`;
    });
};

/**
 * Initializes Google Translate functionality on specific elements based on provided data.
 * @param {Object} data - The data containing source and target languages.
 */
const GoogleTranslater = (data) => {

    const { sourceLang, targetLang, ID, translateStatusHandler, modalRenderId, destroyUpdateHandler } = data;

    const languageObject = lmatPageTranslationGlobal.languageObject || {};
    const lang = mapToGoogleLanguageCode(targetLang, languageObject);
    const srcLang = mapToGoogleLanguageCode(sourceLang, languageObject);

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

    // Clear the cached language cookie so the widget always starts at
    // "Select language", forcing the user to pick a language and fire the
    // `change` event that drives the translation-completion flow.
    clearGoogTransCookie();

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

    let changeReceived = false;

    const onChange = () => {
        changeReceived = true;
        ModalStringScroll(translateStatusHandler,'google', modalRenderId);
    };

    const host = document.querySelector(`#${ID}`);
    if (host) {
        host.addEventListener('change', onChange);
    }

    // ------------------------------------------------------------------
    // Fallback: detect when the Google Translate widget auto-selects a
    // language (e.g. from a cookie that wasn't fully cleared, or from
    // browser-level translation memory) without firing a `change` event.
    //
    // We observe the widget's `<select>` element; if its value changes
    // from the source language to the target language and no `change`
    // event has been received, we programmatically trigger the flow.
    // ------------------------------------------------------------------
    let selectObserver = null;

    const setupSelectObserver = () => {
        const widgetHost = document.querySelector(`#${ID}`);
        if (!widgetHost) return;

        const selectEl = widgetHost.querySelector('select');
        if (!selectEl) return;

        selectObserver = new MutationObserver(() => {
            if (changeReceived) return;

            // The Google Translate Element sets the <select> value to the
            // target language code when it auto-translates.  It also adds
            // child <option> elements, so any attribute / childList change
            // is a good signal to re-check.
            const currentVal = selectEl.value;
            if (currentVal && currentVal !== '' && currentVal !== srcLang) {
                // The widget auto-selected a language without a change event.
                changeReceived = true;
                selectObserver.disconnect();
                selectObserver = null;
                ModalStringScroll(translateStatusHandler, 'google', modalRenderId);
            }
        });

        selectObserver.observe(selectEl, {
            attributes: true,
            childList: true,
            subtree: true,
        });
    };

    // The <select> may not be present immediately; poll briefly.
    let observerAttempts = 0;
    const observerInterval = setInterval(() => {
        observerAttempts++;
        if (changeReceived || observerAttempts > 20) {
            clearInterval(observerInterval);
            return;
        }
        const widgetHost = document.querySelector(`#${ID}`);
        const selectEl = widgetHost?.querySelector('select');
        if (selectEl) {
            clearInterval(observerInterval);
            setupSelectObserver();
        }
    }, 500);

    if (typeof destroyUpdateHandler === 'function') {
        destroyUpdateHandler(() => {
            clearInterval(observerInterval);
            if (selectObserver) {
                selectObserver.disconnect();
                selectObserver = null;
            }
            const node = document.querySelector(`#${ID}`);
            if (node) {
                node.removeEventListener('change', onChange);
                node.innerHTML = '';
            }
        });
    }

}

export default GoogleTranslater;