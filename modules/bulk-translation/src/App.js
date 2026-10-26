import React, { useState, useEffect } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import StatusModal from './status-modal/index.js';
import { useDispatch, useSelector } from 'react-redux';
import { resetStore, updateServiceProvider } from './redux-store/features/actions.js';
import { selectCountInfo } from './redux-store/features/selectors.js';
import ChromeAiTranslator from './components/translate-provider/local-ai/local-ai-translate.js';
import ErrorModalBox from './components/error-modal-box/index.js';
import SettingModal from './setting-modal/index.js';
import SettingModalHeader from './setting-modal/header.js';
import SettingModalFooter from './setting-modal/footer.js';
import TranslateService from './components/translate-provider/index.js';
import DOMPurify from 'dompurify';
import Notice from './components/notice/index.js';

const App = ({ onDestory, prefix, postIds }) => {
    const dispatch = useDispatch();
    const validProviders = Object.keys(TranslateService());
    const hasValidProviders = validProviders.length > 0;
    const { languageObject = {} } = lmatBulkTranslationGlobal || {};
    const postLabelSingular = lmatBulkTranslationGlobal.post_label_singular || lmatBulkTranslationGlobal.post_label;
    const emptyPostIdsErrorMessage = sprintf(__('Please select at least one %s for translation.', 'translate-words'), postLabelSingular);
    const [selectedLanguages, setSelectedLanguages] = useState([]);
    const [errorMessage, setErrorMessage] = useState(postIds.length === 0 ? emptyPostIdsErrorMessage : '');
    const [settingModalVisibility, setSettingModalVisibility] = useState(false);
    const [statusModalVisibility, setStatusModalVisibility] = useState(false);
    const translatePostsCount = useSelector(selectCountInfo).totalPosts;
    const [isLoading, setIsLoading] = useState(true);
    const [errorModal, setErrorModal] = useState(false);
    const [localAiModalError, setLocalAiModalError] = useState(false);
    const [edgeLocalAiModalError, setEdgeLocalAiModalError] = useState(false);

    const destroyApp = (e) => {
        setStatusModalVisibility(false);
        setSettingModalVisibility(false);
        if (e && typeof e.preventDefault === 'function') {
            onDestory(e);
            return;
        }
        onDestory({ preventDefault: () => {} });
    }


    useEffect(() => {
        const checkStatus = async () => {
            const chromeStatus = await ChromeAiTranslator.languageSupportedStatus('en', 'hi', 'English', 'Hindi', false);
            if (chromeStatus && (chromeStatus.type === 'browser-not-supported' || chromeStatus.type === 'translation-api-not-available')) {
                setLocalAiModalError(chromeStatus.html[0].outerHTML);
            }

            const edgeStatus = await ChromeAiTranslator.languageSupportedStatus('en', 'hi', 'English', 'Hindi', true);
            if (edgeStatus && (edgeStatus.type === 'browser-not-supported' || edgeStatus.type === 'translation-api-not-available')) {
                setEdgeLocalAiModalError(edgeStatus.html[0].outerHTML);
            }

            setIsLoading(false);
        }

        checkStatus();
    }, [statusModalVisibility]);

    useEffect(() => {
        if (!statusModalVisibility && !settingModalVisibility) {
            dispatch(resetStore());
        }
    }, [statusModalVisibility, settingModalVisibility, dispatch]);

    const settingModalVisibilityHandler = async () => {
        if (selectedLanguages.length === 0 && !settingModalVisibility) {
            setErrorMessage(__('Please select at least one language', 'translate-words'));
            setErrorModal(true);
            return;
        }

        setSettingModalVisibility((prev) => !prev);
    }

    const handleLanguageChange = (e) => {
        const { value } = e.target;
        const checked = e.target.checked;
        if (checked) {
            setSelectedLanguages([...selectedLanguages, value]);
        } else {
            setSelectedLanguages(selectedLanguages.filter(language => language !== value));
        }
    }

    const closeErrorModal = (e) => {
        setErrorModal(false);
    }

    const handleSelectAllLanguages = (e) => {
        const checked = e.target.checked;
        if (checked) {
            setSelectedLanguages(Object.keys(languageObject));
        } else {
            setSelectedLanguages([]);
        }
    }

    const updateProviderHandler = (services) => {
        dispatch(updateServiceProvider(services));
        setSettingModalVisibility(false);
        setStatusModalVisibility(true);
        setIsLoading(true);
    }

    const shouldHideLanguagesHeader = postIds.length === 0 && errorMessage === emptyPostIdsErrorMessage;

    const containerCls=()=>{
        let cls=[];
        if(statusModalVisibility){
            cls.push(`${prefix}-status-modal-active`);
        }

        if(settingModalVisibility){
            cls.push(`${prefix}-setting-modal-active`);
        }

        if(!translatePostsCount && !settingModalVisibility && statusModalVisibility){
            cls.push(`${prefix}-empty-posts`);
        }

        return cls.join(' ');
    }

    const SelectLanguageNotice = () => {

        const notices = [];
      
        const postMetaSync = lmatBulkTranslationGlobal.postMetaSync === 'true' && lmatBulkTranslationGlobal.taxonomy_page !== 'taxonomy';

          if (postMetaSync) {
            notices.push({
              className: `${prefix}-notice ${prefix}-notice-error`, message: <p>
                {__('For accurate custom field translations, please disable the Custom Fields synchronization in ', 'translate-words')}
                <a
                  href={`${lmatBulkTranslationGlobal.admin_url}admin.php?page=lmat_settings`}
                  target="_blank"
                  rel="noopener noreferrer"
                >
                  {__('Linguator settings', 'translate-words')}
                </a>
                {sprintf(__('. This may affect linked %s.', 'translate-words'), lmatBulkTranslationGlobal.post_label)}
              </p>
            });
          }
      
        const noticeLength = notices.length;
      
        if (notices.length > 0) {
          return notices.map((notice, index) => <Notice className={notice.className} key={index} lastNotice={index === noticeLength - 1}>{notice.message}</Notice>);
        }
      
        return;
      }

    // No compatible provider: show error immediately, skip language selection
    if (!hasValidProviders) {
        const settingsUrl = (lmatBulkTranslationGlobal.admin_url || '') + 'admin.php?page=lmat_settings&tab=translation';
        return <div
            id={`${prefix}-container`}
            className={`${prefix}-setting-modal-active`}>
            <div id={`${prefix}-setting-modal-container`}>
                <div className={`${prefix}-setting-modal-content`}>
                    <SettingModalHeader
                        setSettingVisibility={destroyApp}
                        prefix={prefix}
                        hasProviders={false}
                    />
                    <div className={`${prefix}-setting-modal-body`}>
                        <div className={`${prefix}-no-provider-message`}>
                            <p className={`${prefix}-no-provider-text`}>
                                {__('You have not enabled any translation provider. Please enable at least one service provider to use bulk translation. Go to the', 'translate-words')}{' '}
                                <a href={settingsUrl} target="_blank" rel="noopener noreferrer">
                                    {__('Translation Settings', 'translate-words')}
                                </a>
                                {' '}{__('to configure a translation provider.', 'translate-words')}
                            </p>
                        </div>
                    </div>
                    <SettingModalFooter
                        setSettingVisibility={destroyApp}
                        prefix={prefix}
                    />
                </div>
            </div>
        </div>;
    }

    return <div
        id={`${prefix}-container`}
        className={containerCls()}>
        {settingModalVisibility && <SettingModal
            postIds={postIds}
            prefix={prefix}
            onDestory={destroyApp}
            onCloseHandler={settingModalVisibilityHandler}
            updateProviderHandler={updateProviderHandler} 
            localAiModalError={localAiModalError}
            edgeLocalAiModalError={edgeLocalAiModalError}
            selectedLanguages={selectedLanguages}
        />}

        {statusModalVisibility && !settingModalVisibility && (isLoading ?
            <div
                className={`${prefix}-skeleton-loader`}></div> :
            <StatusModal
                postIds={postIds}
                selectedLanguages={selectedLanguages}
                prefix={prefix}
                onDestory={destroyApp}
            />)}
        {!statusModalVisibility && !settingModalVisibility &&
            <div
                className={`${prefix}-language-container`}>
                <div
                    className={`${prefix}-header`}>
                    {shouldHideLanguagesHeader ? (
                        <h2>{__('Selection Required', 'translate-words')}</h2>
                    ) : (
                        <h2>{__('Step 1: Select Languages', 'translate-words')}</h2>
                    )}
                    <span
                        className="close"
                        onClick={destroyApp}
                        title={__('Close', 'translate-words')}
                    >
                        &times;
                    </span>
                </div>
                {errorMessage && errorMessage !== '' ? (errorModal ? <ErrorModalBox
                    message={errorMessage}
                    onClose={closeErrorModal}
                /> : <div
                    className={`${prefix}-error-message`}
                    dangerouslySetInnerHTML={{ __html: DOMPurify.sanitize(errorMessage) }}
                />) :
                    <>
                        <div
                            className={`${prefix}-body`}>
                            <SelectLanguageNotice />
                            <div
                                className={`${prefix}-languages`}>
                                {Object.keys(languageObject).map((language) => {
                                    return (lmatBulkTranslationGlobal.default_language_slug && lmatBulkTranslationGlobal.default_language_slug === language ? null : <div key={language} className={`${prefix}-language`}>
                                        <div
                                            title={!postIds.length ? emptyPostIdsErrorMessage : languageObject[language].name}>
                                            <input
                                                type="checkbox"
                                                name="languages"
                                                id={language}
                                                value={language}
                                                onChange={(e) => handleLanguageChange(e)}
                                                disabled={!postIds.length}
                                                checked={selectedLanguages.includes(language)} />
                                            <label
                                                htmlFor={language}
                                                className={`${prefix}-language-label`}
                                                title={languageObject[language].name}
                                            >
                                                <img
                                                    src={languageObject[language].flag}
                                                    alt={languageObject[language].name} />
                                                &nbsp; {languageObject[language].name}
                                            </label>
                                        </div>
                                    </div>)
                                })}
                            </div>
                            <div
                                className={`${prefix}-select-all-languages`}>
                                <input
                                    type="checkbox"
                                    name="select-all-languages"
                                    id="select-all-languages"
                                    onChange={(e) => handleSelectAllLanguages(e)}
                                    checked={selectedLanguages.length === Object.keys(languageObject).length} />
                                <label
                                    htmlFor="select-all-languages"
                                >
                                    {selectedLanguages.length === Object.keys(languageObject).length ? __('Unselect All', 'translate-words') : __('Select All', 'translate-words')}
                                </label>
                            </div>
                        </div>
                        <div
                            className={`${prefix}-footer`}>
                            <button
                                className={`${prefix}-footer-button button button-primary`}
                                onClick={destroyApp}
                                title={!postIds.length ? emptyPostIdsErrorMessage : ''}>
                                {__('Close', 'translate-words')}
                            </button>
                            <button
                                className={`${prefix}-footer-button button button-primary`}
                                onClick={settingModalVisibilityHandler}
                                disabled={!postIds.length || !selectedLanguages.length}
                                title={!postIds.length ? emptyPostIdsErrorMessage : (!selectedLanguages.length ? __('Please select at least one language', 'translate-words') : '')}>
                                {__('Translate', 'translate-words')}
                            </button>
                        </div>
                    </>}
            </div>
        }
    </div>
}

export default App;
