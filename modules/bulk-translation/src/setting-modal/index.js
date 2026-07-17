import React, { useState, useEffect } from "react";
import SettingModalHeader from "./header.js";
import SettingModalBody from "./body.js";
import SettingModalFooter from "./footer.js";
import ErrorModalBox from "../components/error-modal-box/index.js";

const AI_PROVIDERS = ['localAiTranslator', 'edgeLocalAiTranslator'];

const SettingModal = (props) => {
    const prefix = props.prefix || 'lmat-bulk-translate';
    const imgFolder = lmatBulkTranslationGlobal.lmat_url + 'admin/assets/images/';
    const [errorModal, setErrorModal] = useState(false);
    const providers = lmatBulkTranslationGlobal.providers || [];
    const [isChecking, setIsChecking] = useState(true);

    const singleProvider = providers.length === 1 ? providers[0] : null;
    // Always show provider UI for AI so Translate is a real user gesture (packs may still be warming from Bulk Translate hover).
    const requireProviderClick = !singleProvider || AI_PROVIDERS.includes(singleProvider);

    useEffect(() => {
        setIsChecking(false);
    }, [props.selectedLanguages]);

    useEffect(() => {
        if (isChecking) return;

        // Auto-start only for a single non-AI provider (Google/Gemini).
        if (singleProvider && !AI_PROVIDERS.includes(singleProvider)) {
            const errorNameKey = () => {
                switch (singleProvider) {
                    case 'localAiTranslator':
                        return 'localAiModalError';
                    case 'edgeLocalAiTranslator':
                        return 'edgeLocalAiModalError';
                    default:
                        return singleProvider + 'ModalError';
                }
            };

            const errorName = errorNameKey();

            if (props[errorName]) {
                setErrorModal(props[errorName]);
            } else {
                props.updateProviderHandler(singleProvider);
            }
        }
    }, [providers, isChecking, singleProvider]);

    const startTranslationHandler = (e) => {
        let targetElement = !e.target.classList.contains(`${prefix}-service-btn`) ? e.target.closest(`.${prefix}-service-btn`) : e.target;

        if (!targetElement) {
            return;
        }

        const dataService = targetElement.dataset && targetElement.dataset.service;
        if (!dataService) {
            return;
        }

        props.updateProviderHandler(dataService);
    };

    const errorModalHandler = (msg) => {
        setErrorModal(msg);
    };

    const closeErrorModal = (e) => {
        setErrorModal(false);
        if (singleProvider) {
            props.onDestory(e);
        }
    };

    return (
        <>
            {errorModal ? <ErrorModalBox message={errorModal} onDestroy={props.onDestory} onClose={closeErrorModal} Title='Linguator Multilingual AI Translation' prefix={prefix} /> :
            (requireProviderClick && <div id={`${prefix}-setting-modal-container`}>
                <div className={`${prefix}-setting-modal-content`}>
                    <SettingModalHeader
                        setSettingVisibility={props.onDestory}
                        prefix={prefix}
                    />
                    <SettingModalBody
                        startTranslationHandler={startTranslationHandler}
                        imgFolder={imgFolder}
                        prefix={prefix}
                        localAiModalError={props.localAiModalError}
                        edgeLocalAiModalError={props.edgeLocalAiModalError}
                        errorModalHandler={errorModalHandler}
                        chromeAiDownload={null}
                        setChromeAiDownload={null}
                        edgeAiDownload={null}
                        setEdgeAiDownload={null}
                        chromeAiDisabled={!!props.localAiModalError}
                        edgeLocalAiTranslatorDisabled={!!props.edgeLocalAiModalError}
                        checkAllProviderErrors={() => {}}
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
