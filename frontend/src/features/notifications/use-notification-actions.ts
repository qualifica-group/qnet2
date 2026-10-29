import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { apiErrorMessage } from '@/lib/forbidden-message'
import { toast } from 'sonner'
import {
  markAllNotificationsAsRead,
  markNotificationAsRead,
  markNotificationAsUnread,
  markNotificationsAsRead,
} from '@/features/notifications/api'
import { notificationKeys } from '@/features/notifications/query-keys'

/**
 * Mutations for marking notifications as read/unread, single or bulk (spec
 * 0150 extends the original single/all pair). On success every one
 * invalidates the shared `all` key (D-7), so the bell badge and the tab title
 * stay aligned with whatever surface (bell panel or `/notifications` page)
 * triggered the change. Errors surface as a toast using i18n keys.
 */
export function useNotificationActions() {
  const { t, i18n } = useTranslation()
  const queryClient = useQueryClient()

  const invalidate = () =>
    queryClient.invalidateQueries({ queryKey: notificationKeys.all })

  const markAsRead = useMutation({
    mutationFn: (id: string) => markNotificationAsRead(id),
    onSuccess: invalidate,
    onError: (error) => toast.error(apiErrorMessage(error, t('notifications.actionError'), i18n)),
  })

  const markAsUnread = useMutation({
    mutationFn: (id: string) => markNotificationAsUnread(id),
    onSuccess: invalidate,
    onError: (error) => toast.error(apiErrorMessage(error, t('notifications.actionError'), i18n)),
  })

  const markAllAsRead = useMutation({
    mutationFn: () => markAllNotificationsAsRead(),
    onSuccess: invalidate,
    onError: (error) => toast.error(apiErrorMessage(error, t('notifications.actionError'), i18n)),
  })

  const markManyAsRead = useMutation({
    mutationFn: (ids: string[]) => markNotificationsAsRead(ids),
    onSuccess: invalidate,
    onError: (error) => toast.error(apiErrorMessage(error, t('notifications.actionError'), i18n)),
  })

  return { markAsRead, markAsUnread, markAllAsRead, markManyAsRead }
}
