import { CrmClient } from './crm.js'
import { HikvisionDevice, toCrmEvent } from './device.js'
import { readState, writeState } from './state.js'

const OVERLAP_MS = 10 * 60 * 1000 // re-read 10 minutes to catch buffered events; the server dedupes.

/** One synchronization cycle: read new device events and push them in batches. */
export async function syncOnce(config, { device = new HikvisionDevice(config), crm = new CrmClient(config), now = new Date(), log = console } = {}) {
  const state = readState(config.stateFile)
  const from = state.lastEventTime
    ? new Date(new Date(state.lastEventTime).getTime() - OVERLAP_MS)
    : new Date(now.getTime() - config.lookbackHours * 3600 * 1000)

  let batch = []
  let pushed = 0
  let latest = state.lastEventTime ? new Date(state.lastEventTime) : null
  const totals = { stored: 0, duplicates: 0, unmatched: 0 }

  const flush = async () => {
    if (!batch.length) return
    const result = await crm.push(batch)
    pushed += batch.length
    totals.stored += result?.stored ?? 0
    totals.duplicates += result?.duplicates ?? 0
    totals.unmatched += result?.unmatched ?? 0
    // Persist progress only after the server accepted the batch.
    writeState(config.stateFile, { ...readState(config.stateFile), lastEventTime: latest?.toISOString(), lastSyncAt: new Date().toISOString() })
    batch = []
  }

  for await (const info of device.events(from, now)) {
    const event = toCrmEvent(info)
    if (!event) continue
    const time = new Date(event.time)
    if (!latest || time > latest) latest = time
    batch.push(event)
    if (batch.length >= config.batchSize) await flush()
  }
  await flush()

  writeState(config.stateFile, { ...readState(config.stateFile), lastEventTime: latest?.toISOString() ?? state.lastEventTime, lastSyncAt: new Date().toISOString() })
  log.info?.(`[hik-bridge] ${now.toISOString()} window ${from.toISOString()} → pushed ${pushed}, new ${totals.stored}, duplicates ${totals.duplicates}, unmatched ${totals.unmatched}`)

  return { pushed, ...totals }
}
