import { useTranslation } from 'react-i18next'
import { CircleCheck, CircleDashed, CircleX, LoaderCircle, TriangleAlert, type LucideIcon } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import {
  resolveMigrationSourceState,
  type MigrationSourceState,
} from '@/features/migrations/migration-source-state'
import type { MigrationSourceSummary } from '@/features/migrations/types'

type BadgeVariant = 'default' | 'secondary' | 'destructive' | 'outline'

const STATE_APPEARANCE: Record<MigrationSourceState, { variant: BadgeVariant; icon: LucideIcon; iconClassName?: string }> = {
  migrated: { variant: 'default', icon: CircleCheck },
  partial: { variant: 'outline', icon: TriangleAlert },
  failed: { variant: 'destructive', icon: CircleX },
  running: { variant: 'secondary', icon: LoaderCircle, iconClassName: 'animate-spin' },
  notMigrated: { variant: 'outline', icon: CircleDashed },
}

interface MigrationSourceStatusProps {
  source: MigrationSourceSummary
}

/** The migration state of a source as a compact badge: icon plus label, never color alone. */
export function MigrationSourceStatus({ source }: MigrationSourceStatusProps) {
  const { t } = useTranslation('migrations')
  const state = resolveMigrationSourceState(source)
  const { variant, icon: Icon, iconClassName } = STATE_APPEARANCE[state]

  return (
    <Badge variant={variant}>
      <Icon aria-hidden="true" className={iconClassName} />
      {t(`state.${state}`)}
    </Badge>
  )
}
