import React, { useState, useEffect, useRef } from 'react'
import { Button, Checkbox, Container, Input, Label, RadioButton, Switch, Badge } from '@bsf/force-ui'
import { Languages, Link } from 'lucide-react';
import { RiDraftLine } from "react-icons/ri";
import { __, sprintf } from '@wordpress/i18n'
import apiFetch from "@wordpress/api-fetch"
import { getNonce } from '../utils'
import { toast } from 'sonner'
import { ChromeIcon } from '../../../../../assets/logo/chrome';
import { GoogleIcon } from '../../../../../assets/logo/google';
import { GeminiIcon } from '../../../../../assets/logo/gemini';
import ApiKey from './api-key';
import DOMPurify from 'dompurify';



const ChromeLocalAINotice = () => {
    const [showBrowserNotice, setShowBrowserNotice] = React.useState(false);
    const [showSecureNotice, setShowSecureNotice] = React.useState(false);
    const [showApiNotice, setShowApiNotice] = React.useState(false);

    React.useEffect(() => {
        const safeBrowser = window?.location?.protocol === "https:";
        const browserContentSecure = window?.isSecureContext;
        // Secure connection + API availability check
        const apiAvailable =
            ("translation" in window?.self &&
                "createTranslator" in window?.self?.translation) ||
            ("ai" in window?.self && "translator" in window?.self?.ai) ||
            ("Translator" in window?.self && "create" in window?.self?.Translator);

        // Browser check (must be Chrome, not Edge or others)
        if (
            !window?.hasOwnProperty("chrome") ||
            !navigator?.userAgent?.includes("Chrome") ||
            navigator?.userAgent?.includes("Edg")
        ) {
            setShowBrowserNotice(true);
        } else if (!apiAvailable && !safeBrowser && !browserContentSecure) {
            setShowSecureNotice(true);
        } else if (!apiAvailable) {
            setShowApiNotice(true);
        }
    }, []);

    if (!showBrowserNotice && !showSecureNotice && !showApiNotice) {
        return null; // no notice needed
    }

    let message = '';
    let heading = '';

    if (showBrowserNotice) {
        heading = __('⚠️ Important Notice: Browser Compatibility', 'translate-words');
        message = `<ul className="list-disc ml-5 mt-2"><li>
                ${sprintf(__('The %sTranslator API%s, which uses Chrome Local AI Models, is designed exclusively for use with the %sChrome browser%s.', 'translate-words'), '<strong>', '</strong>', '<strong>', '</strong>')}
              </li>
              <li>
                ${sprintf(__('If you are using a different browser (such as Edge, Firefox, or Safari), the API may not function correctly.', 'translate-words'))}
              </li>
              <li>
                ${sprintf(__('Learn more in the %sofficial documentation%s.', 'translate-words'), '<a href="https://developer.chrome.com/docs/ai/translator-api" target="_blank" rel="noreferrer" class="underline text-blue-600">', '</a>')}
              </li>
      </ul>`;
    } else if (showSecureNotice) {
        heading = __('⚠️ Important Notice: Secure Connection Required', 'translate-words');
        message = `<ul className="list-disc ml-5 mt-2">
              <li>
                ${sprintf(__('The %sTranslator API%s requires a secure (HTTPS) connection to function properly.', 'translate-words'), '<strong>', '</strong>')}
              </li>
              <li>
                ${__('If you are on an insecure connection (HTTP), the API will not work.', 'translate-words')}
              </li>
            </ul>
            <p className="mt-2">${__('👉 How to Fix This:', 'translate-words')}</p>
            <ol className="list-decimal ml-5 mt-2">
              <li>${sprintf(__('Switch to a secure connection by using %s.', 'translate-words'), '<strong><code>https://</code></strong>')}
              </li>
              <li>
                ${sprintf(__('Alternatively, add this URL to Chrome’s list of insecure origins treated as secure: %s.', 'translate-words'), '<strong><code>chrome://flags/#unsafely-treat-insecure-origin-as-secure</code></strong>')}
                <br />
                ${__('Copy the URL and then open a new window and paste this URL to access the settings.', 'translate-words')}
              </li>
            </ol>`;
    } else if (showApiNotice) {
        heading = __('⚠️ Important Notice: API Availability', 'translate-words');
        message = `<ol>
                    <li>${sprintf(__('Open this URL in a new Chrome tab: %s. Copy this URL and then open a new window and paste this URL to access the settings.', 'translate-words'), '<strong><code>chrome://flags/#translation-api</code></strong>')}</li>
                    <li>${sprintf(__('Ensure that the %sExperimental translation API%s option is set to <strong>Enabled</strong>.', 'translate-words'), '<strong>', '</strong>')}</li>
                    <li>${sprintf(__('After change the setting, Click on the %sRelaunch%s button to apply the changes.', 'translate-words'), '<strong>', '</strong>')}</li>
                    <li>${__('The Translator AI modal should now be enabled and ready for use.', 'translate-words')}</li>
                </ol>
                <p>${sprintf(__('For more information, please refer to the %sdocumentation%s.', 'translate-words'), '<a href="https://developer.chrome.com/docs/ai/translator-api" target="_blank">', '</a>')}</p>   
                <p>${__('If the issue persists, please ensure that your browser is up to date and restart your browser.', 'translate-words')}</p>
                <p>${sprintf(__('If you continue to experience issues after following the above steps, please %sopen a support ticket%s with our team. We are here to help you resolve any problems and ensure a smooth translation experience.', 'translate-words'), '<a href="https://my.coolplugins.net/account/support-tickets/" target="_blank" rel="noopener">', '</a>')}</p>`;
    }

    return (
        <div
            className="flex flex-col gap-4 p-6 rounded-lg"
            style={{ border: "1px solid #e5e7eb", background: "#fff5f5", margin: "0 1.5rem 1.5rem 1.5rem" }}
        >
            <div className="text-red-600 text-sm leading-6">
                <h3 className="font-semibold">{heading}</h3>
                <div dangerouslySetInnerHTML={{ __html: DOMPurify.sanitize(message) }} />
            </div>
        </div>
    );
};

const TranslationConfig = ({ data, setData }) => {

    const aiTranslation = data?.ai_translation_configuration; //store the media option
    const provider = aiTranslation?.provider;
    const wpAiClientAvailable = Boolean(window?.lmat_settings?.wp_ai_client_available);
    const [googleMachineTranslation, setGoogleMachineTranslation] = useState(provider?.google)
    const [chromeLocalAITranslation, setChromeLocalAITranslation] = useState(provider?.chrome_local_ai)
    const [geminiTranslation, setGeminiTranslation] = useState(provider?.gemini)
    const [lastUpdatedValue, setLastUpdatedValue] = useState({ googleMachineTranslation, chromeLocalAITranslation, geminiTranslation })
    const [bulkTranslationPostStatus, setBulkTranslationPostStatus] = useState(aiTranslation?.bulk_translation_post_status || 'draft')
    const [slugTranslationOption, setSlugTranslationOption] = useState(aiTranslation?.slug_translation_option || 'title_translate')
    const [handleButtonDisabled, setHandleButtonDisabled] = useState(true)
    const [isSaving, setIsSaving] = useState(false)
    const [apiKeyDirty, setApiKeyDirty] = useState(false)
    const geminiApiKeyRef = useRef(null)

    useEffect(() => {
        if (!geminiTranslation) {
            setApiKeyDirty(false)
        }
    }, [geminiTranslation])

    useEffect(() => {
        let sameChecker = {
            googleMachineTranslation: true,
            chromeLocalAITranslation: true,
            geminiTranslation: true,
            bulkTranslationPostStatus: true,
            slugTranslationOption: true,
        }

        if (googleMachineTranslation !== provider?.google) {
            sameChecker.googleMachineTranslation = false

        }

        if (chromeLocalAITranslation !== provider?.chrome_local_ai) {
            sameChecker.chromeLocalAITranslation = false
        }
        
        if (wpAiClientAvailable && geminiTranslation !== provider?.gemini) {
            sameChecker.geminiTranslation = false
        }

        if (bulkTranslationPostStatus !== aiTranslation?.bulk_translation_post_status) {
            sameChecker.bulkTranslationPostStatus = false
        }

        if (slugTranslationOption !== aiTranslation?.slug_translation_option) {
            sameChecker.slugTranslationOption = false
        }

        let flag = true;
        for (const key in sameChecker) {
            if (!sameChecker[key]) {
                flag = false;
                break;
            }
        }
        const geminiSectionOpen = wpAiClientAvailable && geminiTranslation
        setHandleButtonDisabled(flag && !(geminiSectionOpen && apiKeyDirty))
    }, [chromeLocalAITranslation, googleMachineTranslation, geminiTranslation, bulkTranslationPostStatus, slugTranslationOption, wpAiClientAvailable, apiKeyDirty])


    //Save Setting Function 
    async function SaveSettings() {
        try {
            if (isSaving) return
            setIsSaving(true)
            let apiBody;
            const apiKeyPayload =
                wpAiClientAvailable && geminiTranslation && geminiApiKeyRef.current?.getPendingPayload
                    ? geminiApiKeyRef.current.getPendingPayload()
                    : null;

            // Require Gemini API key when enabling Gemini.
            if (wpAiClientAvailable && geminiTranslation) {
                const pendingGeminiKey = (apiKeyPayload?.keys?.gemini || '').toString().trim()
                const hasConfiguredGeminiKey = Boolean(geminiApiKeyRef.current?.hasConfiguredKey?.('gemini'))
                if (!hasConfiguredGeminiKey && pendingGeminiKey === '') {
                    throw new Error(__('Please add a Gemini API key to continue.', 'translate-words'))
                }
            }

            apiBody = {
                ai_translation_configuration: {
                    provider: {
                        google: googleMachineTranslation,
                        chrome_local_ai: chromeLocalAITranslation,
                        ...(wpAiClientAvailable ? {
                            gemini: geminiTranslation,
                        } : {}),
                    },
                    bulk_translation_post_status: bulkTranslationPostStatus,
                    slug_translation_option: slugTranslationOption
                }
            }
            if (apiKeyPayload?.keys) {
                apiBody.keys = apiKeyPayload.keys
            }
            if (apiKeyPayload?.models) {
                apiBody.models = apiKeyPayload.models
            }

            setLastUpdatedValue({ googleMachineTranslation, chromeLocalAITranslation, geminiTranslation, bulkTranslationPostStatus, slugTranslationOption })
            if (aiTranslation && (
                lastUpdatedValue.googleMachineTranslation !== googleMachineTranslation ||
                lastUpdatedValue.chromeLocalAITranslation !== chromeLocalAITranslation ||
                (wpAiClientAvailable && lastUpdatedValue.geminiTranslation !== geminiTranslation) ||
                lastUpdatedValue.bulkTranslationPostStatus !== bulkTranslationPostStatus ||
                lastUpdatedValue.slugTranslationOption !== slugTranslationOption
            )) {
                setData(prev => ({
                    ...prev,
                    ...apiBody
                }))
            }
            //API Call (single settings request includes Gemini keys/models when present)
            const response = apiFetch({
                path: 'lmat/v1/settings',
                method: 'POST',
                'headers': {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': getNonce()
                },
                body: JSON.stringify(apiBody)
            })
                .then((settingsResponse) => {
                    setData(prev => ({ ...prev, ...settingsResponse }))
                    if (apiKeyPayload && geminiApiKeyRef.current?.syncAfterParentSave) {
                        geminiApiKeyRef.current.syncAfterParentSave({
                            keys: apiBody.keys,
                            models: apiBody.models,
                        }, settingsResponse)
                        setApiKeyDirty(false)
                    }
                    return settingsResponse
                })
                .catch(error => {
                    // Handle general API errors
                    if (error?.message) {
                        throw new Error(error.message);
                    }
                    throw new Error(__("Something went wrong", 'translate-words'));
                });

            toast.promise(response, {
                loading: __('Saving Settings', 'translate-words'),
                success: __('Settings Saved', 'translate-words'),
                error: (error) => error.message
            })
            await response
                .then(() => {
                    setHandleButtonDisabled(true)
                })
                // Avoid double notifications: toast.promise already shows the error message.
                .catch(() => { })
                .finally(() => {
                    setIsSaving(false)
                })

        } catch (error) {
            setIsSaving(false)
            // Handle domain validation errors
            if (error.message.includes(__("Linguator was unable to access", "translate-words"))) {
                toast.error(error.message);
            } else {
                toast.error(error.message || __("Something went wrong", "translate-words"));
            }
        }
    }

    const postStatusOptions = [
        {
            heading: __('Published', 'translate-words'),
            value: 'publish'
        },
        {
            heading: __('Draft', 'translate-words'),
            value: 'draft'
        }
    ]

    const slugTranslationOptions = [
        {
            heading: __('Use Translated Title', 'translate-words'),
            value: 'title_translate'
        },
        {
            heading: __('Translate Original Slug', 'translate-words'),
            value: 'slug_translate'
        },
        {
            heading: __('Keep Original Slug', 'translate-words'),
            value: 'slug_keep'
        }
    ]

    return (
        <Container className='bg-white p-10 rounded-lg' cols="1" containerType='grid'>
            <Container className='flex items-center'>
                <Container.Item className='flex w-full justify-between px-4 gap-6'>
                    <h1 className='font-bold'>{__('Translation Settings', 'translate-words')}</h1>
                    <Button
                        disabled={handleButtonDisabled || isSaving}
                        className=""
                        iconPosition="left"
                        size="md"
                        tag="button"
                        type="button"
                        onClick={SaveSettings}
                        variant="primary"
                    >
                        {__('Save Settings', 'translate-words')}
                    </Button>
                </Container.Item>
            </Container>
            <hr className="w-full border-b-0 border-x-0 border-t border-solid border-t-border-subtle" />
            <Container.Item className='p-6 rounded-lg' style={{ border: "1px solid #e5e7eb" }}>
                <Label size='md' className='font-bold flex items-center gap-2'>
                    <Languages className="flex-shrink-0 size-5 text-icon-secondary" />
                    {__('Service Provider', 'translate-words')}
                </Label>
                <Label variant='help'>
                    {__('Select at least one translation service provider below. You can enable multiple providers and choose the one that best fits your needs.', 'translate-words')}
                </Label>
                <div className='flex flex-col gap-2' style={{ marginTop: "20px" }}>
                    <div style={{ backgroundColor: "#fbfbfb" }}>
                        <div className='switcher p-6 rounded-lg'>
                            <Container.Item>
                                <h3 className='flex items-center gap-2'>
                                    <GoogleIcon className='w-5 h-5' />
                                    {__('Google Machine Translation', 'translate-words')}
                                </h3>
                                <p>
                                    {__('Google Machine Translation uses the Google Translate API to translate text.', 'translate-words')}
                                </p>
                            </Container.Item>
                            <Container.Item className='flex items-center justify-end' style={{ paddingRight: '30%' }}>
                                <Switch
                                    aria-label="Switch Element"
                                    id="google-machine-translation"
                                    onChange={() => {
                                        setGoogleMachineTranslation(!googleMachineTranslation)
                                    }}
                                    value={googleMachineTranslation}
                                    size="sm"
                                />
                            </Container.Item>
                        </div>
                    </div>
                    <div style={{ backgroundColor: "#fbfbfb" }}>
                        <div className='switcher p-6 rounded-lg'>
                            <Container.Item >
                                <h3 className='flex items-center gap-2'>
                                    <ChromeIcon className="w-5 h-5" />
                                    {__('Chrome Local AI Translation', 'translate-words')}
                                </h3>
                                <p>
                                    {__('Chrome Local AI Translation uses Chrome Local AI API to translate text.', 'translate-words')}
                                </p>
                            </Container.Item>
                            <Container.Item className='flex items-center justify-end' style={{ paddingRight: '30%' }}>
                                <Switch
                                    aria-label="Switch Element"
                                    id="chrome-local-ai-translation"
                                    onChange={() => {
                                        setChromeLocalAITranslation(!chromeLocalAITranslation)
                                    }}
                                    value={chromeLocalAITranslation}
                                    size="sm"
                                />
                            </Container.Item>
                        </div>
                        {chromeLocalAITranslation && <ChromeLocalAINotice />}
                    </div>
                    {wpAiClientAvailable && (
                        <div style={{ backgroundColor: "#fbfbfb" }}>
                            <div className='switcher p-6 rounded-lg'>
                                <Container.Item>
                                    <h3 className='flex items-center gap-2'>
                                        <GeminiIcon className='w-5 h-5' />
                                        {__('Google Gemini AI', 'translate-words')}
                                    </h3>
                                    <p className="m-0">
                                        {__('Google Gemini AI uses Google Gemini API to translate your content.', 'translate-words')}
                                    </p>
                                </Container.Item>
                                <Container.Item className='flex items-center justify-end' style={{ paddingRight: '30%' }}>
                                    <Switch
                                        aria-label="Switch Element"
                                        id="gemini-translation"
                                        onChange={() => {
                                            setGeminiTranslation(!geminiTranslation)
                                        }}
                                        value={geminiTranslation}
                                        size="sm"
                                    />
                                </Container.Item>
                            </div>
                            {geminiTranslation && (
                                <div
                                    className="px-6 pb-6 pt-0"
                                >
                                    <ApiKey
                                        ref={geminiApiKeyRef}
                                        data={data}
                                        setData={setData}
                                        embedded
                                        onPendingChange={setApiKeyDirty}
                                    />
                                </div>
                            )}
                        </div>
                    )}
                </div>
            </Container.Item>
            <hr className="w-full border-b-0 border-x-0 border-t border-solid border-t-border-subtle" />
            <Container.Item>
                <Label size='md' className='font-bold flex items-center gap-2'>
                    <RiDraftLine className="flex-shrink-0 size-5 text-icon-secondary" />
                    {__('Bulk Translation – Default Post and Page Status', 'translate-words')}
                </Label>
                <Label variant='help'>
                    {__('This is the default post and page status for bulk translation.', 'translate-words')}
                </Label>
                <div style={{ marginTop: "20px" }}>
                    <RadioButton.Group>
                        {postStatusOptions.map((postStatus, index) => (
                            <RadioButton.Button
                                badgeItem={<Badge className="mr-2" size="sm" type="rounded" variant="green" />}
                                label={{
                                    heading: postStatus.heading,
                                }}
                                reversePosition={true}
                                value={postStatus.value}
                                key={index}
                                checked={bulkTranslationPostStatus === postStatus.value}
                                onChange={() => {
                                    setBulkTranslationPostStatus(postStatus.value);
                                }}
                            />

                        ))}
                    </RadioButton.Group>
                </div>
            </Container.Item>
            <hr className="w-full border-b-0 border-x-0 border-t border-solid border-t-border-subtle" />
            <Container.Item>
                <Label size='md' className='font-bold flex items-center gap-2'>
                    <Link className="flex-shrink-0 size-5 text-icon-secondary" />
                    {__('Slug Translation Settings', 'translate-words')}
                </Label>
                <Label variant='help'>{__('Choose how post slugs (URLs) are generated when content is translated.', 'translate-words')}</Label>
                <div style={{ marginTop: "20px" }}>
                    <RadioButton.Group>
                        {slugTranslationOptions.map((slugOption, index) => (
                            <RadioButton.Button
                                badgeItem={<Badge className="mr-2" size="sm" type="rounded" variant="green" />}
                                label={{
                                    heading: slugOption.heading,
                                }}
                                reversePosition={true}
                                value={slugOption.value}
                                key={index}
                                checked={slugTranslationOption === slugOption.value}
                                onChange={() => {
                                    setSlugTranslationOption(slugOption.value)
                                }}
                            />
                        ))}

                    </RadioButton.Group>
                </div>
            </Container.Item>
            <hr className="w-full border-b-0 border-x-0 border-t border-solid border-t-border-subtle" />
            <Container className='flex items-center justify-end'>
                <Container.Item className='flex gap-6'>
                    <Button
                        disabled={handleButtonDisabled || isSaving}
                        className=""
                        iconPosition="left"
                        size="md"
                        tag="button"
                        type="button"
                        onClick={SaveSettings}
                        variant="primary"
                    >
                        {__('Save Settings', 'translate-words')}
                    </Button>

                </Container.Item>
            </Container>
        </Container>
    )
}

export default TranslationConfig;