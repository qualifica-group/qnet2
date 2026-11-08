import { useTranslation } from 'react-i18next'
import { cn } from '@/lib/utils'

/** "Step N of M" plus one segment per step, the done ones filled. */
export function TourProgress({ current, total }: { current: number; total: number }) {
  const { t } = useTranslation('onboarding')

  return (
    <div className="flex flex-col gap-1.5">
      <span className="text-xs font-medium text-muted-foreground" aria-live="polite">
        {t('tour.progress', { current, total })}
      </span>
      <div aria-hidden className="flex gap-1">
        {Array.from({ length: total }, (_, index) => (
          <span
            key={index}
            className={cn('h-1 flex-1 rounded-full', index < current ? 'bg-primary' : 'bg-muted')}
          />
        ))}
      </div>
    </div>
  )
}
