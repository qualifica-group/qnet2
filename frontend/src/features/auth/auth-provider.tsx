import { useCallback, useEffect, useMemo, useState, type ReactNode } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { PASSWORD_CHANGE_REQUIRED_EVENT, UNAUTHORIZED_EVENT } from '@/api/client'
import { isTimeoutError } from '@/api/boot-request'
import { isMaintenanceError } from '@/api/maintenance-state'
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
  // Maintenance says nothing about the session (spec 0376): the token is kept.
  // Neither does a timeout: the server is slow, and ProtectedRoute offers a retry.
  const isMeInMaintenance = isMaintenanceError(meQuery.error)
  const isMeTimedOut = isTimeoutError(meQuery.error)
  useEffect(() => {
    if (token && meQuery.isError && !isMeInMaintenance && !isMeTimedOut) {
      void logout()
    }
  }, [token, meQuery.isError, isMeInMaintenance, isMeTimedOut, logout])

  const { refetch: refetchMe } = meQuery
  const retryInit = useCallback(() => {
    void refetchMe()
  }, [refetchMe])

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
      // A retry after a timeout counts as initializing again: the loader shows
      // until `me` answers or times out once more.
      isInitializing:
        Boolean(token) &&
        (meQuery.isPending ||
          (isMeInMaintenance && !meQuery.data) ||
          (isMeTimedOut && meQuery.isFetching)),
      hasInitTimedOut: Boolean(token) && isMeTimedOut && !meQuery.isFetching && !meQuery.data,
      retryInit,
      login,
      logout,
      impersonator,
      impersonate,
      stopImpersonation,
    }),
    [token, meQuery.data, meQuery.isPending, meQuery.isFetching, isMeInMaintenance, isMeTimedOut, retryInit, login, logout, impersonator, impersonate, stopImpersonation],
  )

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}
