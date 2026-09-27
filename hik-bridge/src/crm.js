/** Pushes events to Eversoft CRM with the device bridge token. The server deduplicates, so retries are safe. */
export class CrmClient {
  constructor(config) {
    this.config = config
  }

  async push(events) {
    const res = await fetch(`${this.config.crmUrl}/api/v1/device-bridge/events`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', Authorization: `Bearer ${this.config.token}` },
      body: JSON.stringify({ events }),
      signal: AbortSignal.timeout(60000),
    })
    const json = await res.json().catch(() => ({}))
    if (!res.ok) throw new Error(`CRM rejected the batch: HTTP ${res.status} ${json.message ?? ''}`)
    return json.data
  }
}
