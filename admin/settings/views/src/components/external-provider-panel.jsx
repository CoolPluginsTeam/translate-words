import React, { useEffect, useRef } from 'react'

/**
 * Empty element that the plugin which added an external AI provider fills with its own settings panel.
 *
 * Linguator does not know what goes inside. On mount it stores `{ key, container, setConfigured }` on the element
 * as `lmatPanel` and fires `lmat:provider-panel-mount` on `document`; on unmount it fires
 * `lmat:provider-panel-unmount`. The other plugin's script mounts into `container` (also when it loads after this
 * element exists, by reading `lmatPanel`) and calls `setConfigured(true|false)` when the provider becomes usable.
 *
 * @param {Object}   props
 * @param {string}   props.providerKey    Provider key from `lmat_external_ai_providers`.
 * @param {Function} props.onStatusChange Receives whether the provider is ready to translate.
 */
const ExternalProviderPanel = ({ providerKey, onStatusChange }) => {
    const containerRef = useRef(null)
    // Kept in a ref so the other plugin's callback always reaches the latest handler.
    const onStatusChangeRef = useRef(onStatusChange)
    onStatusChangeRef.current = onStatusChange

    useEffect(() => {
        const container = containerRef.current
        if (!container) {
            return undefined
        }

        const detail = {
            key: providerKey,
            container,
            setConfigured: (configured) => onStatusChangeRef.current?.(Boolean(configured)),
        }
        container.lmatPanel = detail
        document.dispatchEvent(new CustomEvent('lmat:provider-panel-mount', { detail }))

        return () => {
            document.dispatchEvent(new CustomEvent('lmat:provider-panel-unmount', { detail: { key: providerKey, container } }))
            delete container.lmatPanel
        }
    }, [providerKey])

    return <div ref={containerRef} className="lmat-external-provider-panel" data-provider={providerKey} />
}

export default ExternalProviderPanel
