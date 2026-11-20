import { useEffect, useRef } from 'react'

// Shows markup that PHP printed for a server-rendered tab (see linguator_get_settings_server_tabs()).
// The node is moved, not copied, so event handlers already bound to it (jQuery, forms) keep working.
// On unmount it goes back, hidden, to where PHP printed it, so the tab can be shown again.
const ServerTabSlot = ({ id, className = '' }) => {
  const ref = useRef(null)

  useEffect(() => {
    const node = document.getElementById(id)
    if (!node || !ref.current) {
      return undefined
    }

    const home = node.parentNode
    ref.current.appendChild(node)
    node.hidden = false

    return () => {
      node.hidden = true
      if (home) {
        home.appendChild(node)
      }
    }
  }, [id])

  return <div ref={ref} className={className} />
}

export default ServerTabSlot
