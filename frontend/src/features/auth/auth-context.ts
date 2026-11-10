import { createContext } from 'react'
import type { Impersonator, LoginPayload, User } from '@/features/auth/types'

export interface AuthContextValue {
  user: User | null
  isAuthenticated: boolean
  /** True while the persisted token is being validated on first load. */
  isInitializing: boolean
  /** True when validating the persisted token timed out: the session is kept and the user can retry. */
  hasInitTimedOut: boolean
  /** Re-runs the token validation after a timeout. */
  retryInit: () => void
  login: (payload: LoginPayload) => Promise<void>
  logout: () => Promise<void>
  /** The original actor's identity while impersonating another user, else null. */
  impersonator: Impersonator | null
  /** Starts impersonating the given user; swaps the session token in place. */
  impersonate: (userId: number) => Promise<void>
  /** Ends impersonation; swaps the session token back to the original actor. */
  stopImpersonation: () => Promise<void>
}

export const AuthContext = createContext<AuthContextValue | null>(null)
