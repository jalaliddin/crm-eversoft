import { TerminalError, deviceInfo, normalizeOrigin, searchEvents } from './lib/isapi.js'
import { allTerminals, hasPermission, requireTerminal, saveTerminal } from './lib/terminals.js'

/**
 * Answers requests from Eversoft CRM pages (relayed by content.js). The CRM page decides what to read and
 * sends the events to the CRM with the signed-in user's session; this worker only reads the terminal.
 */

// Same origins as the content script in manifest.json.
const CRM_ORIGIN = /^(https:\/\/([a-z0-9-]+\.)*crm\.eversoft\.uz|http:\/\/(localhost|127\.0\.0\.1)(:\d+)?)$/

chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
  const origin = sender.origin ?? (sender.url ? new URL(sender.url).origin : '')
  if (sender.id !== chrome.runtime.id || !sender.tab || !CRM_ORIGIN.test(origin)) return false

  handle(message?.type, message?.payload ?? {})
    .then((result) => sendResponse({ ok: true, result }))
    .catch((error) => sendResponse({
      ok: false,
      error: error instanceof TerminalError ? { code: error.code, message: error.message } : { code: 'error', message: String(error?.message ?? error) },
    }))
  return true // respond asynchronously
})

chrome.action.onClicked.addListener(() => chrome.runtime.openOptionsPage())

async function handle(type, payload) {
  switch (type) {
    case 'hello':
      return { version: chrome.runtime.getManifest().version }

    case 'terminalStatus': {
      const origin = normalizeOrigin(payload.origin)
      if (!origin) throw new TerminalError('invalid', 'Invalid terminal address.')
      const saved = (await allTerminals())[origin]
      return { origin, configured: !!saved?.password, permitted: await hasPermission(origin) }
    }

    case 'deviceInfo':
      return remember(payload.origin, async (terminal) => deviceInfo(terminal))

    case 'searchEvents':
      return remember(payload.origin, async (terminal) => searchEvents(terminal, { startTime: payload.startTime, endTime: payload.endTime }), { automatic: payload.automatic === true })

    case 'openSetup': {
      const origin = normalizeOrigin(payload.origin) ?? ''
      await chrome.tabs.create({ url: `${chrome.runtime.getURL('options.html')}?origin=${encodeURIComponent(origin)}` })
      return null
    }

    default:
      throw new TerminalError('invalid', `Unknown request: ${type}`)
  }
}

/** Run a terminal call and keep its outcome for the settings page. */
async function remember(origin, fn, { automatic = false } = {}) {
  const terminal = await requireTerminal(origin)
  // Terminals lock the account after repeated failed logins: after a rejected password, only a person retries
  // ("Sync now", "Test connection") until the password is changed here (saving clears lastError).
  if (automatic && (await allTerminals())[terminal.origin]?.lastError?.code === 'auth') {
    throw new TerminalError('auth', 'Paused because the terminal rejected the password, so the account does not get locked. Update the password in the extension, or press Sync now to try again.')
  }
  try {
    const result = await fn(terminal)
    await saveTerminal(terminal.origin, { lastOkAt: new Date().toISOString(), lastError: null })
    return result
  } catch (error) {
    await saveTerminal(terminal.origin, { lastError: { at: new Date().toISOString(), message: error.message, code: error.code ?? 'error' } })
    throw error
  }
}
