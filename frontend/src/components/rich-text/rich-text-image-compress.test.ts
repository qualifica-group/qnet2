import { afterEach, describe, expect, it, vi } from 'vitest'
import { compressRichTextImage, RICH_TEXT_IMAGE_MAX_DIMENSION } from '@/components/rich-text/rich-text-image-compress'

interface FakeBitmap {
  width: number
  height: number
  close: () => void
}

/** jsdom has no canvas encoder — these globals are stubbed per test, never in `src/test/setup.ts`. */
function stubBitmap(width: number, height: number) {
  const bitmap: FakeBitmap = { width, height, close: vi.fn() }
  vi.stubGlobal('createImageBitmap', vi.fn().mockResolvedValue(bitmap))
  return bitmap
}

function stubCanvas({
  webp,
  jpeg,
}: {
  webp?: Blob | null
  jpeg?: Blob | null
} = {}) {
  const drawImage = vi.fn()
  HTMLCanvasElement.prototype.getContext = vi.fn(() => ({ drawImage })) as unknown as typeof HTMLCanvasElement.prototype.getContext
  HTMLCanvasElement.prototype.toBlob = vi.fn(function (
    this: HTMLCanvasElement,
    callback: BlobCallback,
    type?: string,
  ) {
    const blob = type === 'image/jpeg' ? (jpeg ?? null) : (webp ?? null)
    callback(blob)
  }) as unknown as typeof HTMLCanvasElement.prototype.toBlob
  return { drawImage }
}

function pngFile(sizeBytes: number, name = 'photo.png'): File {
  const file = new File([new Uint8Array([1, 2, 3])], name, { type: 'image/png' })
  Object.defineProperty(file, 'size', { value: sizeBytes })
  return file
}

afterEach(() => {
  vi.unstubAllGlobals()
})

describe('compressRichTextImage (spec 0128, 413 follow-up)', () => {
  it('bypasses GIF entirely (keeps animation)', async () => {
    const createImageBitmapSpy = vi.fn()
    vi.stubGlobal('createImageBitmap', createImageBitmapSpy)
    const original = new File([new Uint8Array([1, 2, 3])], 'anim.gif', { type: 'image/gif' })

    const result = await compressRichTextImage(original)

    expect(result).toBe(original)
    expect(createImageBitmapSpy).not.toHaveBeenCalled()
  })

  it('downscales a large image and encodes it as webp', async () => {
    const bitmap = stubBitmap(4000, 3000)
    const original = pngFile(2 * 1024 * 1024)
    const webpBlob = new Blob([new Uint8Array(1024)], { type: 'image/webp' })
    const { drawImage } = stubCanvas({ webp: webpBlob })

    const result = await compressRichTextImage(original)

    expect(result.type).toBe('image/webp')
    expect(result.name).toBe('photo.webp')
    expect(result.size).toBe(webpBlob.size)
    expect(bitmap.close).toHaveBeenCalled()
    // Longest side (4000) scaled down to the cap; aspect ratio preserved.
    expect(drawImage).toHaveBeenCalledWith(
      bitmap,
      0,
      0,
      RICH_TEXT_IMAGE_MAX_DIMENSION,
      Math.round((3000 * RICH_TEXT_IMAGE_MAX_DIMENSION) / 4000),
    )
  })

  it('falls back to jpeg when the canvas cannot actually encode webp', async () => {
    stubBitmap(800, 600)
    const original = pngFile(2 * 1024 * 1024)
    // Browsers that don't support webp encoding silently return a png blob
    // instead of honoring the requested type — simulated here.
    const silentPngFallback = new Blob([new Uint8Array(1024)], { type: 'image/png' })
    const jpegBlob = new Blob([new Uint8Array(1024)], { type: 'image/jpeg' })
    stubCanvas({ webp: silentPngFallback, jpeg: jpegBlob })

    const result = await compressRichTextImage(original)

    expect(result.type).toBe('image/jpeg')
    expect(result.name).toBe('photo.jpg')
  })

  it('keeps the original file when the compressed result would be larger', async () => {
    stubBitmap(800, 600)
    const original = pngFile(500)
    const biggerBlob = new Blob([new Uint8Array(600)], { type: 'image/webp' })
    stubCanvas({ webp: biggerBlob })

    const result = await compressRichTextImage(original)

    expect(result).toBe(original)
  })

  it('falls back to the original file when the canvas context is unavailable', async () => {
    stubBitmap(800, 600)
    const original = pngFile(2 * 1024 * 1024)
    HTMLCanvasElement.prototype.getContext = vi.fn(() => null) as unknown as typeof HTMLCanvasElement.prototype.getContext

    const result = await compressRichTextImage(original)

    expect(result).toBe(original)
  })

  it('falls back to the original file when createImageBitmap throws', async () => {
    vi.stubGlobal('createImageBitmap', vi.fn().mockRejectedValue(new Error('unsupported')))
    const original = pngFile(2 * 1024 * 1024)

    const result = await compressRichTextImage(original)

    expect(result).toBe(original)
  })
})
