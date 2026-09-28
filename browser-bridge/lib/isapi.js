import { digestFetch } from './digest.js'

/**
 * The only terminal calls the extension makes. Both are read-only and are the same ISAPI calls the CRM's
 * server-side adapter and hik-bridge use:
 *   GET  /ISAPI/System/deviceInfo
 *   POST /ISAPI/AccessControl/AcsEvent?format=json   (major 5, paginated while responseStatusStrg = "MORE")
 */

export const MAX_EVENTS_PER_SEARCH = 5000
const TIME = /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/

export class TerminalError extends Error {
  /** @param {'unreachable'|'auth'|'http'|'not_configured'|'no_permission'|'invalid'} code */
  constructor(code, message) {
    super(message)
    this.code = code
  }
}

/** "http://192.168.1.64:80/" → "http://192.168.1.64"; anything that is not a bare http(s) origin → null. */
export function normalizeOrigin(value) {
  try {
    const url = new URL(String(value).trim())
    if (!['http:', 'https:'].includes(url.protocol) || url.username || url.password) return null
    if ((url.pathname !== '/' && url.pathname !== '') || url.search || url.hash) return null
    return url.origin
  } catch {
    return null
  }
}

export async function deviceInfo(terminal, options = {}) {
  const res = await call(terminal, '/ISAPI/System/deviceInfo', { method: 'GET' }, options)
  const xml = await res.text()
  const field = (name) => new RegExp(`<${name}>([^<]*)</${name}>`).exec(xml)?.[1]?.trim() || null
  return { model: field('model'), serialNumber: field('serialNumber'), firmwareVersion: field('firmwareVersion'), deviceName: field('deviceName') }
}

/** Access-control events between two device-local times ("2026-09-21T08:00:00+05:00"). */
export async function searchEvents(terminal, { startTime, endTime, pageSize = 30, maxEvents = MAX_EVENTS_PER_SEARCH }, options = {}) {
  if (!TIME.test(startTime ?? '') || !TIME.test(endTime ?? '')) throw new TerminalError('invalid', 'startTime and endTime must look like 2026-09-21T08:00:00+05:00.')

  const searchID = `browser-${Date.now()}`
  const events = []
  let truncated = false

  for (let position = 0; ;) {
    const body = JSON.stringify({ AcsEventCond: { searchID, searchResultPosition: position, maxResults: pageSize, major: 5, minor: 0, startTime, endTime } })
    const res = await call(terminal, '/ISAPI/AccessControl/AcsEvent?format=json', {
      method: 'POST', body, headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    }, options)

    const json = await res.json().catch(() => {
      throw new TerminalError('http', 'The terminal answered the event search with something that is not JSON.')
    })
    let list = json?.AcsEvent?.InfoList ?? []
    if (!Array.isArray(list)) list = [list] // some firmwares return a single object
    events.push(...list)
    position += list.length

    const more = String(json?.AcsEvent?.responseStatusStrg ?? 'OK').toUpperCase() === 'MORE'
    if (!more || list.length === 0) break
    if (events.length >= maxEvents) {
      truncated = true // the CRM continues from the latest stored event on the next run
      break
    }
  }

  return { events, truncated }
}

async function call(terminal, path, init, { fetchImpl } = {}) {
  let res
  try {
    res = await digestFetch(terminal.origin + path, { username: terminal.username, password: terminal.password, fetchImpl, ...init })
  } catch (error) {
    const timeout = error?.name === 'TimeoutError' || error?.name === 'AbortError'
    throw new TerminalError('unreachable', timeout ? `The terminal at ${terminal.origin} did not answer in time.` : `The terminal at ${terminal.origin} is not reachable from this computer.`)
  }
  if (res.status === 401) throw new TerminalError('auth', 'The terminal rejected the user name or password (HTTP 401). Repeated failures can lock the account on the terminal.')
  if (!res.ok) {
    const text = await res.text().catch(() => '')
    const sub = /"subStatusCode"\s*:\s*"([^"]+)"|<subStatusCode>([^<]+)</.exec(text)
    throw new TerminalError('http', `The terminal answered HTTP ${res.status}${sub ? ` (${sub[1] ?? sub[2]})` : ''}.`)
  }
  return res
}
