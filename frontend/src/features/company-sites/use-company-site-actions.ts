import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import {
  companySiteDetailQueryKey,
  deleteCompanySiteLogo,
  setDefaultCompanySite,
  uploadCompanySiteLogo,
} from '@/features/company-sites/api'
import type { CompanySiteDetailWithPermissions } from '@/features/company-sites/types'

/**
 * The detail's two actions that hit the backend at once, outside the row
 * editors: the logo upload/remove and "Società di Default" (AC-020). Each
 * seeds the cached detail with the record the server answers (permissions
 * included) and tells the host, so the grid refreshes without closing the
 * record.
 */
export function useCompanySiteActions(site: CompanySiteDetailWithPermissions, onChanged?: () => void) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [settingDefault, setSettingDefault] = useState(false)

  const seed = (updated: CompanySiteDetailWithPermissions) => {
    queryClient.setQueryData(companySiteDetailQueryKey(site.id), updated)
    onChanged?.()
  }

  const uploadLogo = async (file: File) => seed(await uploadCompanySiteLogo(site.id, file))

  const removeLogo = async () => seed(await deleteCompanySiteLogo(site.id))

  const setDefault = async () => {
    setSettingDefault(true)
    try {
      seed(await setDefaultCompanySite(site.id))
      toast.success(t('companySites.form.defaultSet'))
    } catch {
      toast.error(t('companySites.form.defaultError'))
    } finally {
      setSettingDefault(false)
    }
  }

  return {
    uploadLogo,
    removeLogo,
    setDefault,
    settingDefault,
    canUploadLogo: site.permissions.actions.upload_logo !== false,
    canRemoveLogo: site.permissions.actions.delete_logo !== false,
    canSetDefault: !site.is_default && site.permissions.actions.set_default === true,
  }
}
