import { deviceInfo, normalizeOrigin } from './lib/isapi.js'
import { allTerminals, hasPermission, removeTerminal, saveTerminal } from './lib/terminals.js'

const $ = (id) => document.getElementById(id)
const form = $('form')

function setStatus(text, kind = '') {
  $('form-status').textContent = text
  $('form-status').className = `status ${kind}`
}

async function edit(origin) {
  const saved = (await allTerminals())[origin]
  $('origin').value = origin
  $('username').value = saved?.username ?? 'admin'
  $('password').value = ''
  $('password').required = !saved?.password
  $('password-hint').textContent = saved?.password ? 'saved; leave empty to keep it' : ''
  $('form-title').textContent = saved ? 'Edit terminal' : 'Add a terminal'
  ;(saved ? $('password') : $('username')).focus()
}

form.addEventListener('submit', (event) => {
  event.preventDefault()
  const origin = normalizeOrigin($('origin').value)
  if (!origin) {
    setStatus('Enter the address only, like http://192.168.1.64 or http://192.168.1.64:8080.', 'err')
    return
  }
  // Must run straight from the click: Chrome only shows the permission prompt during a user gesture.
  chrome.permissions.request({ origins: [`${origin}/*`] }).then(async (granted) => {
    if (!granted) {
      setStatus('Access to the terminal was not allowed, so the CRM cannot read it from this browser.', 'err')
      return
    }
    const saved = (await allTerminals())[origin]
    const password = $('password').value || saved?.password
    if (!password) {
      setStatus('Enter the terminal password.', 'err')
      return
    }
    $('save').disabled = true
    setStatus('Connecting to the terminal…')
    try {
      const info = await deviceInfo({ origin, username: $('username').value.trim(), password })
      await saveTerminal(origin, { username: $('username').value.trim(), password, lastOkAt: new Date().toISOString(), lastError: null })
      setStatus(`Connected${info.model ? ` to ${info.model}` : ''}${info.serialNumber ? ` (serial ${info.serialNumber})` : ''}. The CRM can now read it from this browser.`, 'ok')
      $('password').value = ''
      await render()
      await edit(origin)
    } catch (error) {
      setStatus(`${error.message} Nothing was saved.`, 'err')
    } finally {
      $('save').disabled = false
    }
  })
})

// Renders can overlap (storage change events): build the rows first, then swap them in at once.
async function render() {
  const terminals = await allTerminals()
  const rows = []
  const origins = Object.keys(terminals).sort()
  if (!origins.length) {
    const li = document.createElement('li')
    li.className = 'empty'
    li.textContent = 'None yet. Add the terminal address shown on the device in the CRM.'
    rows.push(li)
  }
  for (const origin of origins) {
    const t = terminals[origin]
    const permitted = await hasPermission(origin)
    const li = document.createElement('li')
    const info = document.createElement('div')
    const title = document.createElement('div')
    const code = document.createElement('code')
    code.textContent = origin
    title.append(code, ` · ${t.username ?? 'admin'}`)
    const state = document.createElement('div')
    state.className = 'status'
    if (!permitted) {
      state.classList.add('err')
      state.textContent = 'Access not allowed. Choose Edit, then Save and test.'
    } else if (t.lastError) {
      state.classList.add('err')
      state.textContent = `${new Date(t.lastError.at).toLocaleString()}: ${t.lastError.message}`
    } else {
      state.classList.add('ok')
      state.textContent = t.lastOkAt ? `Last read ${new Date(t.lastOkAt).toLocaleString()}` : 'Saved'
    }
    info.append(title, state)

    const buttons = document.createElement('div')
    buttons.className = 'actions'
    buttons.style.marginTop = '0'
    const editButton = document.createElement('button')
    editButton.type = 'button'
    editButton.textContent = 'Edit'
    editButton.addEventListener('click', () => edit(origin))
    const removeButton = document.createElement('button')
    removeButton.type = 'button'
    removeButton.textContent = 'Remove'
    removeButton.addEventListener('click', async () => {
      if (!confirm(`Remove ${origin}? The CRM will stop reading it from this browser.`)) return
      await removeTerminal(origin)
      await render()
    })
    buttons.append(editButton, removeButton)

    li.append(info, buttons)
    rows.push(li)
  }
  $('list').replaceChildren(...rows)
}

const requested = normalizeOrigin(new URLSearchParams(location.search).get('origin') ?? '')
await render()
if (requested) await edit(requested)
chrome.storage.onChanged.addListener(() => void render())
