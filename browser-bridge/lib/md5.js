/** MD5 (RFC 1321) of a UTF-8 string as lowercase hex. Web Crypto has no MD5, and Hikvision digest auth needs it. */
export function md5(text) {
  const bytes = new TextEncoder().encode(text)
  const words = new Uint32Array((((bytes.length + 8) >>> 6) + 1) * 16)
  for (let i = 0; i < bytes.length; i++) words[i >> 2] |= bytes[i] << ((i % 4) * 8)
  words[bytes.length >> 2] |= 0x80 << ((bytes.length % 4) * 8)
  const bits = bytes.length * 8
  words[words.length - 2] = bits >>> 0
  words[words.length - 1] = Math.floor(bits / 0x100000000)

  let a = 0x67452301
  let b = 0xefcdab89
  let c = 0x98badcfe
  let d = 0x10325476

  for (let block = 0; block < words.length; block += 16) {
    const [aa, bb, cc, dd] = [a, b, c, d]
    for (let j = 0; j < 64; j++) {
      let f
      let g
      if (j < 16) { f = (b & c) | (~b & d); g = j }
      else if (j < 32) { f = (d & b) | (~d & c); g = (5 * j + 1) % 16 }
      else if (j < 48) { f = b ^ c ^ d; g = (3 * j + 5) % 16 }
      else { f = c ^ (b | ~d); g = (7 * j) % 16 }
      const next = d
      d = c
      c = b
      b = (b + rotl((a + f + K[j] + words[block + g]) | 0, S[j])) | 0
      a = next
    }
    a = (a + aa) | 0
    b = (b + bb) | 0
    c = (c + cc) | 0
    d = (d + dd) | 0
  }

  return [a, b, c, d].map(hexLittleEndian).join('')
}

const S = [
  7, 12, 17, 22, 7, 12, 17, 22, 7, 12, 17, 22, 7, 12, 17, 22,
  5, 9, 14, 20, 5, 9, 14, 20, 5, 9, 14, 20, 5, 9, 14, 20,
  4, 11, 16, 23, 4, 11, 16, 23, 4, 11, 16, 23, 4, 11, 16, 23,
  6, 10, 15, 21, 6, 10, 15, 21, 6, 10, 15, 21, 6, 10, 15, 21,
]
const K = Array.from({ length: 64 }, (_, i) => Math.floor(Math.abs(Math.sin(i + 1)) * 2 ** 32) | 0)

const rotl = (x, n) => (x << n) | (x >>> (32 - n))
const hexLittleEndian = (n) => [0, 8, 16, 24].map((shift) => ((n >>> shift) & 0xff).toString(16).padStart(2, '0')).join('')
