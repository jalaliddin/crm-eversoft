import { test } from 'node:test'
import assert from 'node:assert/strict'
import { createHash } from 'node:crypto'
import { md5 } from '../lib/md5.js'
import { digestHeader, parseChallenge } from '../lib/digest.js'
import { MAX_EVENTS_PER_SEARCH, TerminalError, deviceInfo, normalizeOrigin, searchEvents } from '../lib/isapi.js'

const nodeMd5 = (s) => createHash('md5').update(s).digest('hex')

test('md5 matches Node crypto, including block boundaries and UTF-8', () => {
  for (const s of ['', 'abc', 'x'.repeat(55), 'x'.repeat(56), 'x'.repeat(64), 'x'.repeat(119), 'admin:realm:пароль✓']) {
    assert.equal(md5(s), nodeMd5(s))
  }
})

test('digest header follows RFC 7616 (qop=auth)', () => {
  const challenge = parseChallenge('Digest qop="auth", realm="DS-K1A340", nonce="abc123", stale="FALSE", Basic realm="x"')
  assert.deepEqual(challenge, { qop: 'auth', realm: 'DS-K1A340', nonce: 'abc123', stale: 'FALSE' })

  const header = digestHeader({ username: 'admin', password: 'secret', method: 'POST', uri: '/ISAPI/x?format=json', challenge, cnonce: 'c0ffee' })
  const ha1 = nodeMd5('admin:DS-K1A340:secret')
  const ha2 = nodeMd5('POST:/ISAPI/x?format=json')
  assert.match(header, new RegExp(`response="${nodeMd5(`${ha1}:abc123:00000001:c0ffee:auth:${ha2}`)}"`))
  assert.match(header, /qop=auth, nc=00000001, cnonce="c0ffee"/)
})

test('only bare http(s) origins are accepted as terminal addresses', () => {
  assert.equal(normalizeOrigin('http://192.168.1.64'), 'http://192.168.1.64')
  assert.equal(normalizeOrigin(' http://192.168.1.64:80/ '), 'http://192.168.1.64')
  assert.equal(normalizeOrigin('https://10.0.0.5:8443'), 'https://10.0.0.5:8443')
  for (const bad of ['192.168.1.64', 'ftp://x', 'http://a:b@1.2.3.4', 'http://1.2.3.4/ISAPI', 'http://1.2.3.4/?q', 'javascript:alert(1)']) {
    assert.equal(normalizeOrigin(bad), null, bad)
  }
})

/** A terminal that requires digest auth and serves `total` events in pages. */
function fakeTerminal({ total = 0, password = 'secret', status = 200 } = {}) {
  const calls = []
  const fetchImpl = async (url, init) => {
    calls.push({ url, init })
    const auth = init.headers.Authorization
    if (!auth) return new Response('', { status: 401, headers: { 'WWW-Authenticate': 'Digest qop="auth", realm="R", nonce="n1"' } })
    const uri = /uri="([^"]+)"/.exec(auth)[1]
    const cnonce = /cnonce="([^"]+)"/.exec(auth)[1]
    const expected = nodeMd5(`${nodeMd5(`admin:R:${password}`)}:n1:00000001:${cnonce}:auth:${nodeMd5(`${init.method}:${uri}`)}`)
    if (!auth.includes(`response="${expected}"`)) return new Response('', { status: 401, headers: { 'WWW-Authenticate': 'Digest realm="R", nonce="n2"' } })
    if (status !== 200) return new Response('{"statusCode":4,"subStatusCode":"badParameters"}', { status })

    if (url.endsWith('/ISAPI/System/deviceInfo')) {
      return new Response('<DeviceInfo><model>DS-K1A340FWX</model><serialNumber>ABC</serialNumber></DeviceInfo>')
    }
    const cond = JSON.parse(init.body).AcsEventCond
    const list = Array.from({ length: Math.max(0, Math.min(cond.maxResults, total - cond.searchResultPosition)) }, (_, i) => ({
      serialNo: cond.searchResultPosition + i + 1, employeeNoString: '7', time: '2026-09-21T08:30:00+05:00', major: 5, minor: 75,
    }))
    return Response.json({ AcsEvent: { InfoList: list, responseStatusStrg: cond.searchResultPosition + list.length < total ? 'MORE' : 'OK' } })
  }
  return { fetchImpl, calls }
}

const terminal = { origin: 'http://192.168.1.64', username: 'admin', password: 'secret' }
const window = { startTime: '2026-09-21T00:00:00+05:00', endTime: '2026-09-21T23:59:59+05:00' }

test('event search authenticates with digest and follows pagination', async () => {
  const { fetchImpl, calls } = fakeTerminal({ total: 65 })
  const { events, truncated } = await searchEvents(terminal, window, { fetchImpl })

  assert.equal(events.length, 65)
  assert.equal(truncated, false)
  assert.deepEqual(events.map((e) => e.serialNo), Array.from({ length: 65 }, (_, i) => i + 1))
  const searches = calls.filter((c) => c.init.headers.Authorization).map((c) => JSON.parse(c.init.body).AcsEventCond)
  assert.deepEqual(searches.map((c) => c.searchResultPosition), [0, 30, 60])
  assert.ok(searches.every((c) => c.major === 5 && c.startTime === window.startTime))
  assert.ok(calls.every((c) => c.init.credentials === 'omit'))
})

test('very large windows are cut off and reported as truncated', async () => {
  const { fetchImpl } = fakeTerminal({ total: MAX_EVENTS_PER_SEARCH + 100 })
  const { events, truncated } = await searchEvents(terminal, window, { fetchImpl })
  assert.equal(truncated, true)
  assert.ok(events.length >= MAX_EVENTS_PER_SEARCH && events.length < MAX_EVENTS_PER_SEARCH + 30)
})

test('errors are classified so the CRM page can tell "not on the office network" from "wrong password"', async () => {
  const wrong = fakeTerminal({ password: 'other' })
  await assert.rejects(searchEvents(terminal, window, wrong), (e) => e instanceof TerminalError && e.code === 'auth')

  const broken = fakeTerminal({ status: 400 })
  await assert.rejects(deviceInfo(terminal, broken), (e) => e.code === 'http' && /400 \(badParameters\)/.test(e.message))

  const offline = { fetchImpl: async () => { throw new TypeError('Failed to fetch') } }
  await assert.rejects(deviceInfo(terminal, offline), (e) => e.code === 'unreachable')

  await assert.rejects(searchEvents(terminal, { startTime: 'yesterday', endTime: 'now' }, offline), (e) => e.code === 'invalid')
})

test('device info is read from the XML answer', async () => {
  assert.deepEqual(await deviceInfo(terminal, fakeTerminal()), { model: 'DS-K1A340FWX', serialNumber: 'ABC', firmwareVersion: null, deviceName: null })
})
