import { useRef } from 'react'
import { ImagePlus } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { cn } from '@/lib/utils'
import {
  useDeleteDocumentLayoutImage,
  useDocumentLayoutImages,
  useUploadDocumentLayoutImage,
} from '@/features/document-layouts/editor/images/document-layout-images-api'
import { IntegerField } from '@/features/document-layouts/editor/shared/integer-field'
import { MAX_IMAGES_PER_LAYOUT, MAX_IMAGE_POINTS } from '@/features/document-layouts/layout-config-defaults'
import { ALIGN_LCR, IMAGE_WRAPS } from '@/features/document-layouts/layout-config'
import type { AlignLCR, DocumentLayoutZoneName, ImageBlock, ImageWrap } from '@/features/document-layouts/layout-config'

/** UI-only convenience default when switching to `behind_page` and `height` is still null (contract requires a concrete height). */
const DEFAULT_BEHIND_PAGE_HEIGHT_POINTS = 800
const ACCEPTED_IMAGE_TYPES = 'image/jpeg,image/png'

interface ImageBlockInspectorProps {
  block: ImageBlock
  zone: DocumentLayoutZoneName
  /** `null` for an unsaved (create-mode) layout — no id to attach images to yet. */
  layoutId: number | null
  onChange: (next: ImageBlock) => void
  disabled: boolean
}

/**
 * The `image` block editor (AC-126): pick among the layout's uploaded
 * images, set width/align/wrap, upload more. `wrap: 'behind_page'` is only
 * offered in the `header` zone (D-11, mirrored by
 * `layout-config-schema.ts`'s zone-level check). Two distinct empty states:
 * the layout has no id yet (must be saved first), or it has an id but zero
 * images uploaded (AC-126's own empty state, with the upload action).
 */
export function ImageBlockInspector({ block, zone, layoutId, onChange, disabled }: ImageBlockInspectorProps) {
  const { t } = useTranslation()
  const fileInputRef = useRef<HTMLInputElement>(null)
  const imagesQuery = useDocumentLayoutImages(layoutId)
  const uploadMutation = useUploadDocumentLayoutImage(layoutId)
  const deleteMutation = useDeleteDocumentLayoutImage(layoutId)
  const images = imagesQuery.data ?? []

  function handleFileSelected(event: React.ChangeEvent<HTMLInputElement>) {
    const file = event.target.files?.[0]
    event.target.value = ''
    if (!file) {
      return
    }
    uploadMutation.mutate(file)
  }

  function handleWrapChange(wrap: ImageWrap) {
    const height = wrap === 'behind_page' && block.height === null ? DEFAULT_BEHIND_PAGE_HEIGHT_POINTS : block.height
    onChange({ ...block, wrap, height })
  }

  if (layoutId === null) {
    return <p className="text-xs text-muted-foreground italic">{t('documentLayouts.editor.image.saveLayoutFirst')}</p>
  }

  if (images.length === 0) {
    return (
      <div className="flex flex-col items-center gap-2 rounded-md border border-dashed border-border p-4 text-center">
        <ImagePlus className="size-5 text-muted-foreground" aria-hidden="true" />
        <p className="text-xs text-muted-foreground">{t('documentLayouts.editor.image.empty')}</p>
        <input
          ref={fileInputRef}
          type="file"
          accept={ACCEPTED_IMAGE_TYPES}
          disabled={disabled || uploadMutation.isPending}
          onChange={handleFileSelected}
          aria-label={t('documentLayouts.editor.image.upload')}
          className="text-xs"
        />
      </div>
    )
  }

  const wrapOptions: ImageWrap[] = zone === 'header' ? [...IMAGE_WRAPS] : ['inline']

  return (
    <div className="flex flex-col gap-3">
      <div className="grid grid-cols-3 gap-1.5">
        {images.map((image) => (
          <button
            key={image.attachment_id}
            type="button"
            disabled={disabled}
            onClick={() => onChange({ ...block, attachment_id: image.attachment_id })}
            className={cn(
              'flex flex-col items-center gap-1 rounded-md border p-1',
              block.attachment_id === image.attachment_id ? 'border-primary ring-2 ring-primary' : 'border-border',
            )}
          >
            <img src={image.data_uri} alt={image.filename} className="h-12 w-full rounded object-cover" />
            <span className="w-full truncate text-[10px] text-muted-foreground">{image.filename}</span>
          </button>
        ))}
      </div>

      <div className="flex items-center justify-between gap-2">
        <input
          ref={fileInputRef}
          type="file"
          accept={ACCEPTED_IMAGE_TYPES}
          disabled={disabled || uploadMutation.isPending || images.length >= MAX_IMAGES_PER_LAYOUT}
          onChange={handleFileSelected}
          aria-label={t('documentLayouts.editor.image.upload')}
          className="text-xs"
        />
        <Button
          type="button"
          variant="ghost"
          size="xs"
          className="text-muted-foreground hover:text-destructive"
          disabled={disabled || deleteMutation.isPending}
          onClick={() => deleteMutation.mutate(block.attachment_id)}
        >
          {t('documentLayouts.editor.image.removeSelected')}
        </Button>
      </div>

      <div className="grid grid-cols-2 gap-2">
        <IntegerField
          label={t('documentLayouts.editor.image.width')}
          value={block.width}
          min={1}
          max={MAX_IMAGE_POINTS}
          onCommit={(width) => onChange({ ...block, width })}
          disabled={disabled}
        />
        <IntegerField
          label={t('documentLayouts.editor.image.height')}
          value={block.height ?? block.width}
          min={1}
          max={MAX_IMAGE_POINTS}
          onCommit={(height) => onChange({ ...block, height })}
          disabled={disabled}
        />
      </div>

      <div className="flex flex-col gap-1">
        <label className="text-xs text-muted-foreground" htmlFor="image-block-align">
          {t('documentLayouts.editor.image.align')}
        </label>
        <Select value={block.align} onValueChange={(value) => onChange({ ...block, align: value as AlignLCR })} disabled={disabled}>
          <SelectTrigger id="image-block-align" className="h-7 w-full text-xs">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            {ALIGN_LCR.map((align) => (
              <SelectItem key={align} value={align}>
                {t(`documentLayouts.editor.image.aligns.${align}`)}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>

      <div className="flex flex-col gap-1">
        <label className="text-xs text-muted-foreground" htmlFor="image-block-wrap">
          {t('documentLayouts.editor.image.wrap')}
        </label>
        <Select value={block.wrap} onValueChange={(value) => handleWrapChange(value as ImageWrap)} disabled={disabled}>
          <SelectTrigger id="image-block-wrap" className="h-7 w-full text-xs">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            {wrapOptions.map((wrap) => (
              <SelectItem key={wrap} value={wrap}>
                {t(`documentLayouts.editor.image.wraps.${wrap}`)}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>
    </div>
  )
}
