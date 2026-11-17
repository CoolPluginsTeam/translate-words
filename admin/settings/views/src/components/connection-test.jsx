import React, { useEffect, useRef, useState } from 'react';
import { createPortal } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __, sprintf } from '@wordpress/i18n';
import { getNonce } from '../utils';

export const openConnectionTest = (detail) => document.dispatchEvent(new CustomEvent('lmat:test-connection', { detail }));

/** Settings-only test dialog; each request uses saved credentials and the selected model. */
const ConnectionTestDialog = ({ provider, onClose }) => {
    const languages = Array.isArray(window.lmat_settings?.languages)
        ? window.lmat_settings.languages.filter((language) => language?.slug && language?.name && !language.is_default)
        : [];
    const dialog = useRef(null);
    const request = useRef(null);
    const [text, setText] = useState('Hello world');
    const [target, setTarget] = useState(languages[0]?.slug || '');
    const [model, setModel] = useState(provider.model || '');
    const [busy, setBusy] = useState(false);
    const [result, setResult] = useState(null);
    const [error, setError] = useState('');
    const local = ['chrome_local_ai', 'edge_local_ai'].includes(provider.key);
    const models = provider.models || {};
    const modelChanged = !local && model !== (provider.model || '');

    useEffect(() => {
        const previousFocus = document.activeElement;
        dialog.current.showModal();
        return () => {
            request.current?.abort();
            previousFocus?.focus();
        };
    }, []);

    const reset = () => { setResult(null); setError(''); };
    const run = async () => {
        if (busy) return;
        reset();
        if (!languages.some((language) => language.slug === target)) { setError({ summary: __('Add another language in Linguator before running a translation test.', 'translate-words') }); return; }
        if (!text.trim()) { setError({ summary: __('Please enter sample text to test translation.', 'translate-words') }); return; }
        setBusy(true);
        const controller = new AbortController();
        request.current = controller;
        let translator;
        try {
            let next;
            if (local) {
                const framework = window.ChromeAISetupFramework;
                if (!framework || !framework.isApiPresent()) throw new Error(__('Translator API not available in this browser.', 'translate-words'));
                const started = performance.now();
                const language = languages.find((item) => item.slug === target);
                const targetCode = (language.locale || language.slug).replace(/_/g, '-');
                const status = await framework.getLanguagePairStatus('en', targetCode);
                if (!['available', 'readily'].includes(status)) throw new Error(__('Please install this language pack in the AI setup card first.', 'translate-words'));
                if (controller.signal.aborted) return;
                translator = await framework.createTranslator('en', targetCode);
                if (translator.ready) await translator.ready;
                if (controller.signal.aborted) return;
                const translation = await translator.translate(text.trim());
                if (!translation?.trim()) throw new Error(__('The provider returned an empty translation.', 'translate-words'));
                next = { translation, ms: Math.round(performance.now() - started) };
            } else {
                next = await apiFetch({ path: '/lmat/v1/settings/test-connection', method: 'POST', headers: { 'X-WP-Nonce': getNonce() }, signal: controller.signal, data: { provider: provider.key, model, target_language: target, text: text.trim() } });
            }
            if (!controller.signal.aborted) setResult(next);
        } catch (e) {
            if (!controller.signal.aborted) {
                const data = e?.data || {};
                setError({
                    summary: data.summary || e.message || __('The translation test failed. Please try again.', 'translate-words'),
                    hint: data.hint || '',
                    detail: data.detail && data.detail !== data.summary ? data.detail : '',
                });
            }
        } finally {
            translator?.destroy?.();
            if (!controller.signal.aborted) setBusy(false);
        }
    };

    return createPortal(
        <dialog className="lmat-test-dialog" ref={dialog} aria-labelledby="lmat-test-title" onCancel={onClose} onClick={(event) => { if (event.target === dialog.current) onClose(); }}>
            <div className="lmat-test-header">
                <h2 id="lmat-test-title">{__('Test Connection', 'translate-words')}</h2>
                <button type="button" className="lmat-test-close" aria-label={__('Close', 'translate-words')} onClick={onClose}>×</button>
            </div>
            <div className="lmat-test-body">
                <strong>{provider.name}</strong>
                <p className="lmat-test-muted">{__('Translate a sample string to check the real result before translating your content.', 'translate-words')}</p>
                <div className="lmat-test-languages">
                    {local ? <span>{__('English', 'translate-words')}</span> : <label>{__('Model', 'translate-words')}<select value={model} disabled={busy} onChange={(e) => { setModel(e.target.value); reset(); }}>
                        {provider.key !== 'gemini' ? <option value="">{__('Automatic (provider order)', 'translate-words')}</option> : null}
                        {Object.entries(models).map(([id, name]) => <option key={id} value={id}>{name}</option>)}
                        {model && !Object.prototype.hasOwnProperty.call(models, model) ? <option value={model}>{model}</option> : null}
                    </select></label>}
                    <span aria-hidden="true">→</span>
                    <label>{__('Translate into', 'translate-words')}<select value={target} disabled={busy || !languages.length} onChange={(e) => { setTarget(e.target.value); reset(); }}>
                        {!languages.length ? <option value="">{__('No target languages added', 'translate-words')}</option> : null}
                        {languages.map((language) => <option key={language.slug} value={language.slug}>{language.name}</option>)}
                    </select></label>
                </div>
                {modelChanged ? (
                    <div className="lmat-test-model-notice" role="status">
                        <strong>{__('Model changed', 'translate-words')}</strong>
                        <span>{__('This model is selected for this test only. Select and save it in Translation settings to use it for future translations.', 'translate-words')}</span>
                    </div>
                ) : null}
                {!languages.length ? <p role="status">{__('Add another language in Linguator before running a translation test.', 'translate-words')}</p> : null}
                <div className="lmat-test-grid">
                    <label>{__('Sample text (English)', 'translate-words')}<textarea rows="3" maxLength={200} value={text} disabled={busy} onChange={(e) => { setText(e.target.value); reset(); }} /><span className="lmat-test-muted">{text.length}/200</span></label>
                    <div><strong>{__('Translation', 'translate-words')}</strong><div className={`lmat-test-output${error ? ' is-error' : result ? ' is-success' : ''}`} role="status" aria-live="polite">{error ? __('The translation test failed.', 'translate-words') : result?.translation || (busy ? __('Translating…', 'translate-words') : __('The translation will appear here.', 'translate-words'))}</div></div>
                </div>
                <div className="lmat-test-actions"><button type="button" className="button button-primary" disabled={busy || !text.trim() || !target} onClick={run}>{busy ? __('Testing…', 'translate-words') : __('Run test', 'translate-words')}</button>
                    {result ? <span className="lmat-test-muted">{sprintf( /* translators: %d: request time in milliseconds */ __('Completed in %d ms.', 'translate-words'), result.ms)} {result.model || ''}</span> : null}
                </div>
                {error ? (
                    <div className="lmat-test-error" role="alert">
                        <p>{error.summary}</p>
                        {error.hint ? <p className="lmat-test-error-hint">{error.hint}</p> : null}
                        {error.detail ? <p className="lmat-test-error-detail">{error.detail}</p> : null}
                    </div>
                ) : null}
            </div>
        </dialog>, document.body
    );
};

/** Mounted only by the Translation settings provider list. */
export default function ConnectionTests() {
    const [provider, setProvider] = useState(null);
    useEffect(() => {
        const open = (event) => { if (event.detail?.key) setProvider(event.detail); };
        document.addEventListener('lmat:test-connection', open);
        return () => document.removeEventListener('lmat:test-connection', open);
    }, []);
    return provider ? <ConnectionTestDialog key={provider.key} provider={provider} onClose={() => setProvider(null)} /> : null;
}
