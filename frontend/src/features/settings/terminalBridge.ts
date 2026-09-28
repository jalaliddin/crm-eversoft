import { useSyncExternalStore } from 'react'
import type { QueryClient } from '@tanstack/react-query'
import { api } from '@/lib/api'
import type { AttendanceDevice } from '@/types/models'

/**
 * Office-browser bridge. While the CRM is open in a browser that has the Eversoft Terminal Bridge extension
 * (browser-bridge/), this page reads new punches from terminals in "Office browser" mode and posts them to the
 * CRM with the signed-in user's session. The extension keeps the terminal passwords and makes the two read-only
 * ISAPI calls; a web page cannot call the terminal itself (no CORS on the device, HTTPS page → HTTP LAN host).
 *
 * One tab per browser runs the loop (Web Locks); other tabs mirror its state over a BroadcastChannel.
 */

const TAG = 'eversoft-terminal-bridge'
export const EXTENSION_DOWNLOAD = '/downloads/eversoft-terminal-bridge.zip'
const INTERVAL_MS = 60_000
const OVERLAP_MS = 10 * 60_000
const FIRST_LOOKBACK_MS = 24 * 3600_000
const MAX_BACKLOG_MS = 7 * 24 * 3600_000
const WINDOW_MS = 24 * 3600_000
const BATCH_SIZE = 500

// ---------------------------------------------------------------- extension messaging

export class BridgeError extends Error {
  readonly code: string

  constructor(code: string, message: string) {
    super(message)
    this.code = code
  }
}

interface Pending { resolve: (value: unknown) => void; reject: (error: Error) => void; timer: number }
const pending = new Map<string, Pending>()
let listening = false
let sequence = 0

function listen() {
  if (listening) return
  listening = true
  window.addEventListener('message', (event: MessageEvent) => {
    if (event.source !== window || event.origin !== window.location.origin) return
    const m = event.data as { tag?: string; dir?: string; id?: string; ok?: boolean; result?: unknown; error?: { code?: string; message?: string }; version?: string } | null
    if (!m || m.tag !== TAG) return
    if (m.dir === 'announce') {
      setState({ extension: 'ready', version: m.version })
      return
    }
    const request = m.dir === 'response' && m.id ? pending.get(m.id) : undefined
    if (!request) return
    pending.delete(m.id!)
    window.clearTimeout(request.timer)
    if (m.ok) request.resolve(m.result)
    else request.reject(new BridgeError(m.error?.code ?? 'error', m.error?.message ?? 'The terminal bridge extension failed.'))
  })
}

export function extensionRequest<T>(type: string, payload: Record<string, unknown> = {}, timeoutMs = 10_000): Promise<T> {
  listen()
  const id = `${Date.now()}-${++sequence}`
  return new Promise<T>((resolve, reject) => {
    const timer = window.setTimeout(() => {
      pending.delete(id)
      reject(new BridgeError('timeout', 'The terminal bridge extension did not answer.'))
    }, timeoutMs)
    pending.set(id, { resolve: resolve as (value: unknown) => void, reject, timer })
    window.postMessage({ tag: TAG, dir: 'request', id, type, payload }, window.location.origin)
  })
}

// ---------------------------------------------------------------- state

export type RunStatus = 'syncing' | 'ok' | 'needs_setup' | 'unreachable' | 'error'

export interface DeviceRun {
  status: RunStatus
  at: string
  message?: string
  received?: number
  stored?: number
}

export interface BridgeState {
  extension: 'checking' | 'missing' | 'ready'
  version?: string
  /** leader: this tab reads the terminals; follower: another CRM tab in this browser does. */
  role: 'off' | 'leader' | 'follower'
  runs: Record<number, DeviceRun>
}

let state: BridgeState = { extension: 'checking', role: 'off', runs: {} }
const subscribers = new Set<() => void>()

function setState(patch: Partial<BridgeState>) {
  state = { ...state, ...patch }
  subscribers.forEach((notify) => notify())
}

export function useTerminalBridge(): BridgeState {
  return useSyncExternalStore(
    (notify) => {
      subscribers.add(notify)
      return () => subscribers.delete(notify)
    },
    () => state,
  )
}

// ---------------------------------------------------------------- terminal events

/** Same origin string the extension stores credentials under (default ports dropped). */
export function terminalOrigin(device: Pick<AttendanceDevice, 'protocol' | 'ip_address' | 'port'>): string {
  const host = (device.ip_address ?? '').includes(':') ? `[${device.ip_address}]` : device.ip_address ?? ''
  const standard = device.protocol === 'https' ? 443 : 80
  return `${device.protocol}://${host}${device.port && device.port !== standard ? `:${device.port}` : ''}`
}

/** "+05:00" for a time zone at a given instant. */
export function utcOffset(timeZone: string, at: Date): string {
  const name = new Intl.DateTimeFormat('en-US', { timeZone, timeZoneName: 'longOffset' }).formatToParts(at).find((p) => p.type === 'timeZoneName')?.value ?? ''
  return /([+-]\d{2}:\d{2})/.exec(name)?.[1] ?? '+00:00'
}

/** Date → "2026-09-21T08:30:00+05:00" in the terminal's time zone, as AcsEventCond expects. */
export function formatDeviceTime(date: Date, timeZone: string): string {
  const offset = utcOffset(timeZone, date)
  const [hours, minutes] = offset.slice(1).split(':').map(Number)
  const shift = (offset.startsWith('-') ? -1 : 1) * (hours * 60 + minutes) * 60_000
  return new Date(date.getTime() + shift).toISOString().slice(0, 19) + offset
}

export interface AcsEventInfo {
  time?: string
  employeeNoString?: string
  employeeNo?: string | number
  cardNo?: string
  serialNo?: string | number
  attendanceStatus?: string
  major?: number
  minor?: number
  name?: string
  currentVerifyMode?: string
  doorNo?: number
}

export interface CrmEvent {
  time: string
  employee_identifier: string | null
  card_number: string | null
  event_type: 'check_in' | 'check_out' | 'unknown'
  external_id: string | null
  payload: Record<string, unknown>
}

/** Keep person/card events in the shape the CRM expects (same mapping as hik-bridge). */
export function toCrmEvent(info: AcsEventInfo): CrmEvent | null {
  const identifier = String(info.employeeNoString ?? info.employeeNo ?? '').trim()
  const card = String(info.cardNo ?? '').trim()
  if ((!identifier && !card) || !info.time) return null

  const status = String(info.attendanceStatus ?? '').toLowerCase()
  const eventType = ['checkin', 'breakin', 'overtimein'].includes(status) ? 'check_in' : ['checkout', 'breakout', 'overtimeout'].includes(status) ? 'check_out' : 'unknown'

  return {
    time: info.time,
    employee_identifier: identifier || null,
    card_number: card || null,
    event_type: eventType,
    external_id: info.serialNo !== undefined && info.serialNo !== '' ? String(info.serialNo) : null,
    payload: {
      major: info.major, minor: info.minor, name: info.name, serialNo: info.serialNo,
      attendanceStatus: info.attendanceStatus, currentVerifyMode: info.currentVerifyMode, doorNo: info.doorNo, source: 'office_browser',
    },
  }
}

// ---------------------------------------------------------------- sync loop

let channel: BroadcastChannel | null = null
let runCycle: ((only?: number, manual?: boolean) => Promise<void>) | null = null
/** Per device: how far this tab has read, so quiet days are not re-read every minute. */
const readUpTo = new Map<number, number>()

function setRun(deviceId: number, run: DeviceRun) {
  setState({ runs: { ...state.runs, [deviceId]: run } })
  channel?.postMessage({ type: 'runs', runs: state.runs })
}

/** "Sync now" from any tab: the leading tab does the work. */
export function requestTerminalSync(deviceId?: number): void {
  if (state.role === 'leader' && runCycle) void runCycle(deviceId, true)
  else channel?.postMessage({ type: 'sync', deviceId })
}

async function syncDevice(device: AttendanceDevice, organizationTimezone: string, manual: boolean): Promise<number> {
  const origin = terminalOrigin(device)
  const previous = state.runs[device.id]
  setRun(device.id, { ...previous, status: 'syncing', at: previous?.at ?? new Date().toISOString() })

  try {
    const status = await extensionRequest<{ configured: boolean; permitted: boolean }>('terminalStatus', { origin })
    if (!status.configured || !status.permitted) {
      setRun(device.id, { status: 'needs_setup', at: new Date().toISOString(), message: `This browser has not been given access to ${origin} yet.` })
      return 0
    }

    const timeZone = device.settings.timezone || organizationTimezone
    const now = Date.now()
    const serverCursor = device.last_event_at ? Date.parse(device.last_event_at) : now - FIRST_LOOKBACK_MS
    const from = Math.max(serverCursor, readUpTo.get(device.id) ?? 0, now - MAX_BACKLOG_MS) - OVERLAP_MS
    let received = 0
    let stored = 0
    let posted = false
    let readTo = now

    for (let start = from; start < now; start += WINDOW_MS) {
      const end = Math.min(start + WINDOW_MS, now)
      const { events, truncated } = await extensionRequest<{ events: AcsEventInfo[]; truncated: boolean }>(
        'searchEvents',
        // Automatic reads pause after a rejected password (see the extension); "Sync now" always tries.
        { origin, startTime: formatDeviceTime(new Date(start), timeZone), endTime: formatDeviceTime(new Date(end), timeZone), automatic: !manual },
        180_000,
      )
      const mapped = events.map(toCrmEvent).filter((e): e is CrmEvent => e !== null)
      received += mapped.length

      for (let i = 0; i < mapped.length; i += BATCH_SIZE) {
        const result = await api.post<{ stored?: number }>(`/attendance-devices/${device.id}/browser-events`, { events: mapped.slice(i, i + BATCH_SIZE) })
        stored += result.data.stored ?? 0
        posted = true
      }
      if (truncated) {
        // Continue from the latest stored punch next time rather than skipping the rest of this window.
        readTo = 0
        break
      }
    }

    // Nothing new: still tell the CRM the terminal was read, so the device shows as healthy.
    if (!posted) await api.post(`/attendance-devices/${device.id}/browser-events`, { events: [] })
    if (readTo) readUpTo.set(device.id, readTo)
    setRun(device.id, { status: 'ok', at: new Date().toISOString(), received, stored })
    return stored
  } catch (error) {
    const at = new Date().toISOString()
    if (!(error instanceof BridgeError)) {
      setRun(device.id, { status: 'error', at, message: `Could not send punches to the CRM: ${(error as Error).message}` })
    } else if (['not_configured', 'no_permission'].includes(error.code)) {
      setRun(device.id, { status: 'needs_setup', at, message: error.message })
    } else if (['auth', 'http'].includes(error.code)) {
      // The terminal answered but refused or failed: worth showing on the device for everyone.
      await api.post(`/attendance-devices/${device.id}/browser-events`, { events: [], error: error.message }).catch(() => undefined)
      setRun(device.id, { status: 'error', at, message: error.message })
    } else {
      // Unreachable usually means this computer is not on the office network; keep that local.
      setRun(device.id, { status: error.code === 'unreachable' ? 'unreachable' : 'error', at, message: error.message })
    }
    return 0
  }
}

/** Start the loop for this tab. Returns a stop function (for a React effect). */
export function startTerminalBridge({ queryClient, timezone }: { queryClient: QueryClient; timezone: string }): () => void {
  listen()
  const abort = new AbortController()
  let timer: number | undefined
  let running: Promise<void> | null = null

  const refresh = () => {
    void queryClient.invalidateQueries({ queryKey: ['attendance-devices'] })
  }

  const cycle = (only?: number, manual = false): Promise<void> => {
    running ??= (async () => {
      try {
        const devices = (await api.get<AttendanceDevice[]>('/attendance-devices'))
          .filter((d) => d.connection_mode === 'browser' && d.status === 'active' && d.ip_address && (only === undefined || d.id === only))
        let stored = 0
        for (const device of devices) stored += await syncDevice(device, timezone, manual)
        if (devices.length) refresh()
        if (stored) {
          void queryClient.invalidateQueries({ queryKey: ['attendance'] })
          void queryClient.invalidateQueries({ queryKey: ['raw-events'] })
        }
      } catch {
        // Signed out or the CRM is unreachable; try again next minute.
      } finally {
        running = null
      }
    })()
    return running
  }

  channel = typeof BroadcastChannel === 'undefined' ? null : new BroadcastChannel(TAG)
  channel?.addEventListener('message', (event: MessageEvent<{ type: string; runs?: BridgeState['runs']; deviceId?: number }>) => {
    const m = event.data
    if (m.type === 'runs' && state.role === 'follower' && m.runs) {
      setState({ runs: m.runs })
      refresh()
    } else if (m.type === 'sync' && state.role === 'leader') {
      void cycle(m.deviceId, true)
    } else if (m.type === 'hello' && state.role === 'leader') {
      channel?.postMessage({ type: 'runs', runs: state.runs })
    }
  })

  const lead = () => {
    setState({ role: 'leader' })
    runCycle = cycle
    void cycle()
    timer = window.setInterval(() => void cycle(), INTERVAL_MS)
    // Hold the lock until this tab stops; the next CRM tab then takes over.
    return new Promise<void>((resolve) => abort.signal.addEventListener('abort', () => resolve()))
  }

  void (async () => {
    try {
      const hello = await extensionRequest<{ version: string }>('hello', {}, 1500)
      setState({ extension: 'ready', version: hello.version })
    } catch {
      if (state.extension !== 'ready') setState({ extension: 'missing' })
      return
    }
    if (abort.signal.aborted) return
    if (!navigator.locks) {
      void lead()
      return
    }
    setState({ role: 'follower' })
    channel?.postMessage({ type: 'hello' })
    navigator.locks.request(TAG, { signal: abort.signal }, lead).catch(() => undefined)
  })()

  return () => {
    abort.abort()
    window.clearInterval(timer)
    channel?.close()
    channel = null
    runCycle = null
    setState({ role: 'off' })
  }
}
