import React, { useEffect, useState, useRef } from "react";
import FilterTargetContent from "../component/filter-target-content/index.js";
import { __ } from "@wordpress/i18n";
import { select, dispatch } from "@wordpress/data";
import { Fragment } from "@wordpress/element";
import TranslateService from "../component/translate-provider/index.js";
import DOMPurify from "dompurify";

const StringPopUpBody = (props) => {

    const { service: service } = props;
    const translateContent = select("block-lmatPageTranslation/translate").getTranslationEntries();
    const StringModalBodyNotice = props.stringModalBodyNotice;

    const imgFolder = lmatPageTranslationGlobal.lmat_url + 'assets/images/';


    const tableRef = useRef(null);
    const [translationError, setTranslationError] = useState(null);

    useEffect(() => {
        const onTranslationError = (e) => {
            const d = e?.detail;
            if (d?.recoverable && typeof d.onTranslateAgain === "function" && typeof d.onContinue === "function") {
                setTranslationError({
                    recoverable: true,
                    message: typeof d.messagePlain === "string" ? d.messagePlain : "",
                    html: typeof d.html === "string" ? d.html : "",
                    onTranslateAgain: d.onTranslateAgain,
                    onContinue: d.onContinue,
                });
                return;
            }
            const msg = d?.message;
            if (typeof msg === "string" && msg.trim() !== "") {
                setTranslationError({
                    message: msg,
                    link: d?.link || null,
                });
            }
        };
        const onTranslationErrorClear = () => setTranslationError(null);

        document.addEventListener('lmat-page-translation:translation-error', onTranslationError);
        document.addEventListener('lmat-page-translation:translation-error-clear', onTranslationErrorClear);

        return () => {
            document.removeEventListener('lmat-page-translation:translation-error', onTranslationError);
            document.removeEventListener('lmat-page-translation:translation-error-clear', onTranslationErrorClear);
        };
    }, []);

    useEffect(() => {
        setTranslationError(null);
    }, [props.modalRender]);

    /** While a non-recoverable error is shown, disable provider translate buttons. */
    useEffect(() => {
        if (!translationError || translationError.recoverable) {
            return undefined;
        }
        const root = document.getElementById(`lmat_page_translation_${props.service}_translate_element`);
        if (!root) {
            return undefined;
        }
        const disableButtons = () => {
            root.querySelectorAll('button').forEach((el) => {
                el.disabled = true;
            });
        };
        disableButtons();
        const observer = new MutationObserver(() => disableButtons());
        observer.observe(root, { childList: true, subtree: true });
        return () => {
            observer.disconnect();
            root.querySelectorAll('button').forEach((el) => {
                el.disabled = false;
            });
        };
    }, [translationError, props.service]);

    const saveFilteredString = (type, id, filteredString) => {
        const action = `${type}SaveFiltered`;

        dispatch('block-lmatPageTranslation/translate')[action](filteredString, id);
    }



    useEffect(() => {

        if (['yandex', 'google'].includes(props.service)) {
            document.documentElement.setAttribute('translate', 'no');
            document.body.classList.add('notranslate');
        }

        /**
         * Calls the translate service provider based on the service type.
         * For example, it can call services like yandex Translate.
        */
        const service = props.service;
        const id = `lmat_page_translation_${service}_translate_element`;

        const translateContent = select('block-lmatPageTranslation/translate').getTranslationEntries();

        if (translateContent.length > 0 && props.postDataFetchStatus) {
            const ServiceSetting = TranslateService({ Service: service });
            const run = ServiceSetting.Provider({ sourceLang: props.sourceLang, targetLang: props.targetLang, translateStatusHandler: props.translateStatusHandler, ID: id, translateStatus: props.translateStatus, modalRenderId: props.modalRender, destroyUpdateHandler: props.updateDestroyHandler, translationAbortSignal: props.translationAbortSignal, service: service });
            if (run && typeof run.then === "function") {
                run.catch(() => {});
            }
        }
    }, [props.modalRender, props.postDataFetchStatus]);

    const udpateFilterdContent = (div) => {
        const tempElement = document.createElement('div');
        tempElement.innerHTML = div.innerHTML;
        const childNodes = tempElement.childNodes;

        if (childNodes.length > 0) {
            for (let i = 0; i < childNodes.length; i++) {
                const childNode = childNodes[i];
                if (childNode.nodeType === 3) {
                    continue;
                } else {
                    const childNodes = childNode.childNodes;
                    if (childNodes.length > 0) {
                        if (!childNode.classList.contains('lmat-page-translation-notraslate-tag')) {
                            const textContent = udpateFilterdContent(childNode);
                            childNode.outerHTML = textContent;
                        } else {
                            const textContent = udpateFilterdContent(childNode);
                            childNode.innerHTML = textContent;
                        }
                    }
                }
            }
        }

        return tempElement.innerHTML;
    }

    const getTranslation = (data) => {
        let originalTranslation = false;

        if (data.translatedData) {
            if (service && data.translatedData[service]) {
                originalTranslation = data.translatedData[service];
            } else if (data.translatedData[props.service]) {
                originalTranslation = data.translatedData[props.service];
            }
        }

        return originalTranslation;
    }

    // Update the cell display logic in the render section
    const getCellContent = (index, data, cellKey) => {
        const translation = getTranslation(data);

        if (translation) {
            return translation;
        } else {
            if (['google', 'yandex', 'localAiTranslator', 'edgeLocalAiTranslator', 'gemini', ...Object.keys(window.lmatPageTranslationGlobal?.external_providers || {})].includes(props.service)) {
                // Use FilterTargetContent for pending translations with supported services
                if (props.translatePendingStatus && !props.service.includes('_ai')) {
                    if (data.filteredString) {
                        return <span dangerouslySetInnerHTML={{ __html: DOMPurify.sanitize(data.filteredString) }} style={{ whiteSpace: 'pre-wrap' }}></span>;
                    }

                    return <FilterTargetContent service={props.service} content={data.source || ''} contentKey={data.id} item={data} saveFilteredString={saveFilteredString} />;
                }
                return data.source || '';
            } else {
                return (
                    <img
                        src={imgFolder + 'plus.png'}
                        alt={__("Add translation", "translate-words")}
                        className="lmat-page-translation-add-translation-icon"
                    />
                );
            }
        }
    };

    return (
        <div className="modal-body">
            {translateContent.length > 0 && props.postDataFetchStatus ?
                <>
                    {StringModalBodyNotice && <div className="lmat-page-translation-body-notice-wrapper"><StringModalBodyNotice /></div>}
                    {props.translatePendingStatus && (
                        <div
                            className="lmat_page_translation_translate_progress"
                            key={props.modalRender}
                            role="status"
                            aria-live="polite"
                        >
                            {__("Automatic translation is in progress", 'translate-words')}
                            <span className="lmat_page_translation_translate_progress__dots" aria-hidden="true">
                                <span>.</span>
                                <span>.</span>
                                <span>.</span>
                            </span>
                            <br />
                            {__("It will take few minutes, enjoy ☕ coffee in this time!", 'translate-words')}
                            <br /><br />
                            {__("Please do not leave this window or browser tab while translation is in progress", 'translate-words')}
                            <span className="lmat_page_translation_translate_progress__dots" aria-hidden="true">
                                <span>.</span>
                                <span>.</span>
                                <span>.</span>
                            </span>
                        </div>
                    )}
                    {translationError && (
                        <div
                            className="lmat_page_translation_error_popup_overlay"
                            role="alertdialog"
                            aria-modal="true"
                            aria-labelledby="lmat_page_translation_error_popup_title"
                        >
                            <div className="lmat_page_translation_error_popup_card notice notice-error">
                                {translationError.recoverable && translationError.html ? (
                                    <div
                                        className="lmat_page_translation_error_popup_html"
                                        dangerouslySetInnerHTML={{
                                            __html: DOMPurify.sanitize(translationError.html, { ADD_ATTR: ["target", "rel", "data-clipboard-text"] }),
                                        }}
                                    />
                                ) : (
                                    <p id="lmat_page_translation_error_popup_title" className="lmat_page_translation_error_popup_message">
                                        {translationError?.message || ""}
                                        {translationError?.link?.href && (
                                            <>
                                                {" "}
                                                <a href={translationError.link.href} target="_blank" rel="noreferrer">
                                                    {translationError?.link?.text || translationError.link.href}
                                                </a>
                                            </>
                                        )}
                                    </p>
                                )}
                                {translationError.recoverable && (
                                    <div className="lmat_page_translation_ai_error_buttons">
                                        <button
                                            type="button"
                                            className="button lmat_page_translation_ai_error_btn_translate"
                                            onClick={() => {
                                                if (typeof translationError.onTranslateAgain === "function") {
                                                    translationError.onTranslateAgain();
                                                }
                                            }}
                                        >
                                            {__("Translate", "translate-words")}
                                        </button>
                                        <button
                                            type="button"
                                            className="button button-primary lmat_page_translation_ai_error_btn_continue"
                                            onClick={() => {
                                                if (typeof translationError.onContinue === "function") {
                                                    translationError.onContinue();
                                                }
                                            }}
                                        >
                                            {__("Continue", "translate-words")}
                                        </button>
                                    </div>
                                )}
                            </div>
                        </div>
                    )}
                    <div className={`translator-widget ${service}`} style={{ display: 'flex', alignItems: 'center' }}>
                        <h3 className="choose-lang">{TranslateService({ Service: props.service }).heading} <span className="dashicons-before dashicons-translation"></span></h3>

                        <div className={`lmat_page_translation_translate_element_wrapper ${props.translateStatus ? 'translate-completed' : ''}`}>
                            <div id={`lmat_page_translation_${props.service}_translate_element`}></div>
                        </div>
                    </div>

                    <div className="lmat_page_translation_string_section">
                        <div className="lmat_page_translation_string_container">
                            <table className="scrolldown" id="stringTemplate" ref={tableRef}>
                                <thead>
                                    <tr>
                                        <th className="notranslate">{__("S.No", 'translate-words')}</th>
                                        <th className="notranslate">{__("Source Text", 'translate-words')}</th>
                                        <th className="notranslate">{__("Translation", 'translate-words')}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                {props.postDataFetchStatus &&
                                    <>
                                        {translateContent.map((data, index) => {
                                            const cellKey = `${index}_2`;
                                            let originalTranslation = '';

                                            if (data.translatedData) {
                                                if (service && data.translatedData[service]) {
                                                    originalTranslation = data.translatedData[service];
                                                } else if (data.translatedData[props.service]) {
                                                    originalTranslation = data.translatedData[props.service];
                                                }
                                            }


                                            return (
                                                <Fragment key={index + props.translatePendingStatus}>
                                                    {undefined !== data.source && data.source.trim() !== '' &&
                                                        <>
                                                            <tr key={index + 'tr' + props.translatePendingStatus + (data.filteredString ? 'filteredString' : '')}>
                                                                <td>{index + 1}</td>
                                                                <td data-source="source_text">{data.source}</td>
                                                                <td
                                                                    data-key={data.id}
                                                                    data-string-type={data.type}
                                                                    translate="yes"
                                                                    className={`${!originalTranslation ? 'lmat-page-translation-empty-translation-cell' : ''} ${(props.translatePendingStatus && (props.service === 'gemini' || Boolean(window.lmatPageTranslationGlobal?.external_providers?.[props.service]))) ? 'notranslate' : 'translate'}`}
                                                                    data-translate-status={props.translatePendingStatus ? 'pending' : 'translated'}
                                                                >
                                                                    {getCellContent(index, data, cellKey)}
                                                                </td>
                                                            </tr>
                                                        </>
                                                    }
                                                </Fragment>
                                            );
                                        })}
                                    </>
                                }
                                </tbody>
                            </table>
                        </div>
                    </div>
                </> :
                props.postDataFetchStatus ?
                    <p>{__('No strings are available for translation', 'translate-words')}</p> :

                    <div className="lmat-page-translation-skeleton-loader-wrapper">
                        <div className="translate-widget">
                            <div className="lmat-page-translation-skeleton-loader-mini"></div>
                            <div className="lmat-page-translation-skeleton-loader-mini"></div>
                        </div>
                        <table>
                            <thead>
                                <tr>
                                    <th className="notranslate">{__("S.No", 'translate-words')}</th>
                                    <th className="notranslate">{__("Source Text", 'translate-words')}</th>
                                    <th className="notranslate">{__("Translation", 'translate-words')}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {[...Array(10)].map((_, index) => {
                                    return (
                                        <tr key={index}>
                                            <td><div className="lmat-page-translation-skeleton-loader-mini"></div></td>
                                            <td><div className="lmat-page-translation-skeleton-loader-mini"></div></td>
                                            <td><div className="lmat-page-translation-skeleton-loader-mini"></div></td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
            }
        </div>
    );
}

export default StringPopUpBody;
