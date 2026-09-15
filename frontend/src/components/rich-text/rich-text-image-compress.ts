/**
 * Client-side downscale/recompress of a pasted/dropped/picked image (D-3),
 * so a phone photo doesn't blow past the web server's request body limit
 * once base64-inlined into the save payload (constraints: "10 x 5 MB in
 * base64 ~= 70 MB", and this Herd/nginx install caps `client_max_body_size`
 * at 2 MB → 413). `RICH_TEXT_IMAGE_MAX_BYTES` is still enforced by the
 * caller on the RESULT of this function, never on the original file.
 */

/** Longest side, in pixels, an inserted image is downscaled to. */
export const RICH_TEXT_IMAGE_MAX_DIMENSION = 1920

const RICH_TEXT_IMAGE_QUALITY = 0.85

function canvasToBlob(canvas: HTMLCanvasElement, type: string, quality: number): Promise<Blob | null> {
  return new Promise((resolve) => {
    if (typeof canvas.toBlob !== 'function') {
      resolve(null)
      return
    }
    canvas.toBlob((blob) => resolve(blob), type, quality)
  })
}

/** Swaps the file extension to match a re-encoded blob's MIME type. */
function withMatchingExtension(name: string, mimeType: string): string {
  const base = name.replace(/\.[^./\\]+$/, '')
  const extension = mimeType === 'image/webp' ? 'webp' : mimeType === 'image/jpeg' ? 'jpg' : 'png'
  return `${base}.${extension}`
}

/**
 * Downscales an image to `RICH_TEXT_IMAGE_MAX_DIMENSION` on its longest side
 * and re-encodes it as WebP (falling back to JPEG when the canvas can't
 * actually encode WebP — some browsers silently substitute PNG instead of
 * honoring the requested type, so the result's own `blob.type` is checked
 * rather than trusted). GIFs are returned untouched (a re-encode would
 * collapse their animation to a single frame). Any failure along the way —
 * unsupported `createImageBitmap`/canvas, a corrupt image — falls back to
 * the original file so a save is never blocked by a compression bug; the
 * caller's size check runs on whatever this function returns either way.
 */
export async function compressRichTextImage(file: File): Promise<File> {
  if (file.type === 'image/gif') {
    return file
  }

  try {
    const bitmap = await createImageBitmap(file)
    const scale = Math.min(1, RICH_TEXT_IMAGE_MAX_DIMENSION / Math.max(bitmap.width, bitmap.height))
    const width = Math.round(bitmap.width * scale)
    const height = Math.round(bitmap.height * scale)

    const canvas = document.createElement('canvas')
    canvas.width = width
    canvas.height = height
    const context = canvas.getContext('2d')
    if (!context) {
      return file
    }
    context.drawImage(bitmap, 0, 0, width, height)
    bitmap.close?.()

    let blob = await canvasToBlob(canvas, 'image/webp', RICH_TEXT_IMAGE_QUALITY)
    if (!blob || blob.type !== 'image/webp') {
      blob = await canvasToBlob(canvas, 'image/jpeg', RICH_TEXT_IMAGE_QUALITY)
    }
    if (!blob || blob.size >= file.size) {
      return file
    }

    return new File([blob], withMatchingExtension(file.name, blob.type), { type: blob.type })
  } catch {
    return file
  }
}
