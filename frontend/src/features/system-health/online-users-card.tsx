import { useTranslation } from 'react-i18next'
import { Badge } from '@/components/ui/badge'
import { Card } from '@/components/ui/card'
import { formatRelativeTime } from '@/features/system-health/relative-time'
import type { OnlineUsers } from '@/features/system-health/types'

export function OnlineUsersCard({ online }: { online: OnlineUsers }) {
  const { t, i18n } = useTranslation('systemHealth')

  return (
    <Card className="gap-3 p-4">
      <div className="flex items-baseline justify-between gap-3">
        <h2 className="text-base font-semibold">{t('online.title')}</h2>
        <p className="text-xs text-muted-foreground">
          {t('online.window', { minutes: online.window_minutes })}
        </p>
      </div>
      <p className="text-3xl font-semibold tabular-nums" aria-label={t('online.countLabel', { count: online.count })}>
        {online.count}
      </p>
      {online.users.length === 0 ? (
        <p className="text-sm text-muted-foreground">{t('online.empty')}</p>
      ) : (
        <ul className="flex flex-col divide-y divide-border">
          {online.users.map((user) => (
            <li
              key={user.id}
              className="flex flex-wrap items-center justify-between gap-x-3 gap-y-1 py-1.5 text-sm"
            >
              <div className="flex min-w-0 flex-col">
                <span className="truncate font-medium">{user.name}</span>
                <span className="truncate text-xs text-muted-foreground">{user.email}</span>
              </div>
              <div className="flex items-center gap-2">
                {user.impersonating ? (
                  <Badge variant="outline">
                    {t('online.impersonating', { name: user.impersonating.name })}
                  </Badge>
                ) : null}
                <span className="text-xs text-muted-foreground">
                  {formatRelativeTime(user.last_seen_at, i18n.language)}
                </span>
              </div>
            </li>
          ))}
        </ul>
      )}
    </Card>
  )
}
