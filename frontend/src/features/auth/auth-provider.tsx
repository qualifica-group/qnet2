import { useCallback, useEffect, useMemo, useState, type ReactNode } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { PASSWORD_CHANGE_REQUIRED_EVENT, UNAUTHORIZED_EVENT } from '@/api/client'
import { tokenStorage } from '@/api/token-storage'
import { applyLocale } from '@/i18n'
import { fetchMe, login as loginRequest, logout as logoutRequest } from '@/features/auth/api'
import { authKeys } from '@/features/auth/query-keys'
import { AuthContext, type AuthContextValue } from '@/features/auth/auth-context'
import { useImpersonationActions } from '@/features/auth/use-impersonation-actions'
import type { LoginPayload } from '@/features/auth/types'

export function AuthProvider({ children }: { children: ReactNode }) {
  const queryClient = useQueryClient()
  const [token, setTokenState] = useState<string | null>(() => tokenStorage.get())

  const setToken = useCallback((value: string | null) => {
    if (value) {
      tokenStorage.set(value)
    } else {
      tokenStorage.clear()
    }
    setTokenState(value)
  }, [])

  const meQuery = useQuery({
    queryKey: authKeys.me,
    queryFn: fetchMe,
    enabled: Boolean(token),
    retry: false,
  })

  const loginMutation = useMutation({
    mutationFn: loginRequest,
    onSuccess: (result) => {
      setToken(result.token)
      queryClient.setQueryData(authKeys.me, result.user)
    },
  })

  const login = useCallback(
    async (payload: LoginPayload) => {
      await loginMutation.mutateAsync(payload)
    },
    [loginMutation],
  )

  const logout = useCallback(async () => {
    try {
      await logoutRequest()
    } finally {
      setToken(null)
      queryClient.clear()
    }
  }, [queryClient, setToken])

  const { impersonator, impersonate, stopImpersonation } = useImpersonationActions({
    token,
    setToken,
  })

  // A failed `me` fetch means the session can no longer be trusted: log out
  // and let ProtectedRoute redirect to /login. 401s are already handled by the
  // UNAUTHORIZED_EVENT below; this covers the other failures (5xx, network)
  // that would otherwise leave the app rendered with no authenticated user.
  useEffect(() => {
    if (token && meQuery.isError) {
      void logout()
    }
  }, [token, meQuery.isError, logout])

  // Sync the UI language to the authenticated user's preferred locale.
  const userLocale = meQuery.data?.locale
  useEffect(() => {
    applyLocale(userLocale)
  }, [userLocale])

  // The API client signals an expired/invalid token via this event.
  useEffect(() => {
    const handleUnauthorized = () => {
      setTokenState(null)
      queryClient.clear()
    }
    window.addEventListener(UNAUTHORIZED_EVENT, handleUnauthorized)
    return () => window.removeEventListener(UNAUTHORIZED_EVENT, handleUnauthorized)
  }, [queryClient])

  // A 403 `password_change_required` (spec 0355): refresh the user so the gate shows without a reload.
  useEffect(() => {
    const handlePasswordChangeRequired = () => {
      void queryClient.invalidateQueries({ queryKey: authKeys.me })
    }
    window.addEventListener(PASSWORD_CHANGE_REQUIRED_EVENT, handlePasswordChangeRequired)
    return () => window.removeEventListener(PASSWORD_CHANGE_REQUIRED_EVENT, handlePasswordChangeRequired)
  }, [queryClient])

  const value = useMemo<AuthContextValue>(
    () => ({
      user: meQuery.data ?? null,
      isAuthenticated: Boolean(token),
      isInitializing: Boolean(token) && meQuery.isPending,
      login,
      logout,
      impersonator,
      impersonate,
      stopImpersonation,
    }),
    [token, meQuery.data, meQuery.isPending, login, logout, impersonator, impersonate, stopImpersonation],
  )

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}
