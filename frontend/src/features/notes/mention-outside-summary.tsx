import { useTranslation } from 'react-i18next'
import { TriangleAlert, UserPlus } from 'lucide-react'
import type { MentionableUser, MentionOutsideRole } from '@/features/notes/types'

interface MentionOutsideSummaryProps {
  outsiders: MentionableUser[]
  role: MentionOutsideRole | null
}

/**
 * Riepilogo sotto l'editor (spec 0353 D-9): chi, tra i menzionati nel testo,
 * non vede il record e verra' aggiunto al salvataggio della nota.
 */
export function MentionOutsideSummary({ outsiders, role }: MentionOutsideSummaryProps) {
  const { t } = useTranslation()

  if (outsiders.length === 0 || role === null) {
    return null
  }

  return (
    <div
      role="status"
      className="flex flex-col gap-1 rounded-md border border-border bg-muted/40 px-2.5 py-1.5 text-xs"
    >
      <p className="flex items-center gap-1.5 font-medium">
        <UserPlus className="size-3.5 shrink-0" aria-hidden="true" />
        {t(`notes.mentionOutside.summary.${role}`, { count: outsiders.length })}
      </p>
      <ul className="flex flex-col gap-0.5 pl-5">
        {outsiders.map((user) => (
          <li key={user.id} className="min-w-0">
            <span className="font-medium">{user.label}</span>
            {user.has_module_access ? null : (
              <span className="flex items-center gap-1 text-[11px] text-muted-foreground">
                <TriangleAlert className="size-3 shrink-0" aria-hidden="true" />
                {t('notes.mentionOutside.noModuleAccess')}
              </span>
            )}
          </li>
        ))}
      </ul>
    </div>
  )
}
