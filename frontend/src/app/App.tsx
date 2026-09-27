import { MantineProvider, localStorageColorSchemeManager } from '@mantine/core'
import { ModalsProvider } from '@mantine/modals'
import { Notifications } from '@mantine/notifications'
import { DatesProvider } from '@mantine/dates'
import { QueryClientProvider } from '@tanstack/react-query'
import { RouterProvider } from 'react-router'
import { useTranslation } from 'react-i18next'
import { AuthProvider } from '@/features/auth/AuthContext'
import { queryClient } from '@/lib/queryClient'
import { router } from '@/routes/router'
import { theme } from './theme'

const colorSchemeManager = localStorageColorSchemeManager({ key: 'eversoft.color-scheme' })

export function App() {
  const { i18n } = useTranslation()

  return (
    <MantineProvider theme={theme} defaultColorScheme="auto" colorSchemeManager={colorSchemeManager}>
      <DatesProvider settings={{ firstDayOfWeek: 1, locale: i18n.language, consistentWeeks: true }}>
        <QueryClientProvider client={queryClient}>
          <AuthProvider>
            <ModalsProvider>
              <Notifications position="top-right" limit={4} />
              <RouterProvider router={router} />
            </ModalsProvider>
          </AuthProvider>
        </QueryClientProvider>
      </DatesProvider>
    </MantineProvider>
  )
}
