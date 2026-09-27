import { createContext } from 'react'
import type { Me } from '@/types/models'

export interface AuthState {
  me: Me | null
  loading: boolean
  login: (email: string, password: string, remember: boolean) => Promise<Me>
  logout: () => Promise<void>
  refresh: () => Promise<void>
  can: (permission: string | string[]) => boolean
}

export const AuthContext = createContext<AuthState | null>(null)
