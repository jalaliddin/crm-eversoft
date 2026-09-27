import type { ReactNode } from 'react'
import { Navigate, Outlet, useLocation } from 'react-router'
import { LoadingState } from '@/components/LoadingState'
import { ErrorState } from '@/components/ErrorState'
import { useAuth } from './useAuth'

/** Redirects anonymous visitors to /login, keeping the page they wanted. */
export function RequireAuth() {
  const { me, loading } = useAuth()
  const location = useLocation()

  if (loading) return <LoadingState fullscreen />
  if (!me) return <Navigate to="/login" replace state={{ from: location.pathname + location.search }} />

  return <Outlet />
}

export function RequireTenant() {
  const { me } = useAuth()
  if (me?.is_platform_user) return <Navigate to="/platform" replace />
  return <Outlet />
}

export function RequirePlatform() {
  const { me } = useAuth()
  if (!me?.is_platform_user) return <Navigate to="/dashboard" replace />
  return <Outlet />
}

export function RedirectIfAuthenticated() {
  const { me, loading } = useAuth()
  if (loading) return <LoadingState fullscreen />
  if (me) return <Navigate to={me.is_platform_user ? '/platform' : '/dashboard'} replace />
  return <Outlet />
}

/** Route-level permission check. */
export function RequirePermission({ permission, children }: { permission: string | string[]; children?: ReactNode }) {
  const { can } = useAuth()
  if (!can(permission)) {
    return <ErrorState title="No access" message="Your role does not include this section. Ask an administrator if you need it." />
  }
  return children ?? <Outlet />
}

/** Renders children only when the user has the permission. */
export function Can({ permission, children }: { permission: string | string[]; children: ReactNode }) {
  const { can } = useAuth()
  return can(permission) ? <>{children}</> : null
}
