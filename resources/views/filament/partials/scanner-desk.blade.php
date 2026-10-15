{{--
    The behaviour of a desk where a USB scanner types the digits of a card
    and presses Enter (the attendance desk, the sales desk of a market):
    the box keeps the cursor, and each answer is told by a sound too.
    Used as the x-data of the desk: it needs an input with x-ref="code".
--}}
{
    audio: null,
    focusBox() {
        this.$refs.code.focus()
    },
    keepFocus(event) {
        // The scanner types digits. If the box lost the cursor (a
        // click elsewhere on the page), the first digit brings it back.
        const active = document.activeElement
        const typing = active && ['INPUT', 'TEXTAREA', 'SELECT'].includes(active.tagName)

        if (! typing && /^[0-9]$/.test(event.key)) {
            this.focusBox()
        }
    },
    beep(tone) {
        try {
            this.audio ??= new (window.AudioContext || window.webkitAudioContext)()

            const pitch = { success: 880, warning: 520, danger: 220 }[tone] ?? 440
            const length = tone === 'danger' ? 0.45 : 0.14
            const oscillator = this.audio.createOscillator()
            const volume = this.audio.createGain()

            oscillator.frequency.value = pitch
            volume.gain.value = 0.2
            oscillator.connect(volume)
            volume.connect(this.audio.destination)
            oscillator.start()
            oscillator.stop(this.audio.currentTime + length)
        } catch (error) {
            // No sound device: the coloured answer is still shown.
        }
    },
}
