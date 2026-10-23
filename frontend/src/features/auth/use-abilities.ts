import { useCallback } from 'react'
import { useQuery } from '@tanstack/react-query'
import { fetchAbilities } from '@/features/auth/api'
import { authKeys } from '@/features/auth/query-keys'
import { useAuth } from '@/features/auth/use-auth'

/**
 * Client-side permission checks for conditional UI.
 *
 * SECURITY: this only hides/shows UI. Every protected action must still be
 * authorized server-side by the backend Policies — never trust these checks
 * as an access-control boundary.
 */
export function useAbilities() {
  const { isAuthenticated } = useAuth()

  const abilitiesQuery = useQuery({
    queryKey: authKeys.abilities,
    queryFn: fetchAbilities,
    enabled: isAuthenticated,
    staleTime: 5 * 60 * 1000,
  })

  const permissions = abilitiesQuery.data?.permissions
  const roles = abilitiesQuery.data?.roles
  const hasSubordinates = abilitiesQuery.data?.has_subordinates ?? false

  const can = useCallback(
    (permission: string) => permissions?.[permission] === true,
    [permissions],
  )

  const hasRole = useCallback((role: string) => roles?.includes(role) ?? false, [roles])

  return {
    can,
    hasRole,
    roles: roles ?? [],
    hasSubordinates,
    isLoading: abilitiesQuery.isLoading,
  }
}
