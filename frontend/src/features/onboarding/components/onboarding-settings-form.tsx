import { useTranslation } from 'react-i18next'
import { Compass } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { useOnboardingTour } from '@/features/onboarding/onboarding-context'

const HINT_ID = 'onboarding-tour-impersonating'

/**
 * Settings card to replay the welcome tour (spec 0368). Disabled while
 * impersonating: the tour belongs to the person, not to the session (D-8).
 */
export function OnboardingSettingsForm() {
  const { t } = useTranslation()
  const { start, canStart, isImpersonating } = useOnboardingTour()

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-col gap-1">
        <h3 className="text-base font-semibold">{t('settings.onboardingTour.title')}</h3>
        <p className="text-sm text-muted-foreground">{t('settings.onboardingTour.subtitle')}</p>
      </div>

      <div className="flex flex-col items-start gap-2">
        <Button
          type="button"
          size="sm"
          onClick={start}
          disabled={!canStart}
          aria-describedby={isImpersonating ? HINT_ID : undefined}
        >
          <Compass />
          {t('settings.onboardingTour.start')}
        </Button>
        {isImpersonating ? (
          <p id={HINT_ID} className="text-xs text-muted-foreground">
            {t('settings.onboardingTour.impersonating')}
          </p>
        ) : null}
      </div>
    </div>
  )
}
