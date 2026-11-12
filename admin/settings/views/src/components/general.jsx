//Genral Tab Body

import { Badge, Accordion, Button, Checkbox, Container, Input, Label, RadioButton, Switch } from '@bsf/force-ui'
import apiFetch from "@wordpress/api-fetch"
import React, { useEffect, useState } from 'react'
import { toast } from 'sonner'; // importing toaster and toast for notification purpose
import { synchronizations } from '../utils'
import { getNonce } from '../utils'
import { Link, Globe, Focus, Milestone, RefreshCcw, Share2, Settings2, Menu } from 'lucide-react';
import { __, sprintf } from '@wordpress/i18n';
import { applyFilters } from '@wordpress/hooks';

// Boolean settings added by Linguator Pro. Each one is shown only when its key is in the settings response.
// `requiresUrlLanguage`: the setting cannot work when the language is set from the content (force_lang 0).
const PRO_TOGGLES = [
    {
        key: 'share_slugs',
        title: __('Same Slug in Every Language', 'translate-words'),
        description: __('Let translations use the same URL slug in every language, for example /about/ and /fr/about/, instead of adding -2 or a language code. Each URL opens the version in its own language.', 'translate-words'),
        requiresUrlLanguage: true,
    },
    {
        key: 'translate_url_bases',
        title: __('Translate URL Bases', 'translate-words'),
        description: __('Translate the base of post type, category and tag URLs in each language, for example /fr/categorie/news/ instead of /fr/category/news/. Leave a field empty to keep the original base.', 'translate-words'),
    },
];

/**
 * Extra settings fields added by add-ons (such as Linguator Pro), shown under a switch of this tab while it is on.
 *
 * Filter `lmat.settings.general.extraFields` returns an array of fields:
 * - key:          option key, also its key in the settings response. The field is skipped when the key is absent.
 * - parentToggle: key of the switch the field is shown under.
 * - normalize:    optional, ( value ) => the value to compare and save.
 * - render:       ( { value, onChange, data, languages } ) => the field's element.
 */
const getExtraFields = (data) => {
    const fields = applyFilters('lmat.settings.general.extraFields', []);
    return (Array.isArray(fields) ? fields : []).filter(
        (field) => field && field.key && 'function' === typeof field.render && data[field.key] !== undefined
    );
};

const normalizeExtraValue = (field, value) => ('function' === typeof field.normalize ? field.normalize(value) : value);

const getExtraValues = (fields, data) => fields.reduce((values, field) => {
    values[field.key] = normalizeExtraValue(field, data[field.key]);
    return values;
}, {});

const sameExtraValue = (field, a, b) => JSON.stringify(normalizeExtraValue(field, a)) === JSON.stringify(normalizeExtraValue(field, b));

// Linguator Pro product page, shown on the locked Pro settings when Pro is not active.
const PRO_PRODUCT_URL = 'https://coolplugins.net/product/linguator-pro-ai-translation-multilingual-wordpress/';
const getProUrl = (settingKey) => `${PRO_PRODUCT_URL}?utm_source=twlmat_plugin&utm_medium=inside&utm_campaign=get_pro&utm_content=settings_${settingKey}`;

// Reads the current boolean value of each available Pro switch.
const getProToggleValues = (toggles, data) => toggles.reduce((values, toggle) => {
    values[toggle.key] = !!data[toggle.key];
    return values;
}, {});

// Pro switch values to save; a switch that needs the language in the URL is saved as off otherwise.
const getProTogglesToSave = (toggles, values, forceLang) => toggles.reduce((body, toggle) => {
    body[toggle.key] = toggle.requiresUrlLanguage && 0 === forceLang ? false : !!values[toggle.key];
    return body;
}, {});

const General = ({ data, setData }) => {

    const [browser, setBrowser] = useState(data.browser); //For Detect Browser Language
    const [mediaSupport, setMediaSupport] = useState(data.media_support); // For Media
    const [hideDefault, setHideDefault] = useState(data.hide_default); //For Hide URL language in URL modifications
    const [forceLang, setForceLang] = useState(data.force_lang); // Main Radio Options for URL modifications
    const [rewrite, setRewrite] = useState(data.rewrite); // if the first option is selected then for that radio options
    const [redirectLang, setRedirectLang] = useState(data.redirect_lang); // Front page URL contains the language code
    const redirectLangExample = window.lmat_settings.redirect_lang_example || {}; // Example URLs for the front page option
    const [domains, setDomains] = useState([]) // if 3rd option is selected then for that url inputs
    const currentDomain = window.lmat_settings.home_url; // Fetch the current domain of website
    const [AvailablePostTypes, setAvailablePostTypes] = useState([]); // Available Custom Post Types
    const [AvailableTaxonomies, setAvailableTaxonomies] = useState([]); // Available Custom Taxonomies
    const [selectedSynchronization, setSelectedSyncronization] = useState(data.sync); // Selected Synchronization options
    const [selectedPostTypes, setSelectedPostTypes] = useState(data.post_types); // Selected Custom Post Types
    const [selectedTaxonomies, setSelectedTaxonomies] = useState(data.taxonomies); //Selected Custom Taxonomies
    const disabledPostTypes= data.disabled_post_types || []; //Disabled Post Types (programmatically active)
    const [handleButtonDisabled, setHandleButtonDisabled] = useState(true)
    const [isSaving, setIsSaving] = useState(false)
    const [selectAllSync,setSelectAllSync] = useState(false);
    const [selectAllPostTypes, setSelectAllPostTypes] = useState(false);
    const [selectAllTaxonomies, setSelectAllTaxonomies] = useState(false);
    const previousDomains = React.useRef([])
    const [lmatFeedbackData, setLmatFeedbackData] = useState(data.lmat_feedback_data !== undefined ? data.lmat_feedback_data : false); // For Usage Data Sharing
    const [selectedLanguageSwitchers, setSelectedLanguageSwitchers] = useState(data.lmat_language_switcher_options || ['default']); // Selected Language Switcher options
    const [showTerms, setShowTerms] = useState(false); // For showing/hiding terms box
    const [menuSyncVisibility, setMenuSyncVisibility] = useState(data.menu_sync_visibility !== undefined ? data.menu_sync_visibility : false); // For Menu Sync feature visibility
    // Pro switches: only the keys Linguator Pro registered are present in the settings response.
    const availableProToggles = PRO_TOGGLES.filter((toggle) => data[toggle.key] !== undefined);
    const [proToggles, setProToggles] = useState(() => getProToggleValues(availableProToggles, data));
    // Add-on fields shown under a Pro switch (see getExtraFields).
    const extraFields = getExtraFields(data);
    const [extraValues, setExtraValues] = useState(() => getExtraValues(extraFields, data));
    const savedExtraValues = JSON.stringify(getExtraValues(extraFields, data));

    // Show the saved values after each save: the server cleans them.
    useEffect(() => {
        setExtraValues(getExtraValues(extraFields, data));
    }, [savedExtraValues]);


    //make the Domains in a suitable way to view
    useEffect(() => {
        let newDomains = window.lmat_settings.languages.map((item) => {
            const code = item.slug;
            return {
                value: data.domains[code] || "",
                name: `${item.name}-${item.locale}`,
                code: code
            };
        });
        setDomains(newDomains);
        previousDomains.current = newDomains;
    }, []);

   

    //Check if any changes happen to enable or disable the save button
    useEffect(() => {
        let sameChecker = {
            browser: true,
            mediaSupport: true,
            hideDefault: true,
            forceLang: true,
            rewrite: true,
            redirectLang: true,
            domains: true,
            selectedSynchronization: true,
            selectedPostTypes: true,
            selectedTaxonomies: true,
            selectedLanguageSwitchers: true,
            menuSyncVisibility: true
        }
        // Only include lmatFeedbackData in the checker if the setting is available
        if (data.lmat_feedback_data !== undefined) {
            sameChecker.lmatFeedbackData = true;
        }
        if (forceLang !== data.force_lang) {
            sameChecker.forceLang = false

        }

        if (hideDefault !== data.hide_default) {
            sameChecker.hideDefault = false
        }

        if (browser !== data.browser) {
            sameChecker.browser = false

        }

        if (data.lmat_feedback_data !== undefined && lmatFeedbackData !== data.lmat_feedback_data) {
            sameChecker.lmatFeedbackData = false
        }

        if (rewrite !== data.rewrite) {
            sameChecker.rewrite = false
        }

        if (redirectLang !== data.redirect_lang) {
            sameChecker.redirectLang = false
        }


        if (mediaSupport !== data.media_support) {
            sameChecker.mediaSupport = false
        }

        // For menu_sync_visibility, handle both defined and undefined cases
        const menuSyncBackendValue = data.menu_sync_visibility !== undefined ? data.menu_sync_visibility : false;
        if (menuSyncVisibility !== menuSyncBackendValue) {
            sameChecker.menuSyncVisibility = false
        }
        for (const toggle of availableProToggles) {
            if (proToggles[toggle.key] !== !!data[toggle.key]) {
                sameChecker.proToggles = false
                break;
            }
        }
        for (const field of extraFields) {
            if (!sameExtraValue(field, extraValues[field.key], data[field.key])) {
                sameChecker.extraFields = false
                break;
            }
        }
        for (const value of previousDomains.current) {
            if (!domains.includes(value) || previousDomains.current.length != domains.length) {
                sameChecker.domains = false
                break;
            }
        }
        if (data.post_types.length != selectedPostTypes.length) {
            sameChecker.selectedPostTypes = false
        } else {
            for (const value of data.post_types) {
                if (!selectedPostTypes.includes(value)) {
                    sameChecker.selectedPostTypes = false
                    break;
                }
            }
        }


        if (data.taxonomies.length != selectedTaxonomies.length) {
            sameChecker.selectedTaxonomies = false
        } else {
            for (const value of data.taxonomies) {
                if (!selectedTaxonomies.includes(value)) {
                    sameChecker.selectedTaxonomies = false
                    break;
                }
            }
        }


        if (data.sync.length != selectedSynchronization.length) {
            sameChecker.selectedSynchronization = false
        } else {
            for (const value of data.sync) {
                if (!selectedSynchronization.includes(value)) {
                    sameChecker.selectedSynchronization = false
                    break;
                }
            }
        }

        if (data.lmat_language_switcher_options && data.lmat_language_switcher_options.length != selectedLanguageSwitchers.length) {
            sameChecker.selectedLanguageSwitchers = false
        } else if (data.lmat_language_switcher_options) {
            for (const value of data.lmat_language_switcher_options) {
                if (!selectedLanguageSwitchers.includes(value)) {
                    sameChecker.selectedLanguageSwitchers = false
                    break;
                }
            }
        }

        let flag = true;
        for (const key in sameChecker) {
            if (!sameChecker[key]) {
                flag = false;
                setHandleButtonDisabled(false)
                break;
            }
        }
        if (flag) {
            setHandleButtonDisabled(true)
        }
    }, [browser, mediaSupport, hideDefault, forceLang, rewrite, redirectLang, domains, selectedSynchronization, selectedPostTypes, selectedTaxonomies, lmatFeedbackData, selectedLanguageSwitchers, menuSyncVisibility, proToggles, extraValues, savedExtraValues])

    //Make the post types and taxonomies from  posttype->posttype_name   to {value: postype ,label:posttype_name (posttype)}
    useEffect(() => {
        const newAvailableTaxonomies = Object.keys(data.available_taxonomies).map(key => ({
            value: data.available_taxonomies[key].taxonomy_key,
            label: data.available_taxonomies[key].taxonomy_name + " ( " + data.available_taxonomies[key].taxonomy_key + " )"
        }));
        setAvailableTaxonomies(newAvailableTaxonomies);

        const newAvailablePostTypes = data.available_post_types.map(post_type => ({
            value: post_type.post_type_key,
            label: post_type.post_type_name + " ( " + post_type.post_type_key + " )"
        }));
        setAvailablePostTypes(newAvailablePostTypes);
    }, [data.taxonomies, data.post_types]);

    //Handle checkboxes of PostType
    const handlePostTypeChange = (postType) => {
        setHandleButtonDisabled(false)
        setSelectedPostTypes(prev => {
            if (prev.includes(postType)) {
                return prev.filter(item => item !== postType);
            } else {
                return [...prev, postType];
            }
        });

    };

    //Handle Checkboxes of taxonomies
    const handleTaxonomyChange = (taxonomy) => {
        setHandleButtonDisabled(false)
        setSelectedTaxonomies(prev => {
            if (prev.includes(taxonomy)) {
                return prev.filter(item => item !== taxonomy);
            } else {
                return [...prev, taxonomy];
            }
        });

    };

    //Handle Checkboxes of Synchronization
    const handleSynchronizationChange = (synchronization) => {
        setHandleButtonDisabled(false)
        setSelectedSyncronization(prev => {
            if (prev.includes(synchronization)) {
                return prev.filter(item => item !== synchronization);
            } else {
                return [...prev, synchronization];
            }
        });

    };



    //Handle Select All Sync
    const handleSelectAllSync = () => {
        setHandleButtonDisabled(false);
        if (selectAllSync) {
            // Deselect all
            setSelectedSyncronization([]);
            setSelectAllSync(false);
        } else {
            // Select all
            const allSyncValues = synchronizations.map(sync => sync.value);
            setSelectedSyncronization(allSyncValues);
            setSelectAllSync(true);
        }
    };

    // Update selectAllSync state when individual sync items change
    React.useEffect(() => {
        const allSyncValues = synchronizations.map(sync => sync.value);
        const allSelected = allSyncValues.every(value => selectedSynchronization.includes(value));
        setSelectAllSync(allSelected && selectedSynchronization.length > 0);
    }, [selectedSynchronization]);

    //Handle Select All Post Types
    
    const handleSelectAllPostTypes = () => {
        setHandleButtonDisabled(false);
        if (selectAllPostTypes) {
            // Deselect all (except disabled ones)
            const enabledPostTypes = selectedPostTypes.filter(postType => {
                const isDisabled = Array.isArray(disabledPostTypes) && disabledPostTypes.some(disabledType => {
                    const postTypeKey = typeof disabledType === 'object' ? disabledType.post_type_key : disabledType;
                    return postTypeKey === postType;
                });
                return isDisabled;
            });
            setSelectedPostTypes(enabledPostTypes);
            setSelectAllPostTypes(false);
        } else {
            // Select all (only enabled ones)
            const enabledPostTypes = AvailablePostTypes.filter(postType => {
                const isDisabled = Array.isArray(disabledPostTypes) && disabledPostTypes.some(disabledType => {
                    const postTypeKey = typeof disabledType === 'object' ? disabledType.post_type_key : disabledType;
                    return postTypeKey === postType.value;
                });
                return !isDisabled;
            });
            const enabledPostTypeValues = enabledPostTypes.map(postType => postType.value);
            setSelectedPostTypes(enabledPostTypeValues);
            setSelectAllPostTypes(true);
        }
    };



    // Update selectAllPostTypes state when individual post type items change
    React.useEffect(() => {
        if (AvailablePostTypes.length > 0) {
            const enabledPostTypes = AvailablePostTypes.filter(postType => {
                const isDisabled = Array.isArray(disabledPostTypes) && disabledPostTypes.some(disabledType => {
                    const postTypeKey = typeof disabledType === 'object' ? disabledType.post_type_key : disabledType;
                    return postTypeKey === postType.value;
                });
                return !isDisabled;
            });
            const enabledPostTypeValues = enabledPostTypes.map(postType => postType.value);
            const allEnabledSelected = enabledPostTypeValues.every(value => selectedPostTypes.includes(value));
            setSelectAllPostTypes(allEnabledSelected && enabledPostTypeValues.length > 0);
        }
    }, [selectedPostTypes, AvailablePostTypes]);

    //Handle Select All Taxonomies
    const handleSelectAllTaxonomies = () => {
        setHandleButtonDisabled(false);
        if (selectAllTaxonomies) {
            // Deselect all
            setSelectedTaxonomies([]);
            setSelectAllTaxonomies(false);
        } else {
            // Select all
            const allTaxonomyValues = AvailableTaxonomies.map(taxonomy => taxonomy.value);
            setSelectedTaxonomies(allTaxonomyValues);
            setSelectAllTaxonomies(true);
        }
    };

    // Update selectAllTaxonomies state when individual taxonomy items change
    React.useEffect(() => {
        if (AvailableTaxonomies.length > 0) {
            const allTaxonomyValues = AvailableTaxonomies.map(taxonomy => taxonomy.value);
            const allSelected = allTaxonomyValues.every(value => selectedTaxonomies.includes(value));
            setSelectAllTaxonomies(allSelected && selectedTaxonomies.length > 0);
        }
    }, [selectedTaxonomies, AvailableTaxonomies]);

    // Handle terms box visibility
    const handleTermsToggle = (e) => {
        e.preventDefault();
        setShowTerms(!showTerms);
    };

    //Save Setting Function 
    async function SaveSettings() {
        try {
            if (isSaving) return;
            setIsSaving(true);
            let reloadCheck = false;
            let apiBody;
            if (forceLang === 3) {
                let final_domain = {};
                let used_hosts = [];
                
                // Validate domains before processing
                for (const domain of domains) {
                    // Check if domain is empty
                    if (!domain.value || domain.value.trim() === '') {
                        throw new Error(__("Domain URL is required for all languages", "translate-words"));
                    }
                    
                    // Check if domain has proper protocol
                    if (!domain.value.includes("http://") && !domain.value.includes("https://")) {
                        throw new Error(__("Please enter valid URLs with http:// or https://", "translate-words"));
                    }
                    final_domain[domain.code] = domain.value;
                    
                }
                //API Body
                apiBody = {
                    hide_default: hideDefault,
                    browser: forceLang === 3 ? false : browser,
                    media_support: mediaSupport,
                    force_lang: forceLang,
                    rewrite: rewrite,
                    redirect_lang: redirectLang,
                    domains: final_domain,
                    sync: selectedSynchronization,
                    post_types: selectedPostTypes,
                    taxonomies: selectedTaxonomies,
                    menu_sync_visibility: menuSyncVisibility,
                }
                
                
                
                // Only include lmat_feedback_data if the setting is available
                if (data.lmat_feedback_data !== undefined) {
                    apiBody.lmat_feedback_data = lmatFeedbackData;
                }
            } else {
                apiBody = {
                    hide_default: hideDefault,
                    browser: forceLang === 3 ? false : browser,
                    media_support: mediaSupport,
                    force_lang: forceLang,
                    rewrite: rewrite,
                    redirect_lang: redirectLang,
                    sync: selectedSynchronization,
                    post_types: selectedPostTypes,
                    taxonomies: selectedTaxonomies,
                    menu_sync_visibility: menuSyncVisibility,
                }
                
                // Only include lmat_feedback_data if the setting is available
                if (data.lmat_feedback_data !== undefined) {
                    apiBody.lmat_feedback_data = lmatFeedbackData;
                }
            }
            // Pro switches (none without Linguator Pro).
            const proBody = getProTogglesToSave(availableProToggles, proToggles, forceLang)
            apiBody = { ...apiBody, ...proBody }
            setProToggles(proBody)
            setData(prev => ({
                ...prev,
                ...apiBody
            }))
            // Add-on fields are shown from the saved response only: the server cleans them and can reject them.
            const requestBody = { ...apiBody }
            extraFields.forEach((field) => {
                requestBody[field.key] = normalizeExtraValue(field, extraValues[field.key])
            })
            //API Call
            const response = apiFetch({
                path: 'lmat/v1/settings',
                method: 'POST',
                'headers': {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': getNonce()
                },
                body: JSON.stringify(requestBody)
            })
                .then((response) => {
                    setData(prev => ({ ...prev, ...response }))
                    if(reloadCheck){
                        window.location.reload();
                    }
                })
                .catch(error => {
                    // Handle domain validation errors from backend
                    if (error?.code && error?.code.includes('domain')) {
                        throw new Error(error.message);
                    }
                    if (error?.code === "missing_domains" || 
                        error?.code === "invalid_domain_format" || 
                        error?.code === "duplicate_domain" || 
                        error?.code === "empty_domain" || 
                        error?.code === "invalid_language" || 
                        error?.code === "missing_language_domain") {
                        throw new Error(error.message);
                    }
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
                .catch(() => { })
                .finally(() => {
                    setIsSaving(false)
                })
            
        } catch (error) {
            setIsSaving(false)
            // Handle domain validation errors
            if (error.message.includes(__("Please enter valid URLs", "translate-words")) ||
                error.message.includes(__("Domain URL is required", "translate-words")) ||
                error.message.includes(__("Invalid URL format", "translate-words")) ||
                error.message.includes(__("Invalid domain format", "translate-words")) ||
                error.message.includes(__("Duplicate domain host", "translate-words")) ||
                error.message.includes("domain") || error.message.includes("Domain")) {
                toast.error(error.message);
            } else if (error.message.includes(__("Linguator was unable to access", "translate-words"))) {
                toast.error(error.message);
            } else {
                toast.error(error.message || __("Something went wrong", "translate-words"));
            }
        }
    }


    //label and descriptions of URL modifications
    const urlCheckboxes = [{
        description: sprintf(__('Example1: %s/en/my-post', 'translate-words'), currentDomain),
        description2: sprintf(__('Example2: %s/hi/my-post', 'translate-words'), currentDomain),
        heading: __("Different languages in directories", 'translate-words'),
        value: 1
    }, {
        
        description: sprintf(__('Example1: %sen.%s/my-post', 'translate-words'), currentDomain.match(/^https?:\/\//)[0], currentDomain.replace(/^https?:\/\//, '')),
        description2: sprintf(__('Example2: %shi.%s/my-post', 'translate-words'), currentDomain.match(/^https?:\/\//)[0], currentDomain.replace(/^https?:\/\//, '')),
        heading: __("The language is set from the subdomain name ", 'translate-words'),
        value: 2
    }, {
        description: '',
        description2: '',
        heading: __("A different domain per language", 'translate-words'),
        value: 3
    }]

    const directoryNamesLinks = [{
        description: sprintf(__('Example: %s/en/', 'translate-words'), currentDomain),
        heading: __("Remove /language/ in pretty permalinks", 'translate-words'),
        value: true
    }, {
        description: sprintf(__('Example: %s/language/en', 'translate-words'), currentDomain),
        heading: __("Keep /language/ in pretty permalinks", 'translate-words'),
        value: false
    },]
    return (
        <>
            <Container className='bg-white p-10 rounded-lg shadow-sm' cols="1" containerType='grid'>
                <Container className='flex items-center'>
                    <Container.Item className='flex flex-wrap items-center w-full justify-between px-4 gap-6'>
                        <h1 className='font-bold'>General Settings</h1>
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
                {/* URL modifications section */}
                <Container cols="1" containerType='grid' className='border border-b-2' >
                    <Container.Item>
                        <Label size='md' className='font-bold flex items-center gap-2'>
                            <Link className="flex-shrink-0 size-5 text-icon-secondary" />
                            {__('Language URL format', 'translate-words')}
                        </Label>
                        <Label variant='help'>{__('Decide how your website’s URLs will display different languages for visitors.', 'translate-words')}</Label>
                    </Container.Item>
                    <Container containerType='grid' className='grid-cols-1 xl:grid-cols-2 gap-4'>
                        <Container.Item >
                            <RadioButton.Group
                                columns={1}
                                size="sm">
                                {
                                    urlCheckboxes.map((checkbox, index) => (
                                        <RadioButton.Button
                                            badgeItem={<Badge className="mr-2" size="sm" type="rounded" variant="green" />}
                                            label={{
                                                heading: checkbox.heading,
                                                description: (
                                                    <>
                                                        {checkbox.description}
                                                        {checkbox.description2 && (
                                                            <>
                                                                <br />
                                                                {checkbox.description2}
                                                            </>
                                                        )}
                                                    </>
                                                )
                                            }}
                                            reversePosition={true}
                                            value={checkbox.value}
                                            key={index}
                                            checked={forceLang === checkbox.value}
                                            onChange={() => {
                                                setForceLang(checkbox.value);
                                            }}
                                        />
                                    ))
                                }
                            </RadioButton.Group>
                            {
                                forceLang === 3 &&
                                <div className='flex flex-col gap-4'>
                                    {
                                        domains.map((domain, index) => (
                                            <Container.Item key={index}>
                                                <Label size='sm' className='font-semibold'>
                                                    {domain.name}<span style={{ color: "red" }}>*</span>
                                                </Label>
                                                <Input
                                                    aria-label="Text Input"
                                                    id={`input-element-${index}`}
                                                    size="sm"
                                                    type="text"
                                                    value={domain.value}
                                                    onChange={(value) => {
                                                        const updatedDomains = domains.map((d, i) => {
                                                            if (index === i) {
                                                                return { ...d, value: value };
                                                            }
                                                            return d;
                                                        });
                                                        setDomains(updatedDomains);
                                                    }}
                                                />
                                            </Container.Item>
                                        ))
                                    }
                                </div>
                            }
                        </Container.Item>
                        {/* Hide URL language information for default language */}
                        <Container.Item>
                            {
                                forceLang !== 3 &&
                                <Checkbox
                                    label={{
                                        heading: __('Hide URL language information for default language', 'translate-words')
                                    }}
                                    size="sm"
                                    className='cursor-pointer'
                                    checked={hideDefault}
                                    onChange={() => {
                                        setHideDefault(!hideDefault);
                                    }}
                                />
                            }
                            {/* Remove or Keep /language/ in pretty permalinks */}
                            {  
                                forceLang === 1 &&
                                <RadioButton.Group
                                    columns={1}
                                    className="break-all"
                                    size="sm">
                                    {
                                        directoryNamesLinks.map((checkbox, index) => (
                                            <RadioButton.Button
                                                badgeItem={<Badge className="mr-2" size="sm" type="rounded" variant="green" />}
                                                label={{
                                                    description: checkbox.description,
                                                    heading: checkbox.heading
                                                }}
                                                reversePosition={true}
                                                value={checkbox.value}
                                                key={index}
                                                checked={rewrite === checkbox.value}
                                                onChange={() => {
                                                    setRewrite(checkbox.value);
                                                }}
                                            />
                                        ))
                                    }
                                </RadioButton.Group>
                            }
                            {/* Front page URL contains the language code */}
                            {
                                forceLang < 2 && redirectLangExample.home &&
                                <Checkbox
                                    label={{
                                        heading: __('The front page URL contains the language code instead of the page name or page id', 'translate-words'),
                                        description: sprintf(__('Example: %1$s instead of %2$s', 'translate-words'), redirectLangExample.home, redirectLangExample.page)
                                    }}
                                    size="sm"
                                    className='cursor-pointer'
                                    checked={redirectLang}
                                    onChange={() => {
                                        setRedirectLang(!redirectLang);
                                    }}
                                />
                            }

                        </Container.Item>
                    </Container>

                </Container>
                <hr className="w-full border-b-0 border-x-0 border-t border-solid border-t-border-subtle" />
                {/* Custom Post Types section */}
                <Container cols="1" containerType='grid' >
                    <Container.Item className='switcher'>
                       <div>
                         <Label size='md' className='font-bold flex items-center gap-2'>
                            <Milestone className="flex-shrink-0 size-5 text-icon-secondary" />
                            {__('Custom Post Types', 'translate-words')}
                        </Label>
                        <p>{__("Choose the custom post types you want to enable for translation. For example, if you have a 'Portfolio' post type, check the box to enable it for translation.", 'translate-words')}</p>
                       </div>
                        {AvailablePostTypes.length > 0 && (
                            <div className='flex justify-end gap-2 pr-0 lg:pr-[30%]'>
                                <Label size='sm' className='cursor-pointer items-start' htmlFor="select-all-post-types">
                                    {__('Select All', 'translate-words')}
                                </Label>
                                <Switch
                                    aria-label="Select All Post Types"
                                    id="select-all-post-types"
                                    value={selectAllPostTypes}
                                    onChange={()=>{handleSelectAllPostTypes()}}
                                    size="sm"
                                />
                            </div>
                        )}
                    </Container.Item>
                    <Container.Item className='flex gap-4 flex-wrap'>
                        {
                            AvailablePostTypes.length == 0 ?
                                <div style={{ color: "red" }}>
                                    {__('No Custom Post Types Available', 'translate-words')}
                                </div> :
                                <div className='flex gap-4 flex-wrap'>
                                    {
                                        AvailablePostTypes.map((postType, index) => {
                                            const isDisabled = Array.isArray(disabledPostTypes) && disabledPostTypes.some(disabledType => {
                                                // Handle both string and object formats
                                                const postTypeKey = typeof disabledType === 'object' ? disabledType.post_type_key : disabledType;
                                                return postTypeKey === postType.value;
                                            });
                                            return (
                                                <Checkbox
                                                    label={{
                                                        description: '',
                                                        heading: postType.label + (isDisabled ? ' (Programmatically Active)' : '')
                                                    }}
                                                    className={isDisabled ? 'cursor-not-allowed opacity-60' : 'cursor-pointer'}
                                                    value={postType.value}
                                                    checked={isDisabled ? true : selectedPostTypes.includes(postType.value)}
                                                    disabled={isDisabled}
                                                    key={index}
                                                    size="sm"
                                                    onChange={() => handlePostTypeChange(postType.value)}
                                                />
                                            );
                                        })
                                    }
                                </div>
                        }
                    </Container.Item>
                </Container>
                <hr className="w-full border-b-0 border-x-0 border-t border-solid border-t-border-subtle" />
                {/* Custom Taxonomies section */}
                <Container cols="1" containerType='grid' >
                    <Container.Item className='switcher'>
                        <div>
                            <Label size='md' className='font-bold flex items-center gap-2'>
                            <Milestone className="flex-shrink-0 size-5 text-icon-secondary" />
                            {__('Custom Taxonomies', 'translate-words')}
                        </Label>
                        <p>{__('Choose the Custom Taxonomies you want to enable for translation', 'translate-words')}</p>
                        </div>
                        {AvailableTaxonomies.length > 0 && (
                            <div className='flex justify-end gap-2' style={{paddingRight: '30%'}}>
                                <Label size='sm' className='cursor-pointer items-start' htmlFor="select-all-taxonomies">
                                    {__('Select All', 'translate-words')}
                                </Label>
                                <Switch
                                    aria-label="Select All Taxonomies"
                                    id="select-all-taxonomies"
                                    value={selectAllTaxonomies}
                                    onChange={handleSelectAllTaxonomies}
                                    size="sm"
                                />
                            </div>
                        )}
                    </Container.Item>
                    <Container.Item className='flex gap-4 flex-wrap'>
                        {
                            AvailableTaxonomies.length == 0 ?
                                <div style={{ color: "red" }}>
                                    {__('No Custom Taxonomies Available', 'translate-words')}
                                </div> :
                                <div className='flex gap-4 flex-wrap'>
                                    {
                                        AvailableTaxonomies.map((taxonomy, index) => (
                                            <Checkbox
                                                label={{
                                                    description: '',
                                                    heading: taxonomy.label
                                                }}
                                                className='cursor-pointer'
                                                value={taxonomy.value}
                                                checked={selectedTaxonomies.includes(taxonomy.value)}
                                                key={index}
                                                size="sm"
                                                onChange={() => handleTaxonomyChange(taxonomy.value)}
                                            />
                                        ))
                                    }
                                </div>
                        }
                    </Container.Item>
                </Container>
                <hr className="w-full border-b-0 border-x-0 border-t border-solid border-t-border-subtle" />
                {/* Synchronization section */}
                <Container cols="1" containerType='grid' >
                    <Container.Item className='switcher'>
                        <div>
                        <Label size='md' className='font-bold flex items-center gap-2'>
                            <RefreshCcw className="flex-shrink-0 size-5 text-icon-secondary" />
                            {__('Synchronization', 'translate-words')}
                        </Label>
                        <p>{__('Choose synchronization options for translated content.', 'translate-words')}</p>
                        </div>
                        <div className='flex  justify-end  gap-2' style={{paddingRight: '30%'}}>
                            <Label size='sm' className='cursor-pointer items-start' htmlFor="select-all-sync">
                                {__('Select All', 'translate-words')}
                            </Label>

                            <Switch
                                        aria-label="Select All Synchronization"
                                        id="select-all-sync"
                                        value={selectAllSync}
                                        onChange={handleSelectAllSync}
                                        size="sm"
                                    />
                            
                        </div>
                    </Container.Item>
                    <Container.Item className='flex gap-6 flex-wrap'>
                        {
                            synchronizations.map((synchronization, index) => (
                                <Checkbox
                                    label={{
                                        description: '',
                                        heading: synchronization.label
                                    }}
                                    className='cursor-pointer'
                                    value={synchronization.value}
                                    key={index}
                                    checked={selectedSynchronization.includes(synchronization.value)}
                                    size="sm"
                                    onChange={() => handleSynchronizationChange(synchronization.value)}
                                />
                            ))
                        }
                    </Container.Item>
                </Container>
                <hr className="w-full border-b-0 border-x-0 border-t border-solid border-t-border-subtle" />
                {/* Detect Browser Language section */}
                <div className='switcher'>
                    <Container.Item >
                        <h3 className='flex items-center gap-2'>
                            <Globe className="flex-shrink-0 size-5 text-icon-secondary" />
                            {__('Detect Browser Language', 'translate-words')}
                        </h3>
                        <p>
                            {__('When visitors open your homepage, Linguator displays it in their preferred language. To avoid issues, homepage caching is turned off for supported cache plugins.', 'translate-words')}
                        </p>
                    </Container.Item>
                    <Container.Item className='flex items-center justify-end' style={{paddingRight: '30%'}}>
                        {
                            forceLang === 3 ?
                                <Switch
                                    aria-label="Switch Element"
                                    id="browser-support"
                                    onChange={() => {
                                        setBrowser(!browser)

                                    }}
                                    disabled={true}
                                    value={browser}
                                    size="sm"
                                /> :
                                <Switch
                                    aria-label="Switch Element"
                                    id="browser-support"
                                    onChange={() => {
                                        setBrowser(!browser)
                                    }}
                                    value={browser}
                                    size="sm"
                                />
                        }
                    </Container.Item>
                </div>
                
                <hr className="w-full border-b-0 border-x-0 border-t border-solid border-t-border-subtle" />
                {/* Media section */}
                <div className='switcher'>
                    <Container.Item>
                        <h3 className='flex items-center gap-2'>
                            <Focus className="flex-shrink-0 size-5 text-icon-secondary" />
                            {__('Media', 'translate-words')}
                        </h3>
                        <p>
                            {__('Turn on media translation only if you need to translate titles, alt text, captions, or descriptions. The original file stays the same.', 'translate-words')}
                        </p>
                    </Container.Item>
                    <Container.Item className='flex items-center justify-end' style={{paddingRight: '30%'}}>
                        <Switch
                            aria-label="Switch Element"
                            id="media-support"
                            onChange={() => {
                                setMediaSupport(!mediaSupport)
                            }}
                            size="sm"
                            value={mediaSupport}
                        />
                    </Container.Item>
                </div>

                <hr className="w-full border-b-0 border-x-0 border-t border-solid border-t-border-subtle" />
                {/* Menu Sync section */}
                <div className='switcher'>
                    <Container.Item>
                        <h3 className='flex items-center gap-2'>
                            <Menu className="flex-shrink-0 size-5 text-icon-secondary" />
                            {__('Menu Sync', 'translate-words')}
                        </h3>
                        <p>
                            {__('Enable or disable the Menu Sync feature. This feature allows you to synchronize menu structures across different language versions of your site.', 'translate-words')}
                        </p>
                    </Container.Item>
                    <Container.Item className='flex items-center justify-end' style={{paddingRight: '30%'}}>
                        <Switch
                            aria-label="Switch Element"
                            id="menu-sync-visibility"
                            onChange={() => {
                                setMenuSyncVisibility(!menuSyncVisibility)
                            }}
                            size="sm"
                            value={menuSyncVisibility}
                        />
                    </Container.Item>
                </div>

                {/* Linguator Pro sections */}
                {PRO_TOGGLES.map((toggle) => {
                    // Without Linguator Pro the setting is absent: show it locked, linking to the Pro page.
                    const locked = data[toggle.key] === undefined;
                    const unavailable = !locked && toggle.requiresUrlLanguage && 0 === forceLang;
                    const proUrl = getProUrl(toggle.key);
                    return (
                        <React.Fragment key={toggle.key}>
                            <hr className="w-full border-b-0 border-x-0 border-t border-solid border-t-border-subtle" />
                            <div className='switcher'>
                                <Container.Item>
                                    <h3 className='flex items-center gap-2'>
                                        <Link className="flex-shrink-0 size-5 text-icon-secondary" />
                                        {toggle.title}
                                        {locked ? (
                                            <a href={proUrl} target='_blank' rel='noopener noreferrer' className='no-underline'>
                                                <Badge label={__('Pro', 'translate-words')} size="xs" variant="blue" />
                                            </a>
                                        ) : (
                                            <Badge label={__('Pro', 'translate-words')} size="xs" variant="blue" />
                                        )}
                                    </h3>
                                    <p>{toggle.description}</p>
                                    {unavailable && (
                                        <p className='text-red-600'>
                                            {__('Not available while the language is set from the content. Choose a URL format that includes the language to use it.', 'translate-words')}
                                        </p>
                                    )}
                                </Container.Item>
                                <Container.Item className='flex items-center justify-end' style={{paddingRight: '30%'}}>
                                    {locked ? (
                                        <Switch
                                            aria-label={sprintf(
                                                /* translators: %s: setting name */
                                                __('%s (available in Linguator Pro)', 'translate-words'),
                                                toggle.title
                                            )}
                                            id={`lmat-pro-${toggle.key}`}
                                            disabled
                                            size="sm"
                                            value={false}
                                        />
                                    ) : (
                                    <Switch
                                        aria-label={toggle.title}
                                        id={`lmat-pro-${toggle.key}`}
                                        disabled={unavailable}
                                        onChange={() => {
                                            setProToggles(prev => ({ ...prev, [toggle.key]: !prev[toggle.key] }))
                                        }}
                                        size="sm"
                                        value={unavailable ? false : !!proToggles[toggle.key]}
                                    />
                                    )}
                                </Container.Item>
                            </div>
                            {/* Add-on fields of this switch, shown while it is on. */}
                            {!locked && !unavailable && proToggles[toggle.key] && extraFields
                                .filter((field) => field.parentToggle === toggle.key)
                                .map((field) => (
                                    <Container.Item key={field.key} className='mt-4' style={{ paddingRight: '30%' }}>
                                        {field.render({
                                            value: extraValues[field.key],
                                            onChange: (value) => setExtraValues(prev => ({ ...prev, [field.key]: value })),
                                            data,
                                            languages: window.lmat_settings.languages || [],
                                        })}
                                    </Container.Item>
                                ))}
                        </React.Fragment>
                    );
                })}


                {data.lmat_feedback_data !== undefined && (
                    <>
                        <hr className="w-full border-b-0 border-x-0 border-t border-solid border-t-border-subtle" />
                        <div className='switcher'>
                            <Container.Item>
                                <h3 className='flex items-center gap-2'>
                                    <Share2 className="flex-shrink-0 size-5 text-icon-secondary" />
                                    {__('Usage Data Sharing', 'translate-words')}
                                </h3>
                                <div>
                                    <span>{__('Help us make this plugin more compatible with your site by sharing non-sensitive site data. ', 'translate-words')}</span>
                                    <a href="#" className="lmat-see-terms" onClick={handleTermsToggle}>[{showTerms ? 'Hide terms' : 'See terms'}]</a>
                                    <div id="termsBox" className="lmat-terms-box" style={{display: showTerms ? 'block' : 'none', paddingLeft: '20px', marginTop: '10px', fontSize: '12px', color: '#999'}}>
                                        <p>{__("Opt in to receive email updates about security improvements, new features, helpful tutorials, and occasional special offers. We'll collect:", 'translate-words')} <a href='https://my.coolplugins.net/terms/usage-tracking/' target='_blank' rel="noopener noreferrer">Click Here</a></p>
                                        <ul style={{listStyleType: 'auto', paddingLeft: '20px'}}>
                                            <li>{__("Your website home URL and WordPress admin email.", 'translate-words')}</li>
                                            <li>{__("To check plugin compatibility, we will collect the following: list of active plugins and themes, server type, MySQL version, WordPress version, memory limit, site language and database prefix.", 'translate-words')}</li>
                                        </ul>
                                    </div>
                                </div>
                            </Container.Item>
                            <Container.Item className='flex items-center justify-end' style={{paddingRight: '30%'}}>
                                <Switch
                                    aria-label="Switch Element"
                                    id="lmat_feedback_data"
                                    onChange={() => {
                                        
                                        setLmatFeedbackData(!lmatFeedbackData)
                                    }}
                                    size="sm"
                                    value={lmatFeedbackData}
                                />
                            </Container.Item>
                        </div>
                    </>
                )}
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
        </>
    )
}

export default General