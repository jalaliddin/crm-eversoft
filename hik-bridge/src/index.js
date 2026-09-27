#!/usr/bin/env node
import { loadConfig } from './config.js'
import { syncOnce } from './bridge.js'

const config = loadConfig()
let stopping = false
process.on('SIGINT', () => { stopping = true })
process.on('SIGTERM', () => { stopping = true })

async function loop() {
  let backoff = config.pollSeconds
  while (!stopping) {
    try {
      await syncOnce(config)
      backoff = config.pollSeconds
    } catch (error) {
      // Network or device errors never crash the agent; retry with capped exponential backoff.
      console.error(`[hik-bridge] ${new Date().toISOString()} sync failed: ${error.message}`)
      backoff = Math.min(backoff * 2, 15 * 60)
    }
    if (config.once) return
    await new Promise((resolve) => setTimeout(resolve, backoff * 1000))
  }
}

loop().then(() => process.exit(0))
