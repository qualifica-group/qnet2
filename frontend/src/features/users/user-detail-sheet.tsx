import { useCallback, useMemo, useState, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router-dom'
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
  SheetToolbar,
} from '@/components/ui/sheet'
import { SheetDetailPageLink } from '@/features/modules/sheet-detail-page-link'
import { UserDetailSheetContext } from '@/features/users/user-detail-sheet-context'
import { UserDetailView } from '@/features/users/user-detail'

/** Domain key, kept in sync with the users module Sheet layout storage key. */
const USERS_DOMAIN = 'users'

/**
 * Owns a single application-wide Sheet that shows a user's read-only detail
 * (`UserDetailView`). Any user cell across the app (leads operator, opportunities
 * supervisor, business-functions manager/members, users reports_to) opens it via
 * `useUserDetailSheet().openUserDetail(id)` — so opening a person's card is one
 * shared surface, not a Sheet per cell. Mounted once by `AppLayout`, INSIDE the
 * router: the detail renders `RecordLink`s, which need router context.
 */
export function UserDetailSheetProvider({ children }: { children: ReactNode }) {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const [userId, setUserId] = useState<number | null>(null)

  const openUserDetail = useCallback((id: number) => setUserId(id), [])

  const closeUserDetail = useCallback(() => setUserId(null), [])

  const openUserDetailPage = useCallback(
    (path: string) => {
      closeUserDetail()
      void navigate(path)
    },
    [closeUserDetail, navigate],
  )

  const onOpenChange = useCallback(
    (open: boolean) => {
      if (!open) {
        closeUserDetail()
      }
    },
    [closeUserDetail],
  )

  const value = useMemo(() => ({ openUserDetail }), [openUserDetail])

  return (
    <UserDetailSheetContext.Provider value={value}>
      {children}
      <Sheet open={userId !== null} onOpenChange={onOpenChange}>
        <SheetContent className="gap-0" showCloseButton={false} storageKey={`sheet-width:${USERS_DOMAIN}`}>
          <SheetToolbar closeLabel={t('common.close')}>
            {userId !== null && (
              <SheetDetailPageLink domain={USERS_DOMAIN} id={userId} onOpen={openUserDetailPage} />
            )}
          </SheetToolbar>
          <SheetHeader className="sr-only">
            <SheetTitle>{t('users.detail.title')}</SheetTitle>
            <SheetDescription>{t('users.detail.subtitle')}</SheetDescription>
          </SheetHeader>
          {userId !== null && <UserDetailView userId={userId} />}
        </SheetContent>
      </Sheet>
    </UserDetailSheetContext.Provider>
  )
}
