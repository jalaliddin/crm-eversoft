import dayjs from 'dayjs'
import utc from 'dayjs/plugin/utc'
import timezone from 'dayjs/plugin/timezone'
import relativeTime from 'dayjs/plugin/relativeTime'
import isoWeek from 'dayjs/plugin/isoWeek'
import quarterOfYear from 'dayjs/plugin/quarterOfYear'
import customParseFormat from 'dayjs/plugin/customParseFormat'

dayjs.extend(utc)
dayjs.extend(timezone)
dayjs.extend(relativeTime)
dayjs.extend(isoWeek)
dayjs.extend(quarterOfYear)
dayjs.extend(customParseFormat)

export { dayjs }

/**
 * The API returns instants in UTC. They are shown in the organization's timezone,
 * never in the browser's, so HR in Tashkent and a manager travelling abroad see the same times.
 */
let tenantTz = 'Asia/Tashkent'
let dateFormat = 'DD.MM.YYYY'
let timeFormat = 'HH:mm'
let currency = 'UZS'

export function configureFormatting(options: { timezone?: string; dateFormat?: string; timeFormat?: string; currency?: string }) {
  if (options.timezone) tenantTz = options.timezone
  if (options.dateFormat) dateFormat = options.dateFormat
  if (options.timeFormat) timeFormat = options.timeFormat
  if (options.currency) currency = options.currency
}

export const tz = () => tenantTz
export const tenantNow = () => dayjs().tz(tenantTz)
export const today = () => tenantNow().format('YYYY-MM-DD')

export function fmtTime(iso?: string | null): string {
  return iso ? dayjs.utc(iso).tz(tenantTz).format(timeFormat) : '—'
}

export function fmtDateTime(iso?: string | null): string {
  return iso ? dayjs.utc(iso).tz(tenantTz).format(`${dateFormat} ${timeFormat}`) : '—'
}

/** Business dates ("2026-09-21") have no timezone. */
export function fmtDate(date?: string | null): string {
  return date ? dayjs(date.slice(0, 10)).format(dateFormat) : '—'
}

export function fromNow(iso?: string | null): string {
  return iso ? dayjs.utc(iso).fromNow() : '—'
}

export function fmtMinutes(minutes?: number | null): string {
  if (!minutes) return '0m'
  const h = Math.floor(minutes / 60)
  const m = minutes % 60
  return h ? `${h}h ${m.toString().padStart(2, '0')}m` : `${m}m`
}

export function fmtMoney(value?: number | null, code?: string): string {
  if (value === null || value === undefined) return '—'
  const c = code ?? currency
  const digits = c === 'UZS' ? 0 : 2
  return `${new Intl.NumberFormat('ru-RU', { minimumFractionDigits: digits, maximumFractionDigits: digits }).format(value)} ${c}`
}

export function fmtCompact(value: number): string {
  return new Intl.NumberFormat('en', { notation: 'compact', maximumFractionDigits: 1 }).format(value)
}

export function fmtNumber(value?: number | null): string {
  return value === null || value === undefined ? '—' : new Intl.NumberFormat('ru-RU').format(value)
}

export function fmtBytes(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(0)} KB`
  return `${(bytes / 1024 / 1024).toFixed(1)} MB`
}

/** Local wall-clock "YYYY-MM-DD HH:mm" in the tenant timezone → ISO string with offset for the API. */
export function toTenantIso(localDateTime: string): string {
  return dayjs.tz(localDateTime, 'YYYY-MM-DD HH:mm', tenantTz).format()
}

export function tenantLocal(iso?: string | null, format = 'HH:mm'): string {
  return iso ? dayjs.utc(iso).tz(tenantTz).format(format) : ''
}

export function initials(name?: string | null): string {
  return (name ?? '?')
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((p) => p[0]?.toUpperCase())
    .join('')
}

export const humanize = (value?: string | null) => (value ? value.charAt(0).toUpperCase() + value.slice(1).replace(/_/g, ' ') : '—')
