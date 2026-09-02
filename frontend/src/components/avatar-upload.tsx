import { useEffect, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { Loader2, Trash2, Upload } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { UserAvatar } from '@/components/user-avatar'
import { validateAvatarFile } from '@/components/avatar-validation'

/**
 * IMMEDIATE mode: the component performs the upload/remove itself via the given
 * async callbacks and reflects their pending state. Used in settings and the
 * user edit form, where the user already exists.
 */
interface ImmediateModeProps {
  mode: 'immediate'
  /** Current avatar of the persisted entity (data: URI), or null. */
  avatarUrl: string | null
  onUpload: (file: File) => Promise<void>
  onRemove: () => Promise<void>
  /** Metadata-gated (spec 0004): hides the upload affordance. Defaults to true. */
  canUpload?: boolean
  /** Metadata-gated (spec 0004): hides the remove affordance. Defaults to true. */
  canRemove?: boolean
}

/**
 * DEFERRED mode: the component only previews the locally chosen file and bubbles
 * the File (or null on clear) up to the parent, which uploads later. Used in the
 * user create form, where there is no id to upload against yet.
 */
interface DeferredModeProps {
  mode: 'deferred'
  onFileSelected: (file: File | null) => void
}

type AvatarUploadProps = (ImmediateModeProps | DeferredModeProps) & {
  /** Display name used to derive the initials fallback. */
  name: string
  /** Optional accessible label / heading for the control. */
  label?: string
}

/**
 * Reusable avatar picker built on the shadcn Avatar. Renders the current avatar
 * (a data: URI supplied by the parent), an initials fallback, client-side
 * validation and the upload / remove affordances. Stateless about persistence:
 * the parent decides what happens with the chosen file (immediate vs deferred
 * mode).
 */
export function AvatarUpload(props: AvatarUploadProps) {
  const { t } = useTranslation()
  const { name, label } = props
  const inputRef = useRef<HTMLInputElement>(null)
  const [error, setError] = useState<string | null>(null)
  const [pending, setPending] = useState(false)
  const [localPreview, setLocalPreview] = useState<string | null>(null)
  const [hasLocalFile, setHasLocalFile] = useState(false)

  const remoteSrc = props.mode === 'immediate' ? props.avatarUrl : null
  const canUpload = props.mode === 'immediate' ? (props.canUpload ?? true) : true
  const canRemove = props.mode === 'immediate' ? (props.canRemove ?? true) : true

  // Revoke the deferred-mode local preview object URL on unmount (change/remove
  // already revoke the previous one). The ref mirrors the latest value via an
  // effect so the unmount cleanup can read it without depending on it.
  const localPreviewRef = useRef<string | null>(null)
  useEffect(() => {
    localPreviewRef.current = localPreview
  }, [localPreview])
  useEffect(
    () => () => {
      if (localPreviewRef.current) {
        URL.revokeObjectURL(localPreviewRef.current)
      }
    },
    [],
  )

  // Local preview (deferred mode) takes precedence over the persisted avatar.
  const displaySrc = localPreview ?? remoteSrc
  const showRemove =
    (props.mode === 'deferred' ? hasLocalFile : Boolean(props.avatarUrl)) && canRemove

  const validate = (file: File): string | null => {
    const reason = validateAvatarFile(file)
    if (reason === 'invalidType') {
      return t('avatar.invalidImage')
    }
    if (reason === 'tooLarge') {
      return t('avatar.imageTooLarge')
    }
    return null
  }

  const handleFileChange = async (
    event: React.ChangeEvent<HTMLInputElement>,
  ) => {
    const file = event.target.files?.[0] ?? null
    // Reset the input so selecting the same file again still fires onChange.
    event.target.value = ''
    if (!file) {
      return
    }

    const validationError = validate(file)
    if (validationError) {
      setError(validationError)
      toast.error(validationError)
      return
    }
    setError(null)

    if (props.mode === 'deferred') {
      if (localPreview) {
        URL.revokeObjectURL(localPreview)
      }
      setLocalPreview(URL.createObjectURL(file))
      setHasLocalFile(true)
      props.onFileSelected(file)
      return
    }

    setPending(true)
    try {
      await props.onUpload(file)
    } catch {
      const message = t('avatar.avatarUploadError')
      setError(message)
      toast.error(message)
    } finally {
      setPending(false)
    }
  }

  const handleRemove = async () => {
    setError(null)
    if (props.mode === 'deferred') {
      if (localPreview) {
        URL.revokeObjectURL(localPreview)
      }
      setLocalPreview(null)
      setHasLocalFile(false)
      props.onFileSelected(null)
      return
    }

    setPending(true)
    try {
      await props.onRemove()
    } catch {
      const message = t('avatar.avatarUploadError')
      setError(message)
      toast.error(message)
    } finally {
      setPending(false)
    }
  }

  return (
    <div className="flex flex-col gap-3">
      {label && <span className="text-sm font-medium">{label}</span>}
      <div className="flex items-center gap-4">
        <UserAvatar name={name} src={displaySrc} size="2xl" />

        <div className="flex flex-col gap-2">
          {canUpload && (
            <input
              ref={inputRef}
              type="file"
              accept="image/*"
              className="hidden"
              onChange={handleFileChange}
              disabled={pending}
            />
          )}
          <div className="flex gap-2">
            {canUpload && (
              <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={() => inputRef.current?.click()}
                disabled={pending}
              >
                {pending ? (
                  <Loader2 className="animate-spin" aria-hidden="true" />
                ) : (
                  <Upload aria-hidden="true" />
                )}
                {pending ? t('avatar.uploading') : t('avatar.chooseImage')}
              </Button>
            )}
            {showRemove && (
              <Button
                type="button"
                variant="ghost"
                size="sm"
                onClick={handleRemove}
                disabled={pending}
              >
                <Trash2 aria-hidden="true" />
                {t('avatar.removeAvatar')}
              </Button>
            )}
          </div>
          {error && (
            <p className="text-sm font-medium text-destructive" role="alert">
              {error}
            </p>
          )}
        </div>
      </div>
    </div>
  )
}
