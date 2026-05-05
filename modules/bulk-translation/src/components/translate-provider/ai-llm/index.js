import { updateProgressStatus, updateTranslatePostInfo, unsetPendingPost } from "../../../redux-store/features/actions.js";
import { selectProgressStatus, selectTargetContent, selectTranslatePostInfo } from "../../../redux-store/features/selectors.js";
import { store } from "../../../redux-store/store.js";
import storeTranslateString from "../../store-translate-strings/index.js";
import { __ } from "@wordpress/i18n";
import { requestAiBatch, chunkStringMap } from "./api-client.js";

function escapeHtml(text) {
    return String(text ?? "")
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;");
}

/** Match page translation: exact message plus Google AI Studio usage link. */
function geminiQuotaErrorHtml(message) {
    const view = __("View usage.", "translate-words");
    return `${escapeHtml(message)} <a href="https://aistudio.google.com/app/usage" target="_blank" rel="noopener noreferrer">${escapeHtml(view)}</a>`;
}

class AiLlmBulkTranslator {
    constructor({ sourceLang = "en", targetLangs = false, updateContent, totalPosts, storeDispatch, postId, prefix, updateDestoryHandler }) {
        this.textContentObject = selectTargetContent(store.getState(), postId);
        this.totalTranslatedLength = Object.keys(this.textContentObject).length;
        this.sourceLang = sourceLang;
        this.targetLangs = targetLangs;
        this.updateContent = updateContent;
        this.totalPosts = totalPosts;
        this.storeDispatch = storeDispatch;
        this.postId = postId;
        this.prefix = prefix;
        this.serviceProvider = store.getState().serviceProvider;
        this.stopTranslation = false;
        this.completedPostStatus = 0;
        updateDestoryHandler(() => {
            this.destroy();
        });
    }

    destroy = () => {
        this.stopTranslation = true;
    };

    getObjectType = () => {
        const info = store.getState().parentPostsInfo[this.postId];
        return info && info.editorType === "taxonomy" ? "term" : "post";
    };

    getRestUrl = () => {
        const base = (lmatBulkTranslationGlobal.bulkTranslateRouteUrl || "").replace(/\/$/, "");
        return `${base}/ai-translate-batch`;
    };

    updateProgressForChunk = (completedKeys, targetLang) => {
        if (this.totalTranslatedLength < 1) {
            return;
        }
        const completedPercentage = Math.min(100, (completedKeys / this.totalTranslatedLength) * 100);
        const progressBarCircular = document.querySelector(`.${this.prefix}-progress-bar-circular[data-id="${this.postId}_${targetLang}"]`);
        if (progressBarCircular) {
            const rounded = Math.min(100, Math.round(completedPercentage));
            progressBarCircular.querySelector(`.${this.prefix}-percentage`).innerHTML = `${rounded}%`;
            progressBarCircular.querySelector(`.${this.prefix}-progress`).style.strokeDasharray = `${rounded}, 100`;
        }
        const totalProgressBar = document.querySelector(`.${this.prefix}-overall-progress .${this.prefix}-progress`);
        if (totalProgressBar) {
            const totalProgress = Math.min(100, this.completedPostStatus + completedPercentage / this.totalPosts);
            totalProgressBar.style.width = `${totalProgress.toFixed(2)}%`;
            totalProgressBar.innerHTML = `${totalProgress.toFixed(2)}%`;
        }
    };

    getBatchConfig = () => {
        const maxTokens = Number(lmatBulkTranslationGlobal?.AIRequestMaxTokens);
        const batchSize = Number(lmatBulkTranslationGlobal?.AIRequestBatchSize);
        return {
            maxTokens: Number.isFinite(maxTokens) && maxTokens > 0 ? maxTokens : 500,
            concurrency: Number.isFinite(batchSize) && batchSize > 0 ? Math.min(10, Math.max(1, batchSize)) : 5,
        };
    };

    runWithConcurrency = async (items, concurrency, handler) => {
        const limit = Math.min(Math.max(1, concurrency), Math.max(1, items.length));
        let idx = 0;
        let firstError = null;

        const worker = async () => {
            while (idx < items.length) {
                if (firstError) return;
                const currentIndex = idx++;
                try {
                    // eslint-disable-next-line no-await-in-loop
                    await handler(items[currentIndex], currentIndex);
                } catch (e) {
                    firstError = e;
                    return;
                }
            }
        };

        await Promise.all(Array.from({ length: limit }, () => worker()));
        if (firstError) throw firstError;
    };

    async createLlmTranslator(targetLang, index) {
        if (this.stopTranslation || window.lmatBulkTranslationQuotaExceeded) {
            return;
        }

        this.completedPostStatus = selectProgressStatus(store.getState());

        if (!this.textContentObject || Object.keys(this.textContentObject).length === 0) {
            this.storeDispatch(unsetPendingPost(`${this.postId}_${targetLang}`));
            this.storeDispatch(updateProgressStatus(100 / this.totalPosts));
            this.storeDispatch(
                updateTranslatePostInfo({
                    [`${this.postId}_${targetLang}`]: {
                        status: "error",
                        messageClass: "error",
                        errorMessage: __("No content to translate", "translate-words"),
                        errorHtml: false,
                    },
                })
            );
        } else {
            this.storeDispatch(updateTranslatePostInfo({ [`${this.postId}_${targetLang}`]: { status: "running", messageClass: "" } }));
            this.startTime = new Date();

            try {
                const { maxTokens, concurrency } = this.getBatchConfig();
                const chunks = chunkStringMap(this.textContentObject, { maxTokens });
                const p = this.serviceProvider;
                const modelKey = "gemini_model";
                const selectedModel =
                    lmatBulkTranslationGlobal?.ai_models && lmatBulkTranslationGlobal.ai_models[modelKey]
                        ? String(lmatBulkTranslationGlobal.ai_models[modelKey])
                        : "";
                let done = 0;
                await this.runWithConcurrency(chunks, concurrency, async (chunk) => {
                    if (this.stopTranslation || window.lmatBulkTranslationQuotaExceeded) {
                        return;
                    }

                    const translations = await requestAiBatch({
                        provider: this.serviceProvider,
                        postId: this.postId,
                        objectType: this.getObjectType(),
                        sourceLang: this.sourceLang,
                        targetLang,
                        strings: chunk,
                        model: selectedModel,
                        restUrl: this.getRestUrl(),
                        nonce: lmatBulkTranslationGlobal.nonce,
                    });
                    if (Object.keys(translations).length === 0 && Object.keys(chunk).length > 0) {
                        throw new Error(__("The AI returned an empty translation response. Please try again.", "translate-words"));
                    }
                    for (const key of Object.keys(chunk)) {
                        const value = translations[key] !== undefined ? translations[key] : chunk[key];
                        storeTranslateString(this.postId, key, targetLang, value, this.serviceProvider, targetLang, this.storeDispatch);
                        done++;
                    }
                    this.updateProgressForChunk(done, targetLang);
                });

                if (!this.stopTranslation) {
                    this.storeDispatch(updateProgressStatus(100 / this.totalPosts));
                    const endTime = new Date();
                    const duration = endTime - this.startTime;
                    const tInfo = selectTranslatePostInfo(store.getState());
                    const previousDuration = (tInfo && tInfo[`${this.postId}_${targetLang}`] && tInfo[`${this.postId}_${targetLang}`].duration) || 0;
                    this.storeDispatch(
                        updateTranslatePostInfo({
                            [`${this.postId}_${targetLang}`]: { duration: previousDuration + duration },
                        })
                    );
                    await this.updateContent(targetLang);
                }
            } catch (err) {
                const msg = err && err.message ? err.message : __("Translation failed.", "translate-words");
                const isQuotaError = err?.code === "LLM_QUOTA_EXCEEDED";
                const isGeminiQuotaByMessage =
                    this.serviceProvider === "gemini" &&
                    /429|quota exceeded|rate limit|resource has been exhausted|too many requests/i.test(String(msg));
                const haltForQuota = isQuotaError || isGeminiQuotaByMessage;
                const showGeminiUsageLink = this.serviceProvider === "gemini" && (isQuotaError || isGeminiQuotaByMessage);

                if (haltForQuota) {
                    this.stopTranslation = true;
                    window.lmatBulkTranslationQuotaExceeded = true;
                }

                const errorAllowHtml = showGeminiUsageLink;
                const errorMessage = errorAllowHtml ? geminiQuotaErrorHtml(msg) : msg;

                this.storeDispatch(unsetPendingPost(`${this.postId}_${targetLang}`));
                this.storeDispatch(updateProgressStatus(100 / this.totalPosts));
                this.storeDispatch(
                    updateTranslatePostInfo({
                        [`${this.postId}_${targetLang}`]: {
                            status: "error",
                            messageClass: "error",
                            errorMessage,
                            errorHtml: false,
                            errorAllowHtml,
                        },
                    })
                );
            }
        }

        if (index < this.targetLangs.length - 1 && !this.stopTranslation && !window.lmatBulkTranslationQuotaExceeded) {
            await this.createLlmTranslator(this.targetLangs[index + 1], index + 1);
        }
    }

    async initTranslation() {
        if (this.textContentObject && Object.keys(this.textContentObject).length > 0 && this.targetLangs && this.targetLangs.length > 0 && !this.stopTranslation) {
            await this.createLlmTranslator(this.targetLangs[0], 0);
        } else if (this.targetLangs && this.targetLangs.length > 0 && !this.stopTranslation) {
            this.targetLangs.forEach((lang) => {
                this.storeDispatch(unsetPendingPost(`${this.postId}_${lang}`));
                this.storeDispatch(updateProgressStatus(100 / this.totalPosts));
                this.storeDispatch(
                    updateTranslatePostInfo({
                        [`${this.postId}_${lang}`]: {
                            status: "error",
                            messageClass: "error",
                            errorMessage: __("No content to translate", "translate-words"),
                            errorHtml: false,
                        },
                    })
                );
            });
        }
    }
}

export default AiLlmBulkTranslator;
