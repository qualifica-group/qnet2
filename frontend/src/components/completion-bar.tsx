import { useTranslation } from 'react-i18next'
import { Progress } from '@/components/ui/progress'
import { completionTone } from '@/components/completion-tone'
import { cn } from '@/lib/utils'

const EMPTY_VALUE = '—'

interface CompletionBarProps {
  /** 0..100; `null` renders a neutral empty bar and a dash (e.g. no status picked yet). */
  value: number | null
  /** Accessible name of the bar. */
  label: string
  size?: 'xs' | 'sm'
  /** Width of the bar (`w-16` by default; `flex-1` to fill the row). */
  barClassName?: string
  /** Extra classes on the percentage figure (e.g. a fixed width to align a list). */
  valueClassName?: string
  className?: string
}

/**
 * The one completion bar of the app: a compact bar plus its figure, both
 * coloured by `completionTone`, so tasks, subtasks, the task board and the
 * commessa read the same number the same way wherever it appears.
 */
export function CompletionBar({
  value,
  label,
  size = 'xs',
  barClassName = 'w-16',
  valueClassName,
  className,
}: CompletionBarProps) {
  const { t } = useTranslation()
  const tone = value !== null ? completionTone(value) : null

  return (
    <span className={cn('flex items-center gap-2', className)}>
      <Progress
        value={value ?? 0}
        size={size}
        className={cn('shrink-0', barClassName, tone?.track)}
        indicatorClassName={tone?.indicator}
        aria-label={label}
      />
      <span
        className={cn(
          'shrink-0 text-xs tabular-nums',
          tone ? cn('font-semibold', tone.text) : 'text-muted-foreground',
          valueClassName,
        )}
      >
        {value !== null ? t('tasks.form.percentValue', { value }) : EMPTY_VALUE}
      </span>
    </span>
  )
}
