import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { AvatarUpload } from '@/components/avatar-upload'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { deleteUserAvatar, uploadUserAvatar, userDetailQueryKey } from '@/features/users/api'
import type { UserDetail } from '@/features/users/types'

interface UserAvatarFieldProps {
  user: UserDetail
  /** Called after an upload/removal, so the host refreshes whatever lists the user. */
  onChanged?: () => void
}

/**
 * The detail's profile photo (spec 0222 D-5): uploads and removals apply at
 * once (actions `upload_avatar`/`delete_avatar`), then the detail is
 * refetched — the avatar endpoints answer without the profile tree, so their
 * response must not replace the cached record.
 */
export function UserAvatarField({ user, onChanged }: UserAvatarFieldProps) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const { canAction } = useResourcePermissions()

  const refresh = async () => {
    await queryClient.invalidateQueries({ queryKey: userDetailQueryKey(user.id) })
    onChanged?.()
  }

  return (
    <AvatarUpload
      mode="immediate"
      label={t('users.form.avatarLabel')}
      name={user.name}
      avatarUrl={user.avatar_url}
      onUpload={async (file) => {
        await uploadUserAvatar(user.id, file)
        await refresh()
      }}
      onRemove={async () => {
        await deleteUserAvatar(user.id)
        await refresh()
      }}
      canUpload={canAction('upload_avatar')}
      canRemove={canAction('delete_avatar')}
    />
  )
}
