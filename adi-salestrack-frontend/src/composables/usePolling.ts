import { onUnmounted, shallowReactive } from 'vue'

export function usePolling<T>(fetcher: () => Promise<T>, isDone: (data: T) => boolean, intervalMs = 4000, maxDurationMs = 10 * 60 * 1000) {
  const state = shallowReactive({
    data: null as T | null,
    polling: false,
    elapsed: 0,
  })
  let timer: ReturnType<typeof setInterval> | null = null
  const startTime = Date.now()

  async function poll() {
    state.polling = true
    try {
      const res = await fetcher()
      state.data = res
      if (isDone(res)) {
        stop()
      }
    } finally {
      state.polling = false
    }
  }

  async function start(immediate = true) {
    if (immediate) await poll()
    if (!state.data || isDone(state.data)) return
    timer = setInterval(async () => {
      state.elapsed = Date.now() - startTime
      if (state.elapsed >= maxDurationMs) {
        stop()
        return
      }
      await poll()
    }, intervalMs)
  }

  function stop() {
    if (timer) {
      clearInterval(timer)
      timer = null
    }
  }

  onUnmounted(stop)

  return { ...state, start, stop }
}
