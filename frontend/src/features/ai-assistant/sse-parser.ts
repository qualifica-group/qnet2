/** A raw SSE frame: `event:` name (default "message") and the joined `data:` lines. */
export interface SseFrame {
  event: string
  data: string
}

export interface SseParser {
  /** Feeds a decoded text chunk; returns the frames completed by it. */
  push: (chunk: string) => SseFrame[]
  /** Flushes a trailing frame left without the final blank line. */
  end: () => SseFrame[]
}

const DEFAULT_EVENT = 'message'

function parseFrame(block: string): SseFrame | null {
  let event = DEFAULT_EVENT
  const dataLines: string[] = []

  for (const line of block.split('\n')) {
    if (line === '' || line.startsWith(':')) {
      continue
    }
    const separator = line.indexOf(':')
    const field = separator === -1 ? line : line.slice(0, separator)
    let value = separator === -1 ? '' : line.slice(separator + 1)
    if (value.startsWith(' ')) {
      value = value.slice(1)
    }
    if (field === 'event') {
      event = value
    } else if (field === 'data') {
      dataLines.push(value)
    }
  }

  return dataLines.length === 0 ? null : { event, data: dataLines.join('\n') }
}

/**
 * Incremental SSE parser: chunks may split a frame (or a CRLF pair) anywhere,
 * so text is buffered until a blank line closes the frame.
 */
export function createSseParser(): SseParser {
  let buffer = ''

  const drain = (flush: boolean): SseFrame[] => {
    const normalized = buffer.replace(/\r\n/g, '\n').replace(/\r(?!$)/g, '\n')
    const parts = normalized.split('\n\n')
    const remainder = flush ? '' : (parts.pop() ?? '')
    buffer = remainder
    const frames: SseFrame[] = []
    for (const part of parts) {
      const frame = parseFrame(part)
      if (frame) {
        frames.push(frame)
      }
    }
    return frames
  }

  return {
    push(chunk) {
      buffer += chunk
      return drain(false)
    },
    end() {
      return drain(true)
    },
  }
}
