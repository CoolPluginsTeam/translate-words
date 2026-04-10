import { select, dispatch } from "@wordpress/data";
import SaveTranslation from "../../store-translated-string/index.js";
import StoreTimeTaken from "../../store-time-taken/index.js";
import { requestAiBatch, chunkStringMap } from "../../../../../bulk-translation/src/components/translate-provider/ai-llm/api-client.js";
import { __ } from "@wordpress/i18n";

/**
 * @param {string} providerId openai|gemini|anthropic
 * @returns {(props: Object) => Promise<void>}
 */
export default function createAiLlmPageTranslator(providerId) {
    return async (props) => {
        const { sourceLang, targetLang, translateStatusHandler } = props;
        const AllowedMetaFields = select("block-lmatPageTranslation/translate").getAllowedMetaFields();
        const errorNoticeClass = "lmat-page-translation-ai-error-notice";

        const clearErrorNotice = () => {
            const oldNotice = document.querySelector(`.${errorNoticeClass}`);
            if (oldNotice) {
                oldNotice.remove();
            }
        };

        const showErrorNotice = (message) => {
            clearErrorNotice();
            const body = document.querySelector("#lmat_page_translation_strings_model .modal-body");
            if (!body) {
                return;
            }
            const notice = document.createElement("div");
            notice.className = `${errorNoticeClass} notice inline notice-error`;
            notice.innerHTML = `<p>${message}</p>`;
            body.prepend(notice);
        };

        const entries = select("block-lmatPageTranslation/translate").getTranslationEntries();
        const entryById = {};
        const strings = {};
        entries.forEach((row) => {
            if (!row.source || String(row.source).trim() === "") {
                return;
            }
            entryById[row.id] = row;
            strings[row.id] = row.filteredString || row.source;
        });

        const stringContainer = jQuery("#lmat_page_translation_strings_model .modal-content .lmat_page_translation_string_container");
        const progressBar = jQuery("#lmat_page_translation_strings_model .lmat_page_translation_translate_progress");

        if (Object.keys(strings).length === 0) {
            clearErrorNotice();
            translateStatusHandler(false);
            return;
        }

        if (stringContainer[0] && stringContainer[0].scrollHeight > 100) {
            progressBar.fadeIn("slow");
        }

        const startTime = new Date().getTime();
        const restUrl = lmatPageTranslationGlobal.ai_batch_translate_url;
        const nonce = lmatPageTranslationGlobal.rest_nonce;
        const postId = lmatPageTranslationGlobal.current_post_id;
        const objectType = lmatPageTranslationGlobal.editor_type === "taxonomy" ? "term" : "post";

        const finish = () => {
            progressBar.fadeOut("slow");
            translateStatusHandler(false);
        };

        try {
            clearErrorNotice();
            const chunks = chunkStringMap(strings);
            let totalChars = 0;
            let totalStrings = 0;

            for (const chunk of chunks) {
                const translations = await requestAiBatch({
                    provider: providerId,
                    postId,
                    objectType,
                    sourceLang,
                    targetLang,
                    strings: chunk,
                    restUrl,
                    nonce,
                });

                if (Object.keys(translations).length === 0 && Object.keys(chunk).length > 0) {
                    throw new Error(__("The AI returned an empty translation response. Please try again.", "translate-words"));
                }

                for (const key of Object.keys(chunk)) {
                    const row = entryById[key];
                    if (!row) {
                        continue;
                    }
                    const t = translations[key] !== undefined ? translations[key] : chunk[key];
                    SaveTranslation({
                        type: row.type,
                        key: row.id,
                        translateContent: t,
                        source: row.source,
                        provider: providerId,
                        AllowedMetaFields,
                    });
                    const src = row.source || "";
                    totalChars += src.trim().length;
                    totalStrings += src.trim().split(/(?<=[.!?]+)\s+/).filter(Boolean).length;
                }
            }

            dispatch("block-lmatPageTranslation/translate").translationInfo({
                targetStringCount: totalStrings,
                targetWordCount: entries.reduce((acc, row) => {
                    const s = (row.source || "").trim();
                    return acc + s.split(/\s+/).filter((word) => /[^\p{L}\p{N}]/.test(word)).length;
                }, 0),
                targetCharacterCount: totalChars,
                translateStatus: true,
                provider: providerId,
            });

            StoreTimeTaken({ prefix: providerId, start: startTime, end: new Date().getTime(), translateStatus: true });
        } catch (e) {
            StoreTimeTaken({ prefix: providerId, start: startTime, end: new Date().getTime(), translateStatus: false });
            const errorMessage = e?.message || __("Translation failed. Please try again.", "translate-words");
            showErrorNotice(errorMessage);
            // eslint-disable-next-line no-console
            console.error(e);
        }

        setTimeout(finish, 400);
    };
}
