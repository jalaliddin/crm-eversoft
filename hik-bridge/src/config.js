/** Configuration from environment variables (or a .env-style file passed with --config). */
import { readFileSync } from 'node:fs'

export function loadConfig(argv = process.argv.slice(2), env = process.env) {
  const configIndex = argv.indexOf('--config')
  const fileEnv = {}
  if (configIndex !== -1 && argv[configIndex + 1]) {
    for (const line of readFileSync(argv[configIndex + 1], 'utf8').split('\n')) {
      const m = /^\s*([A-Z0-9_]+)\s*=\s*(.*)\s*$/.exec(line)
      if (m && !line.trim().startsWith('#')) fileEnv[m[1]] = m[2].replace(/^["']|["']$/g, '')
    }
  }
  const get = (key, fallback) => env[key] ?? fileEnv[key] ?? fallback

  const config = {
    deviceUrl: get('DEVICE_URL'), // e.g. http://192.168.1.64
    deviceUser: get('DEVICE_USERNAME', 'admin'),
    devicePassword: get('DEVICE_PASSWORD'),
    deviceTimezoneOffset: get('DEVICE_TZ_OFFSET', '+05:00'),
    crmUrl: (get('CRM_URL', 'https://crm.eversoft.uz') ?? '').replace(/\/$/, ''),
    token: get('BRIDGE_TOKEN'),
    pollSeconds: Number(get('POLL_SECONDS', '60')),
    lookbackHours: Number(get('INITIAL_LOOKBACK_HOURS', '24')),
    pageSize: Number(get('PAGE_SIZE', '30')),
    batchSize: Number(get('BATCH_SIZE', '500')),
    stateFile: get('STATE_FILE', './state/bridge-state.json'),
    once: argv.includes('--once'),
  }

  const missing = [['DEVICE_URL', config.deviceUrl], ['DEVICE_PASSWORD', config.devicePassword], ['BRIDGE_TOKEN', config.token]]
    .filter(([, v]) => !v)
    .map(([k]) => k)
  if (missing.length) throw new Error(`Missing configuration: ${missing.join(', ')}`)

  return config
}
