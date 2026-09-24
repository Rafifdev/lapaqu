/**
 * Web Audio API Sound Synthesizer Service
 * Memberikan efek suara audio berkualitas tinggi tanpa dependensi file eksternal.
 */

class SoundService {
  public ctx: AudioContext | null = null

  constructor() {
    if (typeof window !== 'undefined') {
      const unlockAudio = () => {
        if (this.ctx && this.ctx.state === 'suspended') {
          this.ctx.resume().catch(() => {})
        }
      }
      window.addEventListener('click', unlockAudio, { passive: true })
      window.addEventListener('keydown', unlockAudio, { passive: true })
      window.addEventListener('touchstart', unlockAudio, { passive: true })
    }
  }

  private getAudioContext(): AudioContext | null {
    if (typeof window === 'undefined') return null
    if (!this.ctx) {
      const AudioCtx = window.AudioContext || (window as any).webkitAudioContext
      if (AudioCtx) {
        this.ctx = new AudioCtx()
      }
    }
    return this.ctx
  }

  private isEnabled(key: string, defaultVal = true): boolean {
    try {
      const saved = localStorage.getItem('lapaqu_notif_settings')
      if (saved) {
        const parsed = JSON.parse(saved)
        if (parsed[key] !== undefined) return Boolean(parsed[key])
      }
    } catch {
      // fallback
    }
    return defaultVal
  }

  /**
   * Suara Pesanan Masuk (Melodic Ascending Chime: C5 -> E5 -> G5)
   */
  public playOrderChime(force = false): void {
    if (!force && !this.isEnabled('notifyOrderSound')) return

    const ctx = this.getAudioContext()
    if (!ctx) return

    const playNotes = () => {
      const notes = [523.25, 659.25, 783.99] // C5, E5, G5
      const now = ctx.currentTime

      notes.forEach((freq, idx) => {
        const startTime = now + idx * 0.12
        const osc = ctx.createOscillator()
        const gain = ctx.createGain()

        osc.type = 'sine'
        osc.frequency.setValueAtTime(freq, startTime)

        // Lembut & jernih: fade in cepat lalu exponential decay
        gain.gain.setValueAtTime(0.001, startTime)
        gain.gain.linearRampToValueAtTime(0.25, startTime + 0.02)
        gain.gain.exponentialRampToValueAtTime(0.001, startTime + 0.45)

        osc.connect(gain)
        gain.connect(ctx.destination)

        osc.start(startTime)
        osc.stop(startTime + 0.46)
      })
    }

    if (ctx.state === 'suspended') {
      ctx.resume().then(playNotes).catch(() => {})
    } else {
      playNotes()
    }
  }

  /**
   * Suara Lonceng Meja / Panggilan Waiter (High-pitched dual bell strike: 1800Hz)
   */
  public playWaiterBell(force = false): void {
    if (!force && !this.isEnabled('notifyWaiterBell')) return

    const ctx = this.getAudioContext()
    if (!ctx) return

    const playNotes = () => {
      const strikes = [0, 0.18] // Dua dentang lonceng
      const now = ctx.currentTime

      strikes.forEach(delay => {
        const startTime = now + delay

        // Dual oscillator untuk resonansi metalik meja restoran
        const osc1 = ctx.createOscillator()
        const osc2 = ctx.createOscillator()
        const gain = ctx.createGain()

        osc1.type = 'triangle'
        osc1.frequency.setValueAtTime(1760, startTime) // A6

        osc2.type = 'sine'
        osc2.frequency.setValueAtTime(1766, startTime) // Sedikit detuning untuk resonansi lonceng

        gain.gain.setValueAtTime(0.001, startTime)
        gain.gain.linearRampToValueAtTime(0.2, startTime + 0.005)
        gain.gain.exponentialRampToValueAtTime(0.0005, startTime + 0.6)

        osc1.connect(gain)
        osc2.connect(gain)
        gain.connect(ctx.destination)

        osc1.start(startTime)
        osc2.start(startTime)
        osc1.stop(startTime + 0.62)
        osc2.stop(startTime + 0.62)
      })
    }

    if (ctx.state === 'suspended') {
      ctx.resume().then(playNotes).catch(() => {})
    } else {
      playNotes()
    }
  }

  /**
   * Suara Peringatan Void / Pembatalan Pesanan (Descending Two-Tone Warning)
   */
  public playVoidAlert(force = false): void {
    if (!force && !this.isEnabled('notifyKdsVoid')) return

    const ctx = this.getAudioContext()
    if (!ctx) return

    const playNotes = () => {
      const notes = [440, 311.13] // A4 -> Eb4 (tritone / alert tone)
      const now = ctx.currentTime

      notes.forEach((freq, idx) => {
        const startTime = now + idx * 0.14
        const osc = ctx.createOscillator()
        const gain = ctx.createGain()

        osc.type = 'sawtooth'
        osc.frequency.setValueAtTime(freq, startTime)

        gain.gain.setValueAtTime(0.001, startTime)
        gain.gain.linearRampToValueAtTime(0.15, startTime + 0.02)
        gain.gain.exponentialRampToValueAtTime(0.001, startTime + 0.3)

        osc.connect(gain)
        gain.connect(ctx.destination)

        osc.start(startTime)
        osc.stop(startTime + 0.32)
      })
    }

    if (ctx.state === 'suspended') {
      ctx.resume().then(playNotes).catch(() => {})
    } else {
      playNotes()
    }
  }
}

export const soundService = new SoundService()
export default soundService
