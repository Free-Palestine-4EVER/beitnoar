import { onMounted, onUnmounted, watch } from 'vue';

export function useSpinner(hostRef, elRef, hintRef) {
    let raf = null;
    let cleanup = null;

    const init = () => {
        if (!hostRef.value || !elRef.value) return null;

        const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        let ang = 0;
        let vel = reduce ? 0 : 0.35;
        let drag = false;
        let lastX = 0;
        let lastT = 0;
        const base = reduce ? 0 : 0.35;

        const tick = () => {
            ang = (ang + vel) % 360;
            if (elRef.value) {
                elRef.value.style.transform = `rotate(${ang}deg)`;
            }
            if (!drag) {
                if (Math.abs(vel) > base) vel *= 0.96;
                else vel += (base - vel) * 0.05;
            }
            raf = requestAnimationFrame(tick);
        };

        tick();

        const onPointerDown = (e) => {
            drag = true;
            lastX = e.clientX;
            lastT = performance.now();
            try {
                hostRef.value.setPointerCapture(e.pointerId);
            } catch (err) {}
            if (hintRef && hintRef.value) {
                hintRef.value.classList.add('off');
            }
        };

        const onPointerMove = (e) => {
            if (!drag) return;
            const now = performance.now();
            const dx = e.clientX - lastX;
            const dt = Math.max(now - lastT, 8);
            ang += dx * 0.55;
            vel = Math.max(-14, Math.min(14, (dx * 0.55 / dt) * 16));
            lastX = e.clientX;
            lastT = now;
        };

        const onPointerUp = () => {
            drag = false;
        };

        const host = hostRef.value;
        host.addEventListener('pointerdown', onPointerDown);
        host.addEventListener('pointermove', onPointerMove);
        host.addEventListener('pointerup', onPointerUp);
        host.addEventListener('pointercancel', onPointerUp);
        host.addEventListener('pointerleave', onPointerUp);

        return () => {
            if (raf) cancelAnimationFrame(raf);
            host.removeEventListener('pointerdown', onPointerDown);
            host.removeEventListener('pointermove', onPointerMove);
            host.removeEventListener('pointerup', onPointerUp);
            host.removeEventListener('pointercancel', onPointerUp);
            host.removeEventListener('pointerleave', onPointerUp);
        };
    };

    const start = () => {
        if (cleanup) {
            cleanup();
            cleanup = null;
        }
        cleanup = init();
    };

    onMounted(start);

    watch(hostRef, (newVal) => {
        if (newVal) {
            start();
        } else if (cleanup) {
            cleanup();
            cleanup = null;
        }
    });

    onUnmounted(() => {
        if (cleanup) cleanup();
    });
}
