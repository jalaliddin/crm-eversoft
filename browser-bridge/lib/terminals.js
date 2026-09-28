import { TerminalError, normalizeOrigin } from './isapi.js'

/**
 * Terminal credentials live only in this browser profile (chrome.storage.local). They are never sent to the CRM.
 * Shape: { [origin]: { username, password, lastOkAt, lastError } }
 */
export async function allTerminals() {
  return (await chrome.storage.local.get('terminals')).terminals ?? {}
}

export async function saveTerminal(origin, changes) {
  const terminals = await allTerminals()
  terminals[origin] = { ...terminals[origin], ...changes }
  await chrome.storage.local.set({ terminals })
}

export async function removeTerminal(origin) {
  const terminals = await allTerminals()
  delete terminals[origin]
  await chrome.storage.local.set({ terminals })
  await chrome.permissions.remove({ origins: [`${origin}/*`] }).catch(() => {})
}

export async function hasPermission(origin) {
  return chrome.permissions.contains({ origins: [`${origin}/*`] })
}

/** Credentials for a terminal the user set up here and allowed the extension to reach. */
export async function requireTerminal(value) {
  const origin = normalizeOrigin(value)
  if (!origin) throw new TerminalError('invalid', 'Invalid terminal address.')
  const saved = (await allTerminals())[origin]
  if (!saved?.password) throw new TerminalError('not_configured', `This browser has no password for ${origin} yet.`)
  if (!(await hasPermission(origin))) throw new TerminalError('no_permission', `The extension is not allowed to reach ${origin} yet.`)
  return { origin, username: saved.username || 'admin', password: saved.password }
}
