import { test } from 'node:test'
import assert from 'node:assert/strict'
import { createServer } from 'node:http'
import { createHash } from 'node:crypto'
import { mkdtempSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join } from 'node:path'
import { parseChallenge } from '../src/digest.js'
import { formatLocal, toCrmEvent } from '../src/device.js'
import { syncOnce } from '../src/bridge.js'
import { readState } from '../src/state.js'

const md5 = (s) => createHash('md5').update(s).digest('hex')
const REALM = 'DS-K1A340FWX'
const NONCE = 'abc123nonce'

/** Minimal ISAPI mock that enforces HTTP digest auth exactly like the terminal. */
function mockDevice(password, pages) {
  const requests = []
  const server = createServer((req, res) => {
    let body = ''
    req.on('data', (c) => (body += c))
    req.on('end', () => {
      const auth = req.headers.authorization
      if (!auth) {
        res.writeHead(401, { 'WWW-Authenticate': `Digest qop="auth", realm="${REALM}", nonce="${NONCE}", stale="FALSE"` })
        return res.end()
      }
      const p = parseChallenge(auth)
      const ha1 = md5(`${p.username}:${REALM}:${password}`)
      const ha2 = md5(`${req.method}:${p.uri}`)
      const expected = md5(`${ha1}:${NONCE}:${p.nc}:${p.cnonce}:${p.qop}:${ha2}`)
      if (p.response !== expected || p.uri !== req.url) {
        res.writeHead(401, { 'WWW-Authenticate': `Digest qop="auth", realm="${REALM}", nonce="${NONCE}"` })
        return res.end()
      }
      const cond = JSON.parse(body).AcsEventCond
      requests.push(cond)
      const page = pages[Math.min(requests.length - 1, pages.length - 1)]
      res.writeHead(200, { 'Content-Type': 'application/json' })
      res.end(JSON.stringify({ AcsEvent: page }))
    })
  })
  return new Promise((resolve) => server.listen(0, () => resolve({ server, requests, url: `http://127.0.0.1:${server.address().port}` })))
}

const info = (serial, time, employeeNo, status = 'checkIn') => ({ major: 5, minor: 75, time, employeeNoString: employeeNo, serialNo: serial, attendanceStatus: status, name: 'Person' })

function config(deviceUrl, overrides = {}) {
  return {
    deviceUrl, deviceUser: 'admin', devicePassword: 'S3cret!', deviceTimezoneOffset: '+05:00', crmUrl: 'http://unused', token: 'hbr_x',
    pollSeconds: 60, lookbackHours: 24, pageSize: 2, batchSize: 2, stateFile: join(mkdtempSync(join(tmpdir(), 'bridge-')), 'state.json'), once: true,
    ...overrides,
  }
}

test('reads paginated events through digest auth and pushes them in batches', async () => {
  const { server, requests, url } = await mockDevice('S3cret!', [
    { responseStatusStrg: 'MORE', InfoList: [info(1, '2026-09-21T08:31:00+05:00', '1001'), { major: 5, minor: 21, time: '2026-09-21T08:32:00+05:00', serialNo: 2 }] },
    { responseStatusStrg: 'OK', InfoList: [info(3, '2026-09-21T17:40:00+05:00', '1001', 'checkOut')] },
  ])
  const pushed = []
  const crm = { push: async (events) => { pushed.push(events); return { stored: events.length, duplicates: 0, unmatched: 0 } } }
  const cfg = config(url)

  try {
    const result = await syncOnce(cfg, { crm, now: new Date('2026-09-21T15:00:00Z'), log: {} })

    assert.equal(requests.length, 2)
    assert.deepEqual(requests.map((r) => r.searchResultPosition), [0, 2])
    assert.equal(requests[0].major, 5)
    assert.equal(requests[0].startTime, '2026-09-20T20:00:00+05:00') // 24h lookback, device-local offset
    assert.equal(result.pushed, 2) // the door event without a person is skipped
    assert.deepEqual(pushed.flat().map((e) => [e.external_id, e.event_type]), [['1', 'check_in'], ['3', 'check_out']])
    assert.equal(readState(cfg.stateFile).lastEventTime, '2026-09-21T12:40:00.000Z')
  } finally {
    server.close()
  }
})

test('resumes from saved state with a safety overlap', async () => {
  const { server, requests, url } = await mockDevice('S3cret!', [{ responseStatusStrg: 'OK', InfoList: [] }])
  const cfg = config(url)
  const crm = { push: async () => ({}) }
  try {
    await syncOnce(cfg, { crm, now: new Date('2026-09-21T15:00:00Z'), log: {} })
    const { writeState } = await import('../src/state.js')
    writeState(cfg.stateFile, { lastEventTime: '2026-09-21T12:40:00.000Z' })
    await syncOnce(cfg, { crm, now: new Date('2026-09-21T15:05:00Z'), log: {} })
    assert.equal(requests.at(-1).startTime, '2026-09-21T17:30:00+05:00') // 12:40Z − 10 min, in +05:00
  } finally {
    server.close()
  }
})

test('wrong device password fails loudly and does not advance state', async () => {
  const { server, url } = await mockDevice('correct-password', [{ responseStatusStrg: 'OK', InfoList: [] }])
  const cfg = config(url)
  try {
    await assert.rejects(syncOnce(cfg, { crm: { push: async () => ({}) }, log: {} }), /credentials/)
    assert.equal(readState(cfg.stateFile).lastEventTime, undefined)
  } finally {
    server.close()
  }
})

test('CRM rejection keeps state so the batch is retried', async () => {
  const { server, url } = await mockDevice('S3cret!', [{ responseStatusStrg: 'OK', InfoList: [info(9, '2026-09-21T08:31:00+05:00', '7')] }])
  const cfg = config(url)
  try {
    await assert.rejects(syncOnce(cfg, { crm: { push: async () => { throw new Error('HTTP 503') } }, now: new Date('2026-09-21T15:00:00Z'), log: {} }), /503/)
    assert.equal(readState(cfg.stateFile).lastEventTime, undefined)
  } finally {
    server.close()
  }
})

test('event mapping and time formatting', () => {
  assert.equal(formatLocal(new Date('2026-09-21T03:30:00Z'), '+05:00'), '2026-09-21T08:30:00+05:00')
  assert.equal(toCrmEvent({ major: 5, minor: 21, time: '2026-09-21T08:00:00+05:00' }), null)
  assert.deepEqual(toCrmEvent({ time: 't', cardNo: 'C-1', attendanceStatus: 'undefined' }).card_number, 'C-1')
})
