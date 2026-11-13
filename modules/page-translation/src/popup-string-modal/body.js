import React, { useEffect, useState, useRef } from "react";
import FilterTargetContent from "../component/filter-target-content/index.js";
import { __ } from "@wordpress/i18n";
import { select, dispatch } from "@wordpress/data";
import { Fragment } from "@wordpress/element";
import TranslateService from "../component/translate-provider/index.js";
import ReactDOM from "react-dom";
import { InfoPopup } from "../component/RightPopup/index.js";
import SaveTranslation from '../component/store-translated-string/index.js';
import DOMPurify from "dompurify";

const StringPopUpBody = (props) => {

    const { service: service } = props;
    const translateContent = select("block-lmatPageTranslation/translate").getTranslationEntries();
    const StringModalBodyNotice = props.stringModalBodyNotice;

    const imgFolder = lmatPageTranslationGlobal.lmat_url + 'assets/images/';


    // Add refs and state for popup
    const tableRef = useRef(null);
    const [popupInfo, setPopupInfo] = useState(null);
    const [editingCells, setEditingCells] = useState([]);
    const [editingValues, setEditingValues] = useState({});
    const textareaRefs = useRef({});
    const [activePopupCell, setActivePopupCell] = useState(null);
    const [savedValues, setSavedValues] = useState({});
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



    // Update handleTdClick to check for AI service
    const handleTdClick = (event, colIndex, rowIndex, data) => {
        const translationEntry = select("block-lmatPageTranslation/translate").getTranslationEntry({ id: data.id, type: data.type });

        if (colIndex !== 2 || !translationEntry) return;

        const cellKey = `${rowIndex}_${colIndex}`;

        let translation = '';
        if (translationEntry.translatedData && translationEntry.translatedData[service]) {
            translation = translationEntry.translatedData[service];
        } else if (translationEntry.translatedData && translationEntry.translatedData[props.service]) {
            translation = translationEntry.translatedData[props.service];
        } else if (translationEntry.filteredString) {
            translation = translationEntry.filteredString;
        } else {
            translation = translationEntry.source || '';
        }

        // Only set editing state if not already editing this cell
        const isAlreadyEditing = editingCells.some(
            cell => cell.row === rowIndex && cell.col === colIndex
        );

        if (!isAlreadyEditing) {
            setEditingCells([{ row: rowIndex, col: colIndex }]);
            setEditingValues({
                [cellKey]: translation
            });
        }
        setActivePopupCell({ row: rowIndex, col: colIndex });

        if (!tableRef.current) return;
        const tdRect = event.currentTarget.getBoundingClientRect();
        const ths = tableRef.current.querySelectorAll("thead th");
        const thText = ths[colIndex] ? ths[colIndex].textContent : '';
        const stringContainer = document.querySelector('.lmat_page_translation_string_container');
        const stringContainerRect = stringContainer.getBoundingClientRect();
        const difference = stringContainerRect.bottom - tdRect.bottom;
        const differencetop = tdRect.top - stringContainerRect.top;

        // Calculate popup position relative to the clicked cell
        const newPopupInfo = {
            left: tdRect.right + window.scrollX,
            top: tdRect.top + window.scrollY - 35,
            value: data,
            header: thText,
        };

        // Adjust position if near bottom of container
        if (difference < 50) {
            newPopupInfo.top = tdRect.top + window.scrollY - 130;
            newPopupInfo.bottom = 50;
        }
        // Adjust position if near top of container
        if (differencetop < 35) {
            newPopupInfo.top = stringContainerRect.top + window.scrollY;
            newPopupInfo.bottom = 35;
        }

        setPopupInfo(newPopupInfo);

    };

    // Update closePopup to clear all editing states
    const closePopup = () => {
        setEditingCells([]);
        setEditingValues({});
        setPopupInfo(null);
        setActivePopupCell(null);
    };

    // Update handleTextareaInput to use dynamic positioning
    const handleTextareaInput = (e, row, col) => {
        e.target.style.height = 'auto';
        e.target.style.height = e.target.scrollHeight + 'px';
    };

    useEffect(() => {
        if (!popupInfo) {
            setEditingCells([]);
        }
    }, [popupInfo]);

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

    // Add this helper function at the top level of your component
    const getTranslationProviders = (service) => {
        if (service) {
            return [service];
        }
    };

    // Save handler for InfoPopup
    const handleSave = () => {
        const cell = activePopupCell;
        if (cell) {
            const cellKey = `${cell.row}_${cell.col}`;
            const newValue = textareaRefs?.current[cellKey]?.innerText || '';
            const rowData = translateContent[cell.row];

            if (newValue && newValue.trim() !== '') {
                // Save non-empty values
                setSavedValues(prev => ({ ...prev, [cellKey]: newValue }));

                if (rowData?.id) {
                    const providers = getTranslationProviders(service);

                    // Save translation for each provider
                    providers.forEach(provider => {
                        SaveTranslation({
                            type: rowData.type || 'content',
                            key: rowData.id,
                            translateContent: newValue,
                            source: rowData.source,
                            provider: provider,
                            AllowedMetaFields: props.AllowedMetaFields || {}
                        });
                    });
                }
            } else {
                // For empty values, clear both local state and call SaveTranslation with empty string
                setSavedValues(prev => {
                    const newSavedValues = { ...prev };
                    delete newSavedValues[cellKey];
                    return newSavedValues;
                });

                if (rowData?.id) {
                    const providers = getTranslationProviders(service);

                    // Save translation for each provider
                    providers.forEach(provider => {
                        SaveTranslation({
                            type: rowData.type || 'content',
                            key: rowData.id,
                            translateContent: '', // Save empty string to clear the translation
                            source: rowData.source,
                            provider: provider,
                            AllowedMetaFields: props.AllowedMetaFields || {}
                        });
                    });
                }
            }

            // Remove the current cell from editingCells so it renders as a <td>
            setEditingCells(prev => prev.filter(c => !(c.row === cell.row && c.col === cell.col)));

            // Move to next row if exists
            const nextRow = cell.row + 1;
            if (nextRow < translateContent.length) {
                const nextRowData = translateContent[nextRow];
                if (nextRowData?.source?.trim()) {
                    const nextCellKey = `${nextRow}_${cell.col}`;
                    let initialNextTranslation;
                    if (savedValues[nextCellKey] !== undefined) {
                        initialNextTranslation = savedValues[nextCellKey];
                    } else {
                        let trans = '';
                        if (nextRowData.translatedData) {
                            if (service && nextRowData.translatedData[service]) {
                                trans = nextRowData.translatedData[service];
                            } else if (nextRowData.translatedData[props.service]) {
                                trans = nextRowData.translatedData[props.service];
                            }
                        }
                        initialNextTranslation = trans || nextRowData.source || '';
                    }

                    setEditingCells(prev => [...prev, { row: nextRow, col: cell.col }]);
                    setEditingValues(prev => ({ ...prev, [nextCellKey]: initialNextTranslation }));
                    setActivePopupCell({ row: nextRow, col: cell.col });

                    // Update popup position for next row
                    setTimeout(() => {
                        const table = tableRef.current;
                        const nextTd = table?.querySelector(`tbody tr:nth-child(${nextRow + 1}) td:nth-child(${cell.col + 1})`);
                        if (nextTd) {
                            const tdRect = nextTd.getBoundingClientRect();
                            setPopupInfo({
                                left: tdRect.right + window.scrollX,
                                top: tdRect.top + window.scrollY,
                                value: nextRowData,
                                header: table.querySelectorAll("thead th")[cell.col]?.textContent || '',
                            });
                        }
                    }, 0);
                    return;
                }
            }

            setPopupInfo(null);
        }
    };

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
        const savedEdit = savedValues[cellKey];
        const translation = getTranslation(data);

        if (savedEdit !== undefined) {
            return savedEdit;
        } else if (translation) {
            return translation;
        } else {
            if (['google', 'yandex', 'localAiTranslator', 'edgeLocalAiTranslator', 'gemini'].includes(props.service)) {
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

                                            const isEditingThisCell = editingCells.some(cell => cell.row === index && cell.col === 2);

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
                                                                    onClick={(e) => {
                                                                        handleTdClick(e, 2, index, data);
                                                                    }}
                                                                    style={{ cursor: 'pointer' }}
                                                                    translate={(savedValues[cellKey] && props.service === 'google' || savedValues[cellKey] && props.service === 'yandex') ? "no" : (props.translatePendingStatus && ['google', 'yandex', 'localAiTranslator', 'edgeLocalAiTranslator'].includes(props.service) && !props.service.includes('_ai')) ? "yes" : 'yes'}
                                                                    className={`${!isEditingThisCell && !savedValues[cellKey] && !originalTranslation ? 'lmat-page-translation-empty-translation-cell' : ''} ${savedValues[cellKey] && (props.service === 'localAiTranslator' || props.service === 'edgeLocalAiTranslator') || savedValues[cellKey] && props.service === 'google' ? 'notranslate' : (props.translatePendingStatus && props.service === 'gemini') ? 'notranslate' : (props.translatePendingStatus && ['google', 'yandex', 'localAiTranslator', 'edgeLocalAiTranslator'].includes(props.service) && !props.service.includes('_ai')) ? 'translate' : 'translate'} ${isEditingThisCell ? 'lmat-page-translation-editing-cell' : ''}`}
                                                                    data-translate-status={props.translatePendingStatus ? 'pending' : 'translated'}
                                                                >
                                                                    {isEditingThisCell ? (
                                                                        <div
                                                                            contentEditable="true"
                                                                            className="lmat-page-translation-content-editable"
                                                                            ref={el => {
                                                                                textareaRefs.current[cellKey] = el;
                                                                                if (el) {
                                                                                    el.style.height = 'auto';
                                                                                    el.style.height = el.scrollHeight + 'px';
                                                                                }
                                                                            }}
                                                                            onFocus={() => setActivePopupCell({ row: index, col: 2 })}
                                                                            onInput={e => handleTextareaInput(e, index, 2)}
                                                                            style={{ width: '100%', minHeight: 40, resize: 'vertical' }}
                                                                            dangerouslySetInnerHTML={{ __html: DOMPurify.sanitize(editingValues[cellKey] || '') }}
                                                                        />
                                                                    ) : (
                                                                        getCellContent(index, data, cellKey)
                                                                    )}
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
            {/* Popup rendered via portal for correct positioning */}
            {popupInfo &&
                ReactDOM.createPortal(
                    <InfoPopup
                        left={popupInfo.left}
                        top={popupInfo.top}
                        onClose={closePopup}
                        onCopy={() => {
                            const cell = activePopupCell;
                            if (cell) {
                                const sourceText = translateContent[cell.row]?.source || '';
                                setEditingValues(prev => ({
                                    ...prev,
                                    [`${cell.row}_${cell.col}`]: sourceText
                                }));
                                setTimeout(() => {
                                    const textarea = textareaRefs.current[`${cell.row}_${cell.col}`];
                                    if (textarea) textarea.focus();
                                }, 0);
                            }
                        }}
                        onSave={handleSave}
                    />,
                    document.body
                )
            }
        </div>
    );
}

export default StringPopUpBody;
