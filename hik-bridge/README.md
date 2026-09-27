# hik-bridge

A small on-site agent that reads access-control events from a Hikvision terminal on a local network
and pushes them to Eversoft CRM. Use it when the CRM server cannot reach the terminal directly, which
is the usual case for a DS-K1A340FWX on an office LAN.

It has no dependencies (Node ≥ 20) and uses the same ISAPI calls as the server-side adapter:
`POST /ISAPI/AccessControl/AcsEvent?format=json` with HTTP digest auth and pagination.

## Setup

1. In the CRM, go to **Settings → Attendance devices → Add device** and choose **On-site bridge
   pushes events**.
2. Open the device menu, choose **Generate bridge token**, and copy the `hbr_…` token. It is shown
   only once.
3. On a computer or mini-PC in the same network as the terminal:

```bash
cp .env.example .env      # fill DEVICE_URL, DEVICE_PASSWORD, BRIDGE_TOKEN, CRM_URL
node src/index.js --config .env            # runs continuously
node src/index.js --config .env --once     # single sync, e.g. from cron
```

Or with Docker:

```bash
docker build -t eversoft/hik-bridge .
docker run -d --restart unless-stopped --env-file .env -v hik-bridge-state:/app/state eversoft/hik-bridge
```

## Behaviour

- **Polling:** it polls every `POLL_SECONDS` (default 60). It reads from the last pushed event minus
  10 minutes, so buffered events are not missed; the first run looks back `INITIAL_LOOKBACK_HOURS`.
- **Batches:** events are pushed in batches of `BATCH_SIZE`. Progress is saved to `STATE_FILE` only
  after the CRM accepts a batch, and the CRM deduplicates, so a crash or retry never loses or doubles
  punches.
- **Errors:** device or network errors are logged and retried with backoff (up to 15 minutes). The
  agent never exits on them.
- **Clock:** `DEVICE_TZ_OFFSET` must match the terminal clock (`+05:00` for Tashkent).

## Tests

```bash
npm test
```

The test suite runs the agent against a mock terminal that verifies real HTTP digest responses and
paginates with `responseStatusStrg=MORE`.
