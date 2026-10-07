import React from 'react'
import { Button, Input, Label } from '@bsf/force-ui'
import apiFetch from "@wordpress/api-fetch"
import { toast } from 'sonner'
import { __ } from '@wordpress/i18n'
import { getNonce } from '../utils'

/**
 * ChatGPT Web / Gemini Web session cookie fields (each value pasted separately,
 * combined into a Cookie header server-side).
 */
export const webSessionProviders = {
    chatgpt_web: {
        modelKey: 'chatgpt_web_model',
        defaultModel: 'gpt-5-5-instant',
        heading: __('ChatGPT Web cookies', 'translate-words'),
        modelHeading: __('Select ChatGPT Web Model', 'translate-words'),
        help: __('Copy each value from DevTools → Network → a chatgpt.com request → Request Headers → Cookie.', 'translate-words'),
        missing: __('Please add the ChatGPT Web session-token to continue.', 'translate-words'),
        fields: [
            { key: 'chatgpt_web_session_token', label: '__Secure-next-auth.session-token', placeholder: __('Paste session-token value only', 'translate-words') },
            { key: 'chatgpt_web_cf_clearance', label: __('cf_clearance (optional)', 'translate-words'), placeholder: __('Paste cf_clearance value (optional)', 'translate-words') },
        ],
    },
    gemini_web: {
        modelKey: 'gemini_web_model',
        defaultModel: 'gemini-3.5-flash',
        heading: __('Gemini Web cookies', 'translate-words'),
        modelHeading: __('Select Gemini Web Model', 'translate-words'),
        help: __('Copy each value from DevTools → Network → a gemini.google.com request → Request Headers → Cookie.', 'translate-words'),
        missing: __('Please add the Gemini Web __Secure-1PSID cookie to continue.', 'translate-words'),
        fields: [
            { key: 'gemini_web_psid', label: '__Secure-1PSID', placeholder: __('Paste __Secure-1PSID value only', 'translate-words') },
            { key: 'gemini_web_psidts', label: '__Secure-1PSIDTS', placeholder: __('Paste __Secure-1PSIDTS value only', 'translate-words') },
        ],
    },
}

/**
 * Whether the provider already has a saved session (first field is the required cookie).
 */
export const isWebSessionConfigured = (data, provider) =>
    (data?.api_keys_configuration?.keys?.[webSessionProviders[provider].fields[0].key] || '') !== ''

const WebSessionKey = ({ provider, data, setData, drafts, setDrafts, models, setModels }) => {
    const meta = webSessionProviders[provider]
    const masked = data?.api_keys_configuration?.keys || {}
    const isConfigured = isWebSessionConfigured(data, provider)
    const modelList = data?.api_keys_configuration?.available_models?.[provider] || {}

    const resetSession = () => {
        const keys = {}
        meta.fields.forEach((field) => {
            keys[field.key] = ''
        })
        const run = apiFetch({
            path: 'lmat/v1/settings',
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': getNonce(),
            },
            body: JSON.stringify({ keys }),
        }).then((response) => {
            setData((prev) => ({ ...prev, ...response }))
            setDrafts((prev) => ({ ...prev, ...keys }))
            return response
        })
        toast.promise(run, {
            loading: __('Saving Settings', 'translate-words'),
            success: __('Settings Saved', 'translate-words'),
            error: (error) => error?.message || __('Something went wrong', 'translate-words'),
        })
    }

    return (
        <div>
            <h3 className="m-0 text-base font-semibold mb-3 pt-3">{meta.heading}</h3>
            <div className="flex items-start gap-3">
                <div className="flex-1 flex flex-col gap-4" style={{ maxWidth: 400 }}>
                    {meta.fields.map((field) => (
                        <div key={field.key}>
                            <Label size="sm" className="font-medium mb-2 block" htmlFor={field.key}>{field.label}</Label>
                            <Input
                                aria-label={field.label}
                                id={field.key}
                                size="md"
                                type="text"
                                autoComplete="off"
                                placeholder={field.placeholder}
                                disabled={isConfigured}
                                value={isConfigured ? (masked[field.key] || '') : (drafts[field.key] || '')}
                                onChange={(value) => {
                                    if (isConfigured) return
                                    setDrafts((prev) => ({ ...prev, [field.key]: value }))
                                }}
                            />
                        </div>
                    ))}
                    {!isConfigured ? (
                        <div className="text-sm text-gray-600">{meta.help}</div>
                    ) : null}
                </div>
                <div className="flex-shrink-0" style={{ paddingRight: '3em', paddingTop: '1.75rem' }}>
                    <Button
                        size="md"
                        tag="button"
                        type="button"
                        variant="primary"
                        onClick={resetSession}
                        disabled={!isConfigured}
                    >
                        {__('Reset', 'translate-words')}
                    </Button>
                </div>
            </div>

            {isConfigured ? (
                <div className="mt-8">
                    <Label size="sm" className="font-medium mb-2 block">{meta.modelHeading}</Label>
                    <div className="mt-0" style={{ maxWidth: 320 }}>
                        <select
                            className="w-full border border-gray-300 rounded-md px-3 py-2 text-sm bg-white"
                            value={models[meta.modelKey] || meta.defaultModel}
                            onChange={(e) => setModels((prev) => ({ ...prev, [meta.modelKey]: e.target.value }))}
                        >
                            {Object.keys(modelList).map((id) => (
                                <option key={id} value={id}>{modelList[id]}</option>
                            ))}
                        </select>
                    </div>
                </div>
            ) : null}
        </div>
    )
}

export default WebSessionKey
