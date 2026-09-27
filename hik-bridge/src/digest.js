import { createHash, randomBytes } from 'node:crypto'

const md5 = (s) => createHash('md5').update(s).digest('hex')

/** Parse a `WWW-Authenticate: Digest ...` header into its parameters. */
export function parseChallenge(header) {
  const params = {}
  const body = header.replace(/^Digest\s+/i, '')
  for (const match of body.matchAll(/(\w+)=(?:"([^"]*)"|([^,\s]*))/g)) {
    params[match[1].toLowerCase()] = match[2] ?? match[3]
  }
  return params
}

/** Build an RFC 7616 (MD5, qop=auth) Authorization header, as used by Hikvision ISAPI. */
export function digestHeader({ username, password, method, uri, challenge, nc = 1 }) {
  const realm = challenge.realm ?? ''
  const nonce = challenge.nonce ?? ''
  const qop = (challenge.qop ?? '').split(',').map((q) => q.trim()).includes('auth') ? 'auth' : undefined
  const cnonce = randomBytes(8).toString('hex')
  const ncValue = nc.toString(16).padStart(8, '0')
  const ha1 = md5(`${username}:${realm}:${password}`)
  const ha2 = md5(`${method}:${uri}`)
  const response = qop ? md5(`${ha1}:${nonce}:${ncValue}:${cnonce}:${qop}:${ha2}`) : md5(`${ha1}:${nonce}:${ha2}`)

  const parts = [
    `username="${username}"`, `realm="${realm}"`, `nonce="${nonce}"`, `uri="${uri}"`, `response="${response}"`, 'algorithm=MD5',
  ]
  if (challenge.opaque) parts.push(`opaque="${challenge.opaque}"`)
  if (qop) parts.push(`qop=${qop}`, `nc=${ncValue}`, `cnonce="${cnonce}"`)

  return `Digest ${parts.join(', ')}`
}

/** fetch() with transparent digest authentication and a timeout. */
export async function digestFetch(url, { username, password, method = 'GET', body, headers = {}, timeoutMs = 15000 }) {
  const target = new URL(url)
  const uri = target.pathname + target.search
  const init = () => ({ method, body, headers: { ...headers }, signal: AbortSignal.timeout(timeoutMs) })

  let response = await fetch(target, init())
  if (response.status !== 401) return response

  const challengeHeader = response.headers.get('www-authenticate') ?? ''
  if (!/^digest/i.test(challengeHeader)) return response
  await response.arrayBuffer()

  const request = init()
  request.headers.Authorization = digestHeader({ username, password, method, uri, challenge: parseChallenge(challengeHeader) })
  response = await fetch(target, request)
  return response
}
