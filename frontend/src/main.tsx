import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import '@mantine/core/styles.layer.css'
import '@mantine/dates/styles.layer.css'
import '@mantine/notifications/styles.layer.css'
import '@mantine/spotlight/styles.layer.css'
import '@mantine/charts/styles.layer.css'
import 'dayjs/locale/ru'
import 'dayjs/locale/uz-latn'
import '@/lib/i18n'
import '@/app/global.css'
import { App } from '@/app/App'

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <App />
  </StrictMode>,
)
