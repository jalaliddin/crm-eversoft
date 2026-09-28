# Eversoft Terminal Bridge (browser extension)

This extension lets Eversoft CRM, open in an office browser, read punches from a Hikvision terminal on the local
network. It needs no server software in the office. The CRM page runs the sync loop and sends events to the CRM with
the signed-in user's session. The extension does only two things:

- it keeps the terminal user name and password in this browser profile (`chrome.storage.local`, never sent to the CRM);
- it makes two read-only ISAPI calls with digest authentication: `GET /ISAPI/System/deviceInfo` and
  `POST /ISAPI/AccessControl/AcsEvent?format=json`.

A normal web page cannot make these calls. The terminal sends no CORS headers, so the browser discards its answers,
and an HTTPS page cannot call a plain-HTTP LAN address. The extension's service worker can, for the addresses the user
allows on its settings page.

## Install (Chrome or Edge 116+)

1. Download `eversoft-terminal-bridge.zip` from the CRM (**Settings → Attendance devices**, on a device in
   *Office browser* mode). Unzip it into a folder that stays on the computer.
2. Open `chrome://extensions` (or `edge://extensions`), turn on **Developer mode**, click **Load unpacked** and select the
   folder.
3. Reload the CRM. On the device card, click **Set up**, enter the terminal password and click **Save and test**. When
   the browser asks, allow access to the terminal address.

Keep the CRM open in that browser (any page, any tab). The first tab reads the terminal every minute, and the other
tabs show its status. For a managed rollout, publish the extension privately in the Chrome Web Store or deploy it by
policy, so it does not need Developer mode.

## Security

- **Where it runs.** Content script only on `https://crm.eversoft.uz`, its subdomains, and `localhost` / `127.0.0.1`
  for development (`manifest.json`). The service worker ignores messages from anywhere else.
- **Which terminals.** The extension calls only terminals added on its settings page, and only after the user grants
  host access to that address (optional host permissions).
- **What it can do.** It can only read events and device information. It cannot open doors, change users or change
  settings.
- **Failed logins.** After the terminal rejects the password, automatic reads pause so the terminal does not lock the
  account. **Sync now** or saving a new password resumes them.

## Development

```bash
npm test        # digest auth, MD5, pagination, error handling (node --test)
npm run pack    # builds ../frontend/public/downloads/eversoft-terminal-bridge.zip for the Vite dev server
```

Production web images build the zip themselves (`docker/nginx/Dockerfile`).
