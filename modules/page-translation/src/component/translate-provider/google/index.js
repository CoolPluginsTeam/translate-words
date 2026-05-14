import ModalStringScroll from "../../string-modal-scroll/index.js";

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
    
    new google.translate.TranslateElement({
        pageLanguage: srcLang,
        includedLanguages: lang,
        defaultLanguage: srcLang,
        multilanguagePage: true,
        autoDisplay: false,
    }, ID);

    const element=document.querySelector(`#${ID}`);

    if(element){
        const translateElement=element.children;
        
        if(translateElement.length <= 0){
            Object.values(google?.translate?.TranslateElement()).map(item=>{
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