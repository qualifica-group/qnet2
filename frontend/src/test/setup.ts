import { afterEach } from 'vitest'

// Node 24+ exposes a global Web Storage `localStorage`, backed by
// `--localstorage-file`. When that flag has no valid path (this sandbox),
// Node installs a stub object without the real Storage methods, which
// shadows jsdom's own `window.localStorage` implementation (same global in
// the jsdom test environment) — breaking any component that reads/writes it
// (e.g. `components/ui/sheet.tsx`'s persisted width). Replace it with a
// minimal in-memory Storage before any test runs.
if (typeof globalThis.localStorage?.getItem !== 'function') {
  class MemoryStorage implements Storage {
    private store = new Map<string, string>()
    get length() {
      return this.store.size
    }
    clear() {
      this.store.clear()
    }
    getItem(key: string) {
      return this.store.has(key) ? this.store.get(key)! : null
    }
    key(index: number) {
      return Array.from(this.store.keys())[index] ?? null
    }
    removeItem(key: string) {
      this.store.delete(key)
    }
    setItem(key: string, value: string) {
      this.store.set(key, String(value))
    }
  }
  Object.defineProperty(globalThis, 'localStorage', {
    value: new MemoryStorage(),
    configurable: true,
    writable: true,
  })
}

// `*.test.ts` files run in the node environment (see `test.projects` in
// vite.config.ts): the DOM matchers, stubs and cleanup below only exist, and
// are only imported, where there is a DOM.
if (typeof window !== 'undefined') {
  await import('@testing-library/jest-dom/vitest')
  const { cleanup } = await import('@testing-library/react')

  // jsdom does not implement these layout/pointer APIs that Radix primitives
  // (e.g. Select) call when opening their popups. Stub them so component tests
  // can drive the real UI instead of mocking the primitive.
  if (!Element.prototype.scrollIntoView) {
    Element.prototype.scrollIntoView = () => {}
  }
  if (!Element.prototype.hasPointerCapture) {
    Element.prototype.hasPointerCapture = () => false
  }
  if (!Element.prototype.releasePointerCapture) {
    Element.prototype.releasePointerCapture = () => {}
  }
  // Radix popper-based popups (DropdownMenu, Popover) observe their trigger/content
  // size to position themselves; jsdom ships no ResizeObserver implementation.
  if (!globalThis.ResizeObserver) {
    globalThis.ResizeObserver = class {
      observe() {}
      unobserve() {}
      disconnect() {}
    }
  }
  // The notification list watches a sentinel element to trigger infinite-scroll
  // loading; jsdom has no IntersectionObserver, so the observer never fires.
  if (!globalThis.IntersectionObserver) {
    globalThis.IntersectionObserver = class {
      readonly root = null
      readonly rootMargin = ''
      readonly scrollMargin = ''
      readonly thresholds: ReadonlyArray<number> = []
      observe() {}
      unobserve() {}
      disconnect() {}
      takeRecords(): IntersectionObserverEntry[] {
        return []
      }
    }
  }

  // Unmount React trees and reset jsdom between tests.
  afterEach(() => {
    cleanup()
  })
}
