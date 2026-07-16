import React, { useState, useEffect } from "react";
import ReactDOM from "react-dom/client";
import SettingModalHeader from "./header.js";
import SettingModalBody from "./body.js";
import SettingModalFooter from "./footer.js";
import { __ } from "@wordpress/i18n";
import ErrorModalBox from "../components/error-modal-box/index.js";
import ChromeLocalAiTranslator from "../components/translate-provider/local-ai/local-ai-translate.js";

const SettingModal = (props) => {
    const prefix = props.prefix || 'lmat-bulk-translate';
    const imgFolder = lmatBulkTranslationGlobal.lmat_url + 'admin/assets/images/';
    const [errorModal, setErrorModal] = useState(false);
    const providers = lmatBulkTranslationGlobal.providers;

    const [chromeAiDownload, setChromeAiDownload] = useState(null);
    const [edgeAiDownload, setEdgeAiDownload] = useState(null);
    const [chromeAiBtnDisabled, setChromeAiBtnDisabled] = useState(false);
    const [edgeAiBtnDisabled, setEdgeAiBtnDisabled] = useState(false);
    const [isChecking, setIsChecking] = useState(true);

    const providerErrors = async () => {
        let errors = {};
        const sourceLang = lmatBulkTranslationGlobal.source_lang;
        const sourceLangName = lmatBulkTranslationGlobal.languageObject[sourceLang]?.name || sourceLang;

        const localAiSupportStatus = async () => {
            if (!props.selectedLanguages || !props.selectedLanguages.length) return;
            for (const targetLang of props.selectedLanguages) {
                const targetLangName = lmatBulkTranslationGlobal.languageObject[targetLang]?.name || targetLang;
                const status = await ChromeLocalAiTranslator.languageSupportedStatus(sourceLang, targetLang, targetLangName, sourceLangName, false);

                if (status !== true && typeof status === 'object') {
                    if (status.hasClass && status.hasClass('atlt-chromeai-download-pending')) {
                        const isDownloading = status.hasClass('atlt-chromeai-downloading');
                        setChromeAiDownload({
                            status: isDownloading ? 'downloading' : 'downloadable',
                            progress: 0,
                            source: status.attr('data-source'),
                            target: status.attr('data-target'),
                            sourceLabel: status.attr('data-source-label'),
                            targetLabel: status.attr('data-target-label')
                        });
                        errors.chromeAiDownloadPending = true;
                        return; // Stop at the first pending download
                    } else {
                        setChromeAiBtnDisabled(true);
                        errors.localAiTranslator = status[0]?.outerHTML || status;
                        return;
                    }
                }
            }
            setChromeAiBtnDisabled(false);
            setChromeAiDownload(null);
        };

        const edgeLocalAiSupportStatus = async () => {
            if (!props.selectedLanguages || !props.selectedLanguages.length) return;
            for (const targetLang of props.selectedLanguages) {
                const targetLangName = lmatBulkTranslationGlobal.languageObject[targetLang]?.name || targetLang;
                const status = await ChromeLocalAiTranslator.languageSupportedStatus(sourceLang, targetLang, targetLangName, sourceLangName, true);

                if (status !== true && typeof status === 'object') {
                    if (status.hasClass && status.hasClass('atlt-chromeai-download-pending')) {
                        const isDownloading = status.hasClass('atlt-chromeai-downloading');
                        setEdgeAiDownload({
                            status: isDownloading ? 'downloading' : 'downloadable',
                            progress: 0,
                            source: status.attr('data-source'),
                            target: status.attr('data-target'),
                            sourceLabel: status.attr('data-source-label'),
                            targetLabel: status.attr('data-target-label')
                        });
                        errors.edgeAiDownloadPending = true;
                        return; // Stop at the first pending download
                    } else {
                        setEdgeAiBtnDisabled(true);
                        errors.edgeLocalAiTranslator = status[0]?.outerHTML || status;
                        return;
                    }
                }
            }
            setEdgeAiBtnDisabled(false);
            setEdgeAiDownload(null);
        };

        if (providers.includes('localAiTranslator')) {
            await localAiSupportStatus();
        }
        if (providers.includes('edgeLocalAiTranslator')) {
            await edgeLocalAiSupportStatus();
        }

        return errors;
    };

    const checkAllProviderErrors = async () => {
        const errors = await providerErrors();
        if (!errors.chromeAiDownloadPending && !errors.edgeAiDownloadPending) {
            if (providers.length < 2 && providers[0]) {
                props.updateProviderHandler(providers[0]);
            }
        }
    };

    useEffect(() => {
        const check = async () => {
            await providerErrors();
            setIsChecking(false);
        };
        check();
    }, [props.selectedLanguages]);

    useEffect(() => {
        if (isChecking) return;

        if (providers.length < 2 && providers[0]) {
            const provider = providers[0];

            if (provider === 'localAiTranslator' && chromeAiDownload) {
                return;
            }
            if (provider === 'edgeLocalAiTranslator' && edgeAiDownload) {
                return;
            }

            const errorNameKey = () => {
                switch (provider) {
                    case 'localAiTranslator':
                        return 'localAiModalError';
                    case 'edgeLocalAiTranslator':
                        return 'edgeLocalAiModalError';
                    default:
                        return provider + 'ModalError';
                }
            }

            const errorName = errorNameKey(provider);

            if (props[errorName]) {
                setErrorModal(props[errorName]);
            } else {
                props.updateProviderHandler(provider);
            }
        }
    }, [providers, isChecking, chromeAiDownload, edgeAiDownload]);

    const startTranslationHandler = async (e) => {
        let targetElement = !e.target.classList.contains(`${prefix}-service-btn`) ? e.target.closest(`.${prefix}-service-btn`) : e.target;

        if (!targetElement) {
            return;
        }

        const dataService = targetElement.dataset && targetElement.dataset.service;

        props.updateProviderHandler(dataService);
    };

    const errorModalHandler = (msg) => {
        setErrorModal(msg);
    }

    const closeErrorModal = (e) => {
        setErrorModal(false);
        if (providers.length < 2 && providers[0]) {
            props.onDestory(e);
        }
    }

    return (
        <>
            {errorModal ? <ErrorModalBox message={errorModal} onDestroy={props.onDestory} onClose={closeErrorModal} Title='Linguator Multilingual AI Translation' prefix={prefix} /> :
            ((providers.length > 1 || chromeAiDownload || edgeAiDownload) && <div id={`${prefix}-setting-modal-container`}>
                <div className={`${prefix}-setting-modal-content`}>
                    <SettingModalHeader
                        setSettingVisibility={props.onDestory}
                        prefix={prefix}
                    />
                    <SettingModalBody
                        startTranslationHandler={startTranslationHandler}
                        imgFolder={imgFolder}
                        prefix={prefix}
                        localAiModalError={chromeAiBtnDisabled ? props.localAiModalError : null}
                        edgeLocalAiModalError={edgeAiBtnDisabled ? props.edgeLocalAiModalError : null}
                        errorModalHandler={errorModalHandler}
                        chromeAiDownload={chromeAiDownload}
                        setChromeAiDownload={setChromeAiDownload}
                        edgeAiDownload={edgeAiDownload}
                        setEdgeAiDownload={setEdgeAiDownload}
                        chromeAiDisabled={chromeAiBtnDisabled}
                        edgeLocalAiTranslatorDisabled={edgeAiBtnDisabled}
                        checkAllProviderErrors={checkAllProviderErrors}
                    />
                    <SettingModalFooter
                        setSettingVisibility={props.onCloseHandler}
                        prefix={prefix}
                    />
                </div>
            </div>)}
        </>
    );
};

export default SettingModal;