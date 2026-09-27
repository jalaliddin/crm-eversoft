import { digestFetch } from './digest.js'

/**
 * Reads AcsEvent pages from a Hikvision terminal. Uses the same ISAPI calls as the server-side adapter:
 *   POST /ISAPI/AccessControl/AcsEvent?format=json   (major 5 = access-control events, paginated via responseStatusStrg=MORE)
 */
export class HikvisionDevice {
  constructor(config, log = console) {
    this.config = config
    this.log = log
  }

  async deviceInfo() {
    const res = await digestFetch(`${this.config.deviceUrl}/ISAPI/System/deviceInfo`, { username: this.config.deviceUser, password: this.config.devicePassword })
    if (!res.ok) throw new Error(`deviceInfo failed: HTTP ${res.status}`)
    return res.text()
  }

  /** @returns {AsyncGenerator<object>} raw InfoList entries */
  async *events(from, to) {
    let position = 0
    const searchID = `bridge-${Date.now()}`

    for (;;) {
      const body = JSON.stringify({
        AcsEventCond: {
          searchID,
          searchResultPosition: position,
          maxResults: this.config.pageSize,
          major: 5,
          minor: 0,
          startTime: formatLocal(from, this.config.deviceTimezoneOffset),
          endTime: formatLocal(to, this.config.deviceTimezoneOffset),
        },
      })

      const res = await digestFetch(`${this.config.deviceUrl}/ISAPI/AccessControl/AcsEvent?format=json`, {
        username: this.config.deviceUser,
        password: this.config.devicePassword,
        method: 'POST',
        body,
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      })
      if (res.status === 401) throw new Error('Device rejected the credentials (HTTP 401).')
      if (!res.ok) throw new Error(`AcsEvent search failed: HTTP ${res.status} ${await res.text()}`)

      const json = await res.json()
      let list = json?.AcsEvent?.InfoList ?? []
      if (!Array.isArray(list)) list = [list] // some firmwares return a single object
      for (const info of list) yield info

      position += list.length
      const more = String(json?.AcsEvent?.responseStatusStrg ?? 'OK').toUpperCase() === 'MORE'
      if (!more || list.length === 0) break
    }
  }
}

/** Date → "YYYY-MM-DDTHH:mm:ss+05:00" in the device's local offset. */
export function formatLocal(date, offset) {
  const sign = offset.startsWith('-') ? -1 : 1
  const [h, m] = offset.slice(1).split(':').map(Number)
  const local = new Date(date.getTime() + sign * (h * 60 + m) * 60000)
  return local.toISOString().slice(0, 19) + offset
}

/** Keep only person/card authentication events, in the shape the CRM ingest endpoint expects. */
export function toCrmEvent(info) {
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
      attendanceStatus: info.attendanceStatus, currentVerifyMode: info.currentVerifyMode, doorNo: info.doorNo,
    },
  }
}
