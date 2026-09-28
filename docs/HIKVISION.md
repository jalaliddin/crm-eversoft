# Hikvision integration (DS-K1A340FWX)

> **Verification status.** The adapter is tested against a faked ISAPI (HTTP digest, pagination,
> errors) and end to end through the on-site bridge with a digest-verifying mock terminal. It has
> **not yet been exercised against a physical DS-K1A340FWX from this codebase.** The endpoints are
> the ones already used in production against this model in the team's `hr2.urtg.uz` project. Run
> the checklist below on the real device before relying on it.

## Architecture

```text
Controller ─▶ AttendanceDeviceManager ─▶ AttendanceSyncService ─▶ HikvisionAttendanceDevice ─▶ HikvisionClient ─▶ device
                (vendor → driver)          (lock, window,          (AttendanceDeviceInterface)   (digest, timeout,
                                            ingest, process)                                      retry, logging)
```

- `AttendanceDeviceInterface`: `testConnection()`, `getEvents($from, $to)`, `syncEmployees()`,
  `pushEmployee()`, `removeEmployee()`.
- `HikvisionEventMapper` turns `AcsEvent.InfoList[]` into vendor-neutral `DeviceEvent`s.
- `FakeAttendanceDevice` (vendor `fake`) is a simulator for development and tests.
  `settings.offline=true` simulates an unreachable device.
- Other vendors (ZKTeco, Suprema…) register with `AttendanceDeviceManager::extend('vendor', fn)`.
  Nothing else changes.

## ISAPI calls used

| Purpose | Call |
|---|---|
| Connection test / serial / firmware | `GET /ISAPI/System/deviceInfo` (XML) |
| Events | `POST /ISAPI/AccessControl/AcsEvent?format=json` with `AcsEventCond {searchID, searchResultPosition, maxResults, major: 5, minor: 0, startTime, endTime}`; paginate while `responseStatusStrg = "MORE"` |
| Add user | `POST /ISAPI/AccessControl/UserInfo/Record?format=json` |
| Update user (keeps face data) | `PUT /ISAPI/AccessControl/UserInfo/Modify?format=json`, used when Record answers `employeeNoAlreadyExist` |
| Remove user | `PUT /ISAPI/AccessControl/UserInfo/Delete?format=json` |

**Event fields used:** `time` (with offset), `employeeNoString`, `cardNo`, `serialNo` (the dedup key is
`sn:<serialNo>:<UTC time>`, because the guide gives `serialNo` a range of 1–100000 and it wraps around), `attendanceStatus` (checkIn/checkOut/break…/overtime…/undefined), `major`,
`minor`, `name`, `currentVerifyMode`. Entries without a person or card, such as door alarms, are
skipped. When a firmware omits `serialNo`, a deterministic hash of
device + identifier + card + time + type is used instead.

Check-in and check-out are derived from the **first and last punch of the business day**, not from
`attendanceStatus`. Many firmwares report `undefined`, and people forget to press the status key.

**Resilience:**

- Timeouts are configurable: `HIKVISION_TIMEOUT` and `HIKVISION_CONNECT_TIMEOUT`.
- Only network errors are retried (`HIKVISION_RETRIES`).
- HTTP 401 is reported as a credentials problem.
- Other HTTP errors include the ISAPI `subStatusCode`.
- A failed sync never throws. It records `last_sync_status=failed` and `last_error` on the device, and
  punches stored before the failure are still processed.
- Logs go to `storage/logs/hikvision-*.log` and `attendance-*.log` (JSON). Passwords are never logged.

The device password is stored with Laravel's `encrypted` cast (AES-256, `APP_KEY`), is write-only in
the API (`has_password: true`), and is excluded from audit logs.

## Connection modes

| Mode | Who connects | Can the CRM send commands? | Recovers missed events? |
|---|---|---|---|
| Direct | Server → terminal (ISAPI) | Yes | Yes (pulls with overlap) |
| Bridge | Office agent → CRM | No (agent only reads) | Yes (agent pulls, then pushes) |
| HTTP push | Terminal → CRM (listening host) | No | No: events sent during an outage are lost |
| Office browser | Office browser with the CRM open → terminal, then → CRM | No (the extension only reads) | Yes, up to 7 days, once a browser is open again |

**Direct.** The server reaches the terminal (public IP, VPN or port forward). The scheduler runs
`attendance:sync --queue` every 5 minutes. It reads from `last_event_at − 10 min` (overlap) up to now,
in 24-hour windows, and the first sync looks back `ATTENDANCE_INITIAL_LOOKBACK_DAYS`.

**Bridge (recommended when the terminal is on an office LAN).**

1. In the CRM, go to **Settings → Attendance devices → Add device**, choose **On-site bridge pushes
   events**, then **Generate bridge token**. The token is shown once and only its SHA-256 is stored.
2. On any machine in the office network, run `hik-bridge` (see `hik-bridge/README.md`):

   ```bash
   DEVICE_URL=http://192.168.1.64 DEVICE_PASSWORD=… BRIDGE_TOKEN=hbr_… CRM_URL=https://crm.eversoft.uz npm start
   ```

3. The agent polls the terminal, pushes batches to `POST /api/v1/device-bridge/events`
   (`Authorization: Bearer hbr_…`), and stores its progress in a state file. Retries are safe because
   the server deduplicates.

**HTTP push (terminal posts events itself).** No inbound access to the terminal is needed.

1. In the CRM, go to **Settings → Attendance devices → Add device** and choose **Terminal pushes**.
2. Choose **Generate push URL** in the device menu. The dialog shows the URL
   (`/api/v1/hikvision/push/hpt_…`, secret, shown once, only its SHA-256 is stored) and the terminal
   setting to apply, with the documented fields of `PUT /ISAPI/Event/notification/httpHosts/1`:
   `id`, `url`, `protocolType` (HTTP/HTTPS), `parameterFormatType` (JSON/XML), `addressingFormatType`
   (`hostname`/`ipaddress`), `hostName` or `ipAddress`, `portNo`, `httpAuthenticationMethod` (`none`).
3. Apply it from a computer in the terminal's network (the dialog gives a `curl --digest … -X PUT`
   command) or in the terminal's web page, then scan a face.
4. **Settings → Attendance devices → Push log** shows every request received: result (`accepted`,
   `heartbeat`, `ignored`, `unparsed`, `rejected`, `failed`), the parsed employee and time, and the text
   of the request. Pictures are never stored. Entries are kept 14 days.

The receiver accepts multipart/form-data with a JSON or XML `EventNotificationAlert` part (the format
the ISAPI guide documents), as well as a raw JSON or XML body. Heartbeats mark the device as alive.
Events without a person or card (door alarms and the like) are logged as `ignored`. Prefer
`protocolType` HTTPS: over plain HTTP the secret URL and employee data travel unencrypted. Nginx does
not write push URLs to its access log, but check any reverse proxy in front of it.

**Office browser (no server software on site).** An ordinary web page cannot read the terminal. The
ISAPI guide says nothing about CORS, so the browser discards the terminal's answers, and digest
login cannot work from a page for the same reason. An HTTPS page calling a plain-HTTP LAN address is
also blocked as mixed content. The **Eversoft Terminal Bridge** extension (`browser-bridge/`, Chrome
or Edge) fills that gap:

1. In the CRM, go to **Settings → Attendance devices → Add device**, choose **Office browser** and enter
   the terminal's LAN address and port. The server never connects to this address.
2. On a computer in the office, install the extension. The devices page links to
   `/downloads/eversoft-terminal-bridge.zip`. Unzip it, open `chrome://extensions`, turn on Developer
   mode, and choose **Load unpacked**. Then reload the CRM.
3. On the device card, choose **Set up** and enter the terminal user name and password in the
   extension. Chrome asks to allow access to that address. The password stays in that browser profile
   and is never sent to the CRM.
4. While the CRM is open in any tab of that browser, one tab reads the terminal every minute
   (`AcsEvent`, the same call as the pull adapter) and posts new punches to
   `POST /attendance-devices/{id}/browser-events` with the user's session. Other tabs show its status.
   If that tab closes, another open tab takes over. **Sync now** and **Test connection** go through the
   extension too.

The extension answers only CRM pages (`crm.eversoft.uz` and its subdomains, and localhost for
development). It makes only two calls, both read-only: `GET /ISAPI/System/deviceInfo` and the
`AcsEvent` search. It calls only terminals that someone added in its settings. After a rejected
password, it stops automatic reads so the terminal does not lock the account. **Sync now** retries, and
saving a new password resumes them. A computer that cannot reach the terminal, such as a laptop at
home, shows that on its own screen but does not mark the device as failed for everyone.

Punches are read only while the CRM is open in an office browser. After a gap, such as a weekend, the
next read catches up from the latest stored punch, up to 7 days back, and the server removes
duplicates. For readings that never stop, use direct mode, the bridge agent or HTTP push. The
extension needs Chrome or Edge 116+. Custom tenant domains need their origin added to `manifest.json`.

Access-control events are kept whatever their `subEventType`, as in the pull adapter. Once real
events have been captured, a filter that keeps only successful authentications can be added; the
major/minor codes of every push are stored in the raw event payload for that purpose.

## Mapping employees

Every employee needs a **terminal ID**: the terminal's `employeeNo`, under
**Employee → Terminal IDs**. It is *not* assumed to equal the database id or the employee number. An
ID can apply to all devices (`device_id = NULL`) or to one device. A card number can be stored as a
fallback match. **Push employees to device** creates or updates users on the terminal. Faces and
fingerprints are enrolled on the terminal itself.

## On-device verification checklist

Run these against the real DS-K1A340FWX and record the firmware version.

1. **Network.** From the server (or the bridge host), `curl --digest -u admin:PASS http://IP/ISAPI/System/deviceInfo`
   returns XML with `<model>DS-K1A340FWX</model>`. If the answer is 401 with the right password, check
   that digest auth is enabled (web UI → Configuration → Security → Authentication).
2. **Clock.** The terminal clock and timezone are correct (Configuration → System → Time settings,
   NTP recommended). Wrong clocks produce wrong lateness.
3. **Test connection** in the CRM. It should report the model, serial and firmware.
4. **Map one employee.** Add their terminal ID in the CRM, then click **Push employees to device** and
   confirm the user appears on the terminal (Person management).
5. **Enroll a face** for that person on the terminal and make 3–4 punches.
6. **Sync now.** The punches appear under **Punch log** as `processed`, and the attendance record
   shows the first punch as check-in and the last as check-out.
7. **Sync again.** It reports 0 new and N duplicates (deduplication works with this firmware's
   `serialNo`).
8. **Unknown person.** A punch by someone without a mapping appears under **Unmatched punches**. Map
   it, then **Reprocess all**; the record is created.
9. **Pagination.** With more than 30 events in the window, confirm that all are imported. If the
   device rejects `maxResults`, lower `HIKVISION_PAGE_SIZE`.
10. **Offline.** Unplug the terminal, then **Sync now**. The device shows *Last sync failed* with a
    readable message and nothing else breaks.
11. **HTTP push.** With the device in push mode and the listening host applied, scan a face. The
    **Push log** shows an `accepted` entry within seconds and the punch appears in the attendance
    record. Open the entry and check that the body matches the documented `EventNotificationAlert`; if
    it shows `unparsed`, the body there is exactly what the firmware sent.
12. **Office browser.** With the device in office-browser mode and the extension set up on an office
    computer, keep the CRM open and scan a face. Within a minute the card shows *Read by this
    browser* and the punch appears. Test once with a wrong password: the card shows the error, and
    the terminal's log shows no repeated login attempts afterwards.
13. **Modify path.** Change an employee's name, then **Push employees to device**. Confirm the terminal
    keeps the enrolled face; this exercises `UserInfo/Modify`.

## Troubleshooting

| Symptom | Check |
|---|---|
| `Authentication failed` | Username and password; digest enabled; the account is not locked after failed attempts |
| `Device unreachable` | IP and port from the server/bridge host; firewall; use bridge mode for LAN-only terminals |
| Punches stay `unmatched` | Terminal ID mapping matches the terminal's `employeeNo` exactly |
| Times off by hours | Terminal timezone; set `settings.timezone` on the device if its clock is not in the organization timezone |
| Everyone shows `incomplete` | People punch only once per day; the day was closed before check-outs were synced (re-sync, then *Recalculate period*) |
