import { ref, watch, type Ref, isRef, onBeforeUnmount } from 'vue'
import { isReducedMotion } from './useMotion'

export function useAnimatedNumber(
  target: Ref<number> | (() => number),
  baseDuration = 800
) {
  const getTarget = (): number => {
    let val: any
    if (typeof target === 'function') {
      val = target()
    } else if (isRef(target)) {
      val = target.value
    } else {
      val = target
    }
    const num = Number(val)
    return isNaN(num) ? 0 : num
  }

  const current = ref(getTarget())
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

    const diff = Math.abs(to - from)
    // Adaptive duration: angka kecil (0-5) lebih cepat & gesit (400ms), angka besar (omset/sales) mulus 800ms
    const duration = diff <= 5 ? Math.min(baseDuration, 420) : baseDuration
    const startTime = performance.now()

    const step = (now: number) => {
      const elapsed = now - startTime
      const progress = Math.min(elapsed / duration, 1)

      // Silky smooth easeOutQuart curve
      const ease = 1 - Math.pow(1 - progress, 4)
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
    (newVal) => {
      if (newVal === current.value) return
      if (isReducedMotion.value) {
        current.value = newVal
        return
      }
      animate(current.value, newVal)
    },
    { immediate: false }
  )

  onBeforeUnmount(() => {
    if (animationFrameId) {
      cancelAnimationFrame(animationFrameId)
    }
  })

  return current
}
