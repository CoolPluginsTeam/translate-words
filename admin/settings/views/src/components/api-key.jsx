import React, { useEffect, useRef, useState, useCallback, useMemo, forwardRef, useImperativeHandle } from 'react'
import { Button, Container, Input, Label } from '@bsf/force-ui'
import apiFetch from "@wordpress/api-fetch"
import { toast } from 'sonner'
import { __, sprintf } from '@wordpress/i18n'
import { getNonce } from '../utils'

const providerKeyLinks = {
  gemini: 'https://aistudio.google.com/app/api-keys',
  ollama: 'https://ollama.com/settings/keys',
}

const providerKeyLabels = {
  gemini: 'Gemini',
  ollama: 'Ollama',
}

const providerMeta = [
  {
    key: 'gemini',
    modelKey: 'gemini_model',
    heading: __('Add Gemini API key', 'translate-words'),
    modelHeading: __('Select Gemini Model', 'translate-words'),
    placeholder: __('Enter your API key', 'translate-words'),
  },
  {
    key: 'ollama',
    modelKey: 'ollama_model',
    heading: __('Add Ollama API key', 'translate-words'),
    modelHeading: __('Select Ollama Model', 'translate-words'),
    placeholder: __('Enter your API key', 'translate-words'),
  },
]

const ApiKey = forwardRef(function ApiKey({ data, setData, embedded = false, onPendingChange, providerKeys }, ref) {
  const normalizeApiKey = useCallback((v) => (v || '').toString().replace(/\s+/g, '').trim(), [])
  const providerFilter = Array.isArray(providerKeys) ? providerKeys.join('|') : ''
  const activeProviderMeta = useMemo(
    () => Array.isArray(providerKeys)
      ? providerMeta.filter(({ key }) => providerKeys.includes(key))
      : providerMeta,
    [providerFilter]
  )
  const [loading, setLoading] = useState(true)
  const [masked, setMasked] = useState({ gemini: '', ollama: '' })
  const [configured, setConfigured] = useState({ gemini: false, ollama: false })
  const [keyDrafts, setKeyDrafts] = useState({ gemini: '', ollama: '' })
  const [availableModels, setAvailableModels] = useState({ gemini: [], ollama: [] })
  const [models, setModels] = useState({
    gemini_model: 'gemini-2.5-flash',
    ollama_model: 'gemma4:31b',
  })
  const [handleButtonDisabled, setHandleButtonDisabled] = useState(true)
  const initialModelsRef = useRef({
    gemini_model: 'gemini-2.5-flash',
    ollama_model: 'gemma4:31b',
  })

  const computeHasPendingSave = useCallback(() => {
    const hasKeyChanges = activeProviderMeta.some(({ key }) => (keyDrafts[key] || '').trim() !== '')
    const initial = initialModelsRef.current || {}
    const hasModelChanges = activeProviderMeta.some(({ modelKey }) =>
      (models[modelKey] || '') !== (initial[modelKey] || '')
    )
    return hasKeyChanges || hasModelChanges
  }, [keyDrafts, models, activeProviderMeta])

  useEffect(() => {
    const config = data?.api_keys_configuration
    if (!config) return

    const keys = config?.keys || {}
    const m = config?.models || {}
    const discovered = config?.available_models || {}

    const nextMasked = {
      gemini: keys?.gemini || '',
      ollama: keys?.ollama || '',
    }

    setMasked(nextMasked)
    setConfigured({
      gemini: Boolean(nextMasked.gemini),
      ollama: Boolean(nextMasked.ollama),
    })
    const nextModels = {
      gemini_model: m?.gemini_model || 'gemini-2.5-flash',
      ollama_model: m?.ollama_model || 'gemma4:31b',
    }
    setModels(nextModels)
    initialModelsRef.current = nextModels
    setAvailableModels({
      gemini:
        Array.isArray(discovered?.gemini) || (discovered?.gemini && typeof discovered.gemini === 'object')
          ? discovered.gemini
          : [],
      ollama:
        Array.isArray(discovered?.ollama) || (discovered?.ollama && typeof discovered.ollama === 'object')
          ? discovered.ollama
          : [],
    })
    setLoading(false)
  }, [data?.api_keys_configuration])

  // Fallback: if settings didn't include api_keys_configuration for some reason, fetch once.
  useEffect(() => {
    if (data?.api_keys_configuration) return
    let cancelled = false

    async function load() {
      try {
        const resp = await apiFetch({
          path: 'lmat/v1/settings',
          method: 'GET',
          headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': getNonce(),
          },
        })
        if (cancelled) return

        const config = resp?.api_keys_configuration || {}
        const keys = config?.keys || {}
        const m = config?.models || {}
        const discovered = config?.available_models || {}

        const nextMasked = {
          gemini: keys?.gemini || '',
          ollama: keys?.ollama || '',
        }

        setMasked(nextMasked)
        setConfigured({
          gemini: Boolean(nextMasked.gemini),
          ollama: Boolean(nextMasked.ollama),
        })
        const nextModels = {
          gemini_model: m?.gemini_model || 'gemini-2.5-flash',
          ollama_model: m?.ollama_model || 'gemma4:31b',
        }
        setModels(nextModels)
        initialModelsRef.current = nextModels
        setAvailableModels({
          gemini:
            Array.isArray(discovered?.gemini) || (discovered?.gemini && typeof discovered.gemini === 'object')
              ? discovered.gemini
              : [],
          ollama:
            Array.isArray(discovered?.ollama) || (discovered?.ollama && typeof discovered.ollama === 'object')
              ? discovered.ollama
              : [],
        })
      } finally {
        if (!cancelled) setLoading(false)
      }
    }

    load()
    return () => {
      cancelled = true
    }
  }, [])

  useEffect(() => {
    const hasKeyChanges = activeProviderMeta.some(({ key }) => (keyDrafts[key] || '').trim() !== '')
    const initial = initialModelsRef.current || {}
    const hasModelChanges = activeProviderMeta.some(({ modelKey }) =>
      (models[modelKey] || '') !== (initial[modelKey] || '')
    )

    setHandleButtonDisabled(!(hasKeyChanges || hasModelChanges))
  }, [keyDrafts, models, configured])

  useEffect(() => {
    if (!embedded || !onPendingChange) return
    onPendingChange(computeHasPendingSave())
  }, [embedded, onPendingChange, computeHasPendingSave])

  const persistApiKeys = useCallback(async ({ resetProvider } = {}) => {
    const keys = {}
    const modelsBody = {}

    for (const { key, modelKey } of activeProviderMeta) {
      const draft = normalizeApiKey(keyDrafts[key])

      if (resetProvider === key) {
        keys[key] = ''
        continue
      }

      if (draft !== '') {
        keys[key] = draft
      }

      if (resetProvider !== key && (configured[key] || draft !== '')) {
        modelsBody[modelKey] = models[modelKey]
      }
    }

    const apiBody = { keys, models: modelsBody }

    // Save via Settings route
    const resp = await apiFetch({
      path: 'lmat/v1/settings',
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': getNonce(),
      },
      body: JSON.stringify(apiBody),
    })

    if (setData && resp) {
      setData((prev) => ({ ...(prev || {}), ...(resp || {}) }))
    }

    const nextModels = { ...models, ...modelsBody }
    setModels(nextModels)
    initialModelsRef.current = nextModels

    const responseConfig = resp?.api_keys_configuration || {}
    const responseKeys = responseConfig?.keys || {}
    const nextConfigured = { ...configured }
    const nextMasked = { ...masked }
    for (const { key } of activeProviderMeta) {
      if (!Object.prototype.hasOwnProperty.call(responseKeys, key)) continue
      const responseMask = typeof responseKeys[key] === 'string' ? responseKeys[key] : ''
      nextConfigured[key] = responseMask !== ''
      nextMasked[key] = responseMask
    }
    setConfigured(nextConfigured)
    setMasked(nextMasked)

    setKeyDrafts({ gemini: '', ollama: '' })
    setHandleButtonDisabled(true)

    // Update models directly from the save response
    const discovered = responseConfig?.available_models || {}
    setAvailableModels({
      gemini:
        Array.isArray(discovered?.gemini) || (discovered?.gemini && typeof discovered.gemini === 'object')
          ? discovered.gemini
          : [],
      ollama:
        Array.isArray(discovered?.ollama) || (discovered?.ollama && typeof discovered.ollama === 'object')
          ? discovered.ollama
          : [],
    })
  }, [keyDrafts, models, configured, activeProviderMeta])

  useImperativeHandle(ref, () => ({
    hasConfiguredKey: (provider) => {
      const key = typeof provider === 'string' ? provider : ''
      if (!key) return false
      if (configured?.[key]) return true
      const m = (masked?.[key] || '').toString().trim()
      return m !== ''
    },
    getPendingPayload: () => {
      const hasKeyChanges = activeProviderMeta.some(({ key }) => (keyDrafts[key] || '').trim() !== '')
      const initial = initialModelsRef.current || {}
      const hasModelChanges = activeProviderMeta.some(({ modelKey }) =>
        (models[modelKey] || '') !== (initial[modelKey] || '')
      )
      if (!hasKeyChanges && !hasModelChanges) return null

      const keys = {}
      for (const { key } of activeProviderMeta) {
        const draft = normalizeApiKey(keyDrafts[key])
        if (draft !== '') keys[key] = draft
      }

      const payload = {}
      if (Object.keys(keys).length) payload.keys = keys
      if (hasModelChanges) {
        payload.models = activeProviderMeta.reduce((result, { modelKey }) => ({
          ...result,
          [modelKey]: models[modelKey],
        }), {})
      }
      return payload
    },
    syncAfterParentSave: (payload, settingsResponse) => {
      if (!payload || typeof payload !== 'object') return

      if (payload.models && typeof payload.models === 'object') {
        const nextModels = {
          gemini_model: payload.models.gemini_model ?? models.gemini_model,
          ollama_model: payload.models.ollama_model ?? models.ollama_model,
        }
        setModels(nextModels)
        initialModelsRef.current = nextModels
      }

      const config = settingsResponse?.api_keys_configuration || {}
      const responseKeys = config?.keys || {}
      const nextConfigured = { ...configured }
      const nextMasked = { ...masked }
      let hasKeyState = false
      for (const { key } of activeProviderMeta) {
        if (!Object.prototype.hasOwnProperty.call(responseKeys, key)) continue
        const responseMask = typeof responseKeys[key] === 'string' ? responseKeys[key] : ''
        nextConfigured[key] = responseMask !== ''
        nextMasked[key] = responseMask
        hasKeyState = true
      }
      if (hasKeyState) {
        setConfigured(nextConfigured)
        setMasked(nextMasked)
      }

      setKeyDrafts({ gemini: '', ollama: '' })
      setHandleButtonDisabled(true)

      // If parent save returned models, apply them (no extra GET).
      const discovered = config?.available_models || {}
      if (discovered && typeof discovered === 'object') {
        setAvailableModels({
          gemini:
            Array.isArray(discovered?.gemini) || (discovered?.gemini && typeof discovered.gemini === 'object')
              ? discovered.gemini
              : [],
          ollama:
            Array.isArray(discovered?.ollama) || (discovered?.ollama && typeof discovered.ollama === 'object')
              ? discovered.ollama
              : [],
        })
      }
    },
  }), [keyDrafts, models, configured, masked, normalizeApiKey, activeProviderMeta])

  async function SaveSettings({ resetProvider } = {}) {
    try {
      const run = persistApiKeys({ resetProvider }).catch((error) => {
        if (error?.message) {
          throw new Error(error.message)
        }
        throw new Error(__("Something went wrong", 'translate-words'))
      })

      const showToast = !embedded || resetProvider
      if (showToast) {
        toast.promise(run, {
          loading: resetProvider
            ? __('API key resetting…', 'translate-words')
            : __('Saving Settings', 'translate-words'),
          success: resetProvider
            ? __('API key reset', 'translate-words')
            : __('Settings Saved', 'translate-words'),
          error: (error) => error.message,
        })
      } else {
        await run
      }
    } catch (error) {
      toast.error(error?.message || __("Something went wrong", "translate-words"))
    }
  }

  // Keep provider visibility in sync with Translation Config + Wizard toggles (both persist to ai_translation_configuration.provider)
  const wpAiClientAvailable = Boolean(window?.lmat_settings?.wp_ai_client_available || window?.lmat_setup?.wp_ai_client_available)
  const providerConfig = data?.ai_translation_configuration?.provider
  const visibleProviders = providerMeta.filter((p) => {
    if (Array.isArray(providerKeys) && !providerKeys.includes(p.key)) return false
    if (p.key === 'gemini' && !wpAiClientAvailable) return false
    // Embedded fields are controlled by the parent provider toggle.
    if (embedded) return true
    // If provider settings aren't present yet, default to showing the inputs.
    if (!providerConfig) return true
    // If provider settings exist, show only the providers that are explicitly enabled.
    return Boolean(providerConfig?.[p.key])
  })

  return (
    <Container
      className={
        embedded
          ? 'bg-transparent p-0 shadow-none'
          : 'bg-white p-10 rounded-lg shadow-sm'
      }
      cols="1"
      containerType='grid'
    >
      <Container cols="1" containerType="grid" className="gap-8">
        {visibleProviders.map((p) => {
          const isConfigured = configured[p.key]
          const draft = keyDrafts[p.key] || ''
          const displayValue = draft === '' && isConfigured ? (masked[p.key] || '') : draft
          const selectedModel = (models?.[p.modelKey] || '').trim()
          const listRaw = availableModels?.[p.key]
          const listFromApi = Array.isArray(listRaw)
            ? listRaw
            : (listRaw && typeof listRaw === 'object' ? Object.keys(listRaw) : [])
          const labelsMap = (!Array.isArray(listRaw) && listRaw && typeof listRaw === 'object') ? listRaw : {}
          const providerModelList = [
            ...listFromApi,
            ...(selectedModel && !listFromApi.includes(selectedModel) ? [selectedModel] : []),
          ]
          const normalizeModelLabel = (metadata, fallback) => {
            if (typeof metadata === 'string') return metadata
            if (!metadata || typeof metadata !== 'object' || typeof metadata.label !== 'string') return fallback

            const qualifiers = []
            if (metadata.fast) qualifiers.push(__('Fast', 'translate-words'))

            return qualifiers.length ? `${metadata.label} (${qualifiers.join(', ')})` : metadata.label
          }
          const getModelLabel = (id) => {
            if (labelsMap?.[id]) return normalizeModelLabel(labelsMap[id], id)
            // Some providers return version-suffixed ids (e.g. -001). Try a base-id lookup.
            const base = typeof id === 'string' ? id.replace(/-\\d+$/, '') : ''
            if (base && labelsMap?.[base]) return normalizeModelLabel(labelsMap[base], id)
            return id
          }

          return (
            <Container.Item
              key={p.key}
            >
              <h3 className={`m-0 text-base font-semibold mb-3 ${embedded ? 'pt-3' : ''}`}>
                {p.heading}
              </h3>
              <div className="flex items-start gap-3">
                <div className="flex-1" style={{ maxWidth: 400 }}>
                  <Input
                    aria-label={`${p.key}-api-key`}
                    id={`${p.key}-api-key`}
                    size="md"
                    type="text"
                    placeholder={p.placeholder}
                    disabled={isConfigured}
                    value={displayValue}
                    onChange={(v) => {
                      if (isConfigured) return
                      setKeyDrafts((prev) => ({ ...prev, [p.key]: v }))
                    }}
                  />
                  {providerKeyLinks[p.key] ? (
                    <div className="mt-2 text-sm text-gray-600">
                      {sprintf(__('Get your %s API key from ', 'translate-words'), providerKeyLabels[p.key] || p.key)}{' '}
                      <a href={providerKeyLinks[p.key]} target="_blank" rel="noopener noreferrer">
                        {__('here', 'translate-words')}
                      </a>
                    </div>
                  ) : null}
                </div>

                <div
                  className="flex-shrink-0"
                  style={embedded ? { paddingRight: '3em' } : undefined}
                >
                  <Button
                    size="md"
                    tag="button"
                    type="button"
                    variant="primary"
                    onClick={() => SaveSettings({ resetProvider: p.key })}
                    disabled={!isConfigured}
                  >
                    {__('Reset', 'translate-words')}
                  </Button>
                </div>
              </div>

              {isConfigured ? (
                <div className="mt-8">
                  <Label size="sm" className="font-medium mb-2 block">{p.modelHeading}</Label>
                  <div className="mt-0" style={{ maxWidth: 320 }}>
                    <select
                      className="w-full border border-gray-300 rounded-md px-3 py-2 text-sm bg-white"
                      value={models[p.modelKey] || ''}
                      onChange={(e) => setModels((prev) => ({ ...prev, [p.modelKey]: e.target.value }))}
                    >
                      {providerModelList.map((id) => (
                        <option key={id} value={id}>{getModelLabel(id)}</option>
                      ))}
                    </select>
                  </div>
                </div>
              ) : null}
            </Container.Item>
          )
        })}
      </Container>
    </Container>
  )
})

ApiKey.displayName = 'ApiKey'

export default ApiKey
