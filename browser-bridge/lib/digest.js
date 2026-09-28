import { md5 } from './md5.js'

/** Parse a `WWW-Authenticate: Digest ...` header. A Basic challenge sent alongside it is ignored. */
export function parseChallenge(header) {
  const params = {}
  const digest = header.replace(/^.*?Digest\s+/is, '').split(/,\s*Basic\s/i)[0]
  for (const match of digest.matchAll(/(\w+)=(?:"([^"]*)"|([^,\s]*))/g)) {
    params[match[1].toLowerCase()] = match[2] ?? match[3]
  }
  return params
}

/** Build an RFC 7616 (MD5, qop=auth) Authorization header, as Hikvision ISAPI expects. */
export function digestHeader({ username, password, method, uri, challenge, nc = 1, cnonce = randomHex(8) }) {
  const realm = challenge.realm ?? ''
  const nonce = challenge.nonce ?? ''
  const qop = (challenge.qop ?? '').split(',').map((q) => q.trim()).includes('auth') ? 'auth' : undefined
  const ncValue = nc.toString(16).padStart(8, '0')
  const ha1 = md5(`${username}:${realm}:${password}`)
  const ha2 = md5(`${method}:${uri}`)
  const response = qop ? md5(`${ha1}:${nonce}:${ncValue}:${cnonce}:${qop}:${ha2}`) : md5(`${ha1}:${nonce}:${ha2}`)

  const parts = [`username="${username}"`, `realm="${realm}"`, `nonce="${nonce}"`, `uri="${uri}"`, `response="${response}"`, 'algorithm=MD5']
  if (challenge.opaque) parts.push(`opaque="${challenge.opaque}"`)
  if (qop) parts.push(`qop=${qop}`, `nc=${ncValue}`, `cnonce="${cnonce}"`)

  return `Digest ${parts.join(', ')}`
}

/** fetch() with digest authentication and a timeout. Never sends cookies or triggers the browser's login dialog. */
export async function digestFetch(url, { username, password, method = 'GET', body, headers = {}, timeoutMs = 15000, fetchImpl = fetch }) {
  const target = new URL(url)
  const uri = target.pathname + target.search
  const init = (extra = {}) => ({
    method, body, credentials: 'omit', cache: 'no-store', headers: { ...headers, ...extra }, signal: AbortSignal.timeout(timeoutMs),
  })

  let response = await fetchImpl(target.href, init())
  if (response.status !== 401) return response

  const challenge = response.headers.get('www-authenticate') ?? ''
  if (!/digest/i.test(challenge)) return response
  await response.arrayBuffer()

  response = await fetchImpl(target.href, init({ Authorization: digestHeader({ username, password, method, uri, challenge: parseChallenge(challenge) }) }))
  return response
}

function randomHex(bytes) {
  return Array.from(crypto.getRandomValues(new Uint8Array(bytes)), (b) => b.toString(16).padStart(2, '0')).join('')
}
