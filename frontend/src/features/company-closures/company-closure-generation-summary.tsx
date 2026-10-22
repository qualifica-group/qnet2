import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import type { ClosureGeneration } from '@/features/company-closures/types'

interface CompanyClosureGenerationSummaryProps {
  generation: ClosureGeneration
  onClose: () => void
}

/** What a collective leave generated: how many requests were created and who was skipped, with the reason. */
export function CompanyClosureGenerationSummary({ generation, onClose }: CompanyClosureGenerationSummaryProps) {
  const { t } = useTranslation()

  return (
    <section aria-labelledby="closure-generation-title" className="flex flex-col gap-3 p-4">
      <h2 id="closure-generation-title" className="text-base font-semibold">
        {t('companyClosures.generation.title')}
      </h2>
      <p className="text-sm" role="status">
        {t('companyClosures.generation.created', { count: generation.created })}
      </p>
      {generation.skipped.length > 0 ? (
        <div className="flex flex-col gap-1.5">
          <p className="text-sm font-medium">{t('companyClosures.generation.skipped', { count: generation.skipped.length })}</p>
          <ul className="flex flex-col gap-1 text-sm">
            {generation.skipped.map(({ user, reason }) => (
              <li key={user.id} className="flex flex-wrap gap-x-2">
                <span className="font-medium">{user.name}</span>
                <span className="text-muted-foreground">{t(`companyClosures.generation.reasons.${reason}`)}</span>
              </li>
            ))}
          </ul>
        </div>
      ) : null}
      <div>
        <Button onClick={onClose}>{t('companyClosures.generation.close')}</Button>
      </div>
    </section>
  )
}
