import { ref, watch, type Ref, isRef, onBeforeUnmount } from 'vue'

export function useAnimatedNumber(
  target: Ref<number> | (() => number),
  duration = 800
) {
  const getTarget = () => {
    if (typeof target === 'function') return target()
    if (isRef(target)) return target.value
    return 0
  }

  const current = ref(0)
  let animationFrameId: number | null = null

  const animate = (from: number, to: number) => {
    if (animationFrameId) {
      cancelAnimationFrame(animationFrameId)
      animationFrameId = null
    }

    if (from === to) {
      current.value = to
      return
    }

    const startTime = performance.now()

    const step = (now: number) => {
      const elapsed = now - startTime
      const progress = Math.min(elapsed / duration, 1)

      // easeOutExpo curve: melesat cepat di awal, melambat sangat halus di akhir
      const ease = progress === 1 ? 1 : 1 - Math.pow(2, -10 * progress)
      current.value = Math.round(from + (to - from) * ease)

      if (progress < 1) {
        animationFrameId = requestAnimationFrame(step)
      } else {
        current.value = to
        animationFrameId = null
      }
    }

    animationFrameId = requestAnimationFrame(step)
  }

  watch(
    () => getTarget(),
    (newVal, oldVal) => {
      animate(oldVal ?? 0, newVal ?? 0)
    },
    { immediate: true }
  )

  onBeforeUnmount(() => {
    if (animationFrameId) {
      cancelAnimationFrame(animationFrameId)
    }
  })

  return current
}
