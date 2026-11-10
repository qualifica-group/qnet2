import { useCallback, useEffect, useMemo, useRef, useState, type ReactNode } from 'react'
import { useQuery } from '@tanstack/react-query'
import { fetchImpersonation } from '@/features/auth/api'
import { authKeys } from '@/features/auth/query-keys'
import { useAuth } from '@/features/auth/use-auth'
import { OnboardingContext, type OnboardingContextValue } from '@/features/onboarding/onboarding-context'
import { ONBOARDING_STEPS } from '@/features/onboarding/steps'
import {
  autostartMode,
  availableSteps,
  manualStartMode,
  skipOutcome,
  type ImpersonationPhase,
} from '@/features/onboarding/tour-rules'
import type { TourMode } from '@/features/onboarding/types'
import { OnboardingTour } from '@/features/onboarding/components/onboarding-tour'
import { useSaveOnboarding } from '@/features/onboarding/use-save-onboarding'
import { useTourLayout } from '@/features/onboarding/use-tour-layout'
import { useTourSidebar } from '@/features/onboarding/use-tour-sidebar'

interface TourSession {
  mode: TourMode
  stepId: string
}

const FIRST_STEP_ID = ONBOARDING_STEPS[0]?.id ?? ''

/**
 * Owns the welcome tour of the app shell (spec 0368): starts it by itself when
 * the user has never seen the current version, lets Settings replay it, drives
 * the steps and records the outcome. Mounted inside the shell, so it never runs
 * behind the first-access password gate (D-7).
 */
export function OnboardingProvider({ children }: { children: ReactNode }) {
  const { user } = useAuth()
  const impersonation = useImpersonationPhase(Boolean(user))
  const save = useSaveOnboarding()
  const [session, setSession] = useState<TourSession | null>(null)
  const autoStartedFor = useRef<number | null>(null)

  const layout = useTourLayout(session !== null, session?.stepId ?? null)
  const steps = availableSteps(ONBOARDING_STEPS, layout.presentIds)
  const currentIndex = steps.findIndex((step) => step.id === session?.stepId)
  const current = ONBOARDING_STEPS.find((step) => step.id === session?.stepId) ?? null
  useTourSidebar(current?.surface ?? null)

  const status = user?.onboarding_status ?? 'completed'

  // Step 1: automatic start, once per user and app load, after the
  // impersonation state is known (D-8) — server state drives the UI here.
  useEffect(() => {
    const mode = autostartMode(status, impersonation)
    if (!user || mode === null || autoStartedFor.current === user.id) {
      return
    }
    autoStartedFor.current = user.id
    setSession({ mode, stepId: FIRST_STEP_ID })
  }, [user, status, impersonation])

  const start = useCallback(() => {
    if (impersonation !== 'none') {
      return
    }
    setSession({ mode: manualStartMode(status), stepId: FIRST_STEP_ID })
  }, [impersonation, status])

  const goTo = (offset: number) => {
    const target = steps[currentIndex + offset]
    if (session && target) {
      setSession({ ...session, stepId: target.id })
    }
  }

  // Step 2: closing records the outcome; a replay that is skipped records nothing.
  const finish = () => {
    setSession(null)
    save.mutate('completed')
  }

  const skip = () => {
    setSession(null)
    const outcome = skipOutcome(status)
    if (outcome) {
      save.mutate(outcome)
    }
  }

  const value = useMemo<OnboardingContextValue>(
    () => ({ start, canStart: impersonation === 'none', isImpersonating: impersonation === 'active' }),
    [start, impersonation],
  )

  const anchoredSteps = steps.filter((step) => step.surface !== 'center')

  return (
    <OnboardingContext.Provider value={value}>
      {children}
      {session && current ? (
        <OnboardingTour
          step={current}
          mode={session.mode}
          userName={user?.name ?? ''}
          progress={{ current: anchoredSteps.indexOf(current) + 1, total: anchoredSteps.length }}
          isFirst={currentIndex <= 0}
          isLast={currentIndex === steps.length - 1}
          target={layout.target}
          viewport={layout.viewport}
          onNext={() => goTo(1)}
          onBack={() => goTo(-1)}
          onSkip={skip}
          onFinish={finish}
        />
      ) : null}
    </OnboardingContext.Provider>
  )
}

/**
 * Reads the impersonation state from the same cached query the auth layer
 * uses (no extra request): `pending` until it answers, and also on error, so
 * the tour never starts on an uncertain identity.
 */
function useImpersonationPhase(enabled: boolean): ImpersonationPhase {
  const query = useQuery({
    queryKey: authKeys.impersonation,
    queryFn: fetchImpersonation,
    enabled,
    retry: false,
  })

  if (!query.isSuccess) {
    return 'pending'
  }
  return query.data.impersonator ? 'active' : 'none'
}
