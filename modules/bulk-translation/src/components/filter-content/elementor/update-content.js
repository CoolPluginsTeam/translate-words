import {selectTranslatedContent} from '../../../redux-store/features/selectors.js';
import {store} from '../../../redux-store/store.js';
import updateMetaFields from '../metaFields/update-meta-fields.js';
import { atomicMainTextProps, getAtomicPropDefault } from './index.js';

const updateElementorContent = async ({source, lang, translatedContent, serviceProvider, postId}) => {

    /**
    * @param {Object} Object
    * @param {string} key
    * @param {string} translateValue
    * @returns {boolean}
    */
    const replaceValue = (Object, key, translateValue) => {
        if (Object && Object[key] && typeof Object[key] === 'string' && Object[key].trim() !== '') {
            Object[key] = translateValue;

            return true;
        }

        return false;
    }

    /**
     * Create an atomic widget's main text prop when it is missing (still at schema default).
     * @param {Object} content
     * @param {Array} keyArray
     * @param {string} translateValue
     */
    const ensureAtomicDefaultProp = (content, keyArray, translateValue) => {
        const settingsIndex = keyArray.lastIndexOf('settings');
        if(settingsIndex < 1 || keyArray.length !== settingsIndex + 3 || keyArray[settingsIndex + 2] !== 'value' || typeof translateValue !== 'string' || translateValue.trim() === ''){
            return;
        }

        let widget = content;
        for(let i = 0; i < settingsIndex; i++){
            widget = widget ? widget[keyArray[i]] : null;
        }

        const prop = keyArray[settingsIndex + 1];
        if(!widget || atomicMainTextProps[widget.widgetType] !== prop){
            return;
        }

        if(!widget.settings || typeof widget.settings !== 'object' || (Array.isArray(widget.settings) && widget.settings.length === 0)){
            widget.settings = {};
        }

        if(!Array.isArray(widget.settings) && (widget.settings[prop] === undefined || widget.settings[prop] === null)){
            const defaultValue = getAtomicPropDefault(widget.widgetType, prop);
            widget.settings[prop] = { $$type: defaultValue?.$$type || 'escaped-html', value: translateValue };
        }
    }

    const getTransaltedValue=(key)=>{
        const stateValue=selectTranslatedContent(store.getState(), postId, key, lang, serviceProvider);
        return stateValue;
    }

    /**
     * @param {Object} source
     * @param {string} value
     */
    const updateTitle=(source, value)=>{
        if(value && '' !== value){
            source.title=getTransaltedValue('title');
        }
    }

    /**
     * @param {Object} source
     * @param {string} value
     */
    const updatePostName=(source, value)=>{
        if(value && '' !== value){
            source.post_name=getTransaltedValue('post_name');
        }
    }

    const updateExcerpt=(source, value)=>{
        if(value && '' !== value){
            source.excerpt=getTransaltedValue('excerpt');
        }
    }

    /**
     * @param {Object} source
     * @param {Object} translation
     */
    const updateContent = (source, translation) => {
        Object.keys(translation).forEach(key=>{
            const keys=key.split('_lmat_bulk_content_temp_');
            if(keys[0] === 'title'){
                updateTitle(source, translation[keys[0]]);
            }else if(keys[0] === 'post_name'){
                updatePostName(source, translation[keys[0]]);
            }else if(keys[0] === 'excerpt'){
                updateExcerpt(source, translation[keys[0]]);
            }else if(keys[0] === 'content'){
                let keyArray=keys;

                let currentElement = source.content;
                const translateValue = getTransaltedValue(key);
                let parentElement = null;
                let parentKey = null;

                keyArray=keyArray.slice(1);
                ensureAtomicDefaultProp(source.content, keyArray, translateValue);

                keyArray.forEach(key => {
                    parentElement = currentElement;
                    parentKey = key;
                    currentElement = currentElement[key];
                });

                if (parentElement && parentKey) {
                    replaceValue(parentElement, parentKey, translateValue)
                }
            }
        });
    }

    updateContent(source, translatedContent);

    if("false" === lmatBulkTranslationGlobal.postMetaSync && source.metaFields && Object.keys(source.metaFields).length > 0){
        source.metaFields=updateMetaFields(source.metaFields, lang, serviceProvider, postId);
      }

    return source;
}

export default updateElementorContent;
