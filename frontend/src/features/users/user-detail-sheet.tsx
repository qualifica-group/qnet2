import { useCallback, useMemo, useState, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
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
import { router } from '@/routes/router'

/** Domain key, kept in sync with the users module Sheet layout storage key. */
const USERS_DOMAIN = 'users'

/**
 * Owns a single application-wide Sheet that shows a user's read-only detail
 * (`UserDetailView`). Any user cell across the app (leads operator, opportunities
 * supervisor, business-functions manager/members, users reports_to) opens it via
 * `useUserDetailSheet().openUserDetail(id)` — so opening a person's card is one
 * shared surface, not a Sheet per cell. Mounted once near the router root, under
 * auth + query providers so the detail fetch is authorized.
 */
export function UserDetailSheetProvider({ children }: { children: ReactNode }) {
  const { t } = useTranslation()
  const [userId, setUserId] = useState<number | null>(null)

  const openUserDetail = useCallback((id: number) => setUserId(id), [])

  const closeUserDetail = useCallback(() => setUserId(null), [])

  // This provider sits above `RouterProvider` (App.tsx), so router hooks and
  // `<Link>` have no context here: navigate through the router instance.
  const openUserDetailPage = useCallback(
    (path: string) => {
      closeUserDetail()
      void router.navigate(path)
    },
    [closeUserDetail],
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
