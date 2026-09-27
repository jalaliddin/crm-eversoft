import { useCallback, useEffect, useMemo, useState, type ReactNode } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import { api, tokenStore } from '@/lib/api'
import { configureFormatting } from '@/lib/format'
import i18n from '@/lib/i18n'
import type { Me } from '@/types/models'
import { AuthContext } from './authState'

interface LoginResult extends Me {
  token: string
  expires_at: string
}


function applyPreferences(me: Me) {
  const org = me.organization
  if (org) {
    configureFormatting({
      timezone: org.timezone,
      currency: org.currency,
      dateFormat: org.settings.date_format,
      timeFormat: org.settings.time_format,
    })
  }
  const locale = me.user.locale ?? org?.locale
  if (locale && i18n.language !== locale) void i18n.changeLanguage(locale)
}

export function AuthProvider({ children }: { children: ReactNode }) {
  const queryClient = useQueryClient()
  const [me, setMe] = useState<Me | null>(null)
  const [loading, setLoading] = useState<boolean>(() => tokenStore.get() !== null)

  const refresh = useCallback(async () => {
    if (!tokenStore.get()) {
      setMe(null)
      setLoading(false)
      return
    }
    try {
      const data = await api.get<Me>('/auth/me')
      applyPreferences(data)
      setMe(data)
    } catch {
      tokenStore.clear()
      setMe(null)
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    // Without a stored token the initial state (signed out, not loading) is already correct.
    if (tokenStore.get()) void api.get<Me>('/auth/me').then(
      (data) => {
        applyPreferences(data)
        setMe(data)
        setLoading(false)
      },
      () => {
        tokenStore.clear()
        setMe(null)
        setLoading(false)
      },
    )
    const onExpired = () => {
      setMe(null)
      queryClient.clear()
    }
    window.addEventListener('auth:expired', onExpired)
    return () => window.removeEventListener('auth:expired', onExpired)
  }, [queryClient])

  const login = useCallback(
    async (email: string, password: string, remember: boolean) => {
      const { data } = await api.post<LoginResult>('/auth/login', { email, password, remember, device_name: navigator.userAgent.slice(0, 90) })
      tokenStore.set(data.token)
      const next: Me = { user: data.user, organization: data.organization, permissions: data.permissions, is_platform_user: data.is_platform_user }
      applyPreferences(next)
      queryClient.clear()
      setMe(next)
      return next
    },
    [queryClient],
  )

  const logout = useCallback(async () => {
    try {
      await api.post('/auth/logout')
    } catch {
      /* token may already be invalid */
    }
    tokenStore.clear()
    queryClient.clear()
    setMe(null)
  }, [queryClient])

  const can = useCallback(
    (permission: string | string[]) => {
      const list = me?.permissions ?? []
      return Array.isArray(permission) ? permission.some((p) => list.includes(p)) : list.includes(permission)
    },
    [me],
  )

  const value = useMemo(() => ({ me, loading, login, logout, refresh, can }), [me, loading, login, logout, refresh, can])

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}
