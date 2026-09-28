// Relays requests between an Eversoft CRM page and the extension. Injected only on CRM origins (manifest.json).
// Page → { tag, dir: 'request', id, type, payload }; reply → { tag, dir: 'response', id, ok, result | error }.
(() => {
  const TAG = 'eversoft-terminal-bridge'
  const post = (message) => window.postMessage({ tag: TAG, ...message }, location.origin)

  window.addEventListener('message', (event) => {
    if (event.source !== window || event.origin !== location.origin) return
    const m = event.data
    if (!m || m.tag !== TAG || m.dir !== 'request' || typeof m.id !== 'string') return

    const reply = (body) => post({ dir: 'response', id: m.id, ...body })
    try {
      chrome.runtime.sendMessage({ type: m.type, payload: m.payload }, (response) => {
        if (chrome.runtime.lastError) reply({ ok: false, error: { code: 'extension', message: chrome.runtime.lastError.message } })
        else reply(response ?? { ok: false, error: { code: 'extension', message: 'The extension did not answer.' } })
      })
    } catch {
      // Thrown when the extension was reloaded or updated while this page stayed open.
      reply({ ok: false, error: { code: 'extension', message: 'The terminal bridge extension was updated. Reload this page.' } })
    }
  })

  post({ dir: 'announce', version: chrome.runtime.getManifest().version })
})()
