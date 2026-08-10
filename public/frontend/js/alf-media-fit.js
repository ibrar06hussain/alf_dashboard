/**
 * Fit uploaded images into fixed design slots:
 * - Larger than slot → crop (cover)
 * - Smaller than slot → stretch to fill (100% 100% / object-fit: fill)
 */
(function () {
    function extractUrl(bg) {
        if (!bg || bg === 'none') return null;
        var m = bg.match(/url\(["']?(.*?)["']?\)/i);
        return m ? m[1] : null;
    }

    function applyBg(el, mode) {
        el.style.backgroundSize = mode === 'stretch' ? '100% 100%' : 'cover';
        el.style.backgroundPosition = 'center center';
        el.style.backgroundRepeat = 'no-repeat';
        el.classList.add(mode === 'stretch' ? 'is-stretched' : 'is-cropped');
    }

    function applyImg(el, mode) {
        el.style.objectFit = mode === 'stretch' ? 'fill' : 'cover';
        el.style.objectPosition = 'center center';
        el.classList.add(mode === 'stretch' ? 'is-stretched' : 'is-cropped');
    }

    function decide(naturalW, naturalH, slotW, slotH) {
        if (!slotW || !slotH) return 'cover';
        if (naturalW < slotW || naturalH < slotH) return 'stretch';
        return 'cover';
    }

    function fitElement(el) {
        var type = el.getAttribute('data-alf-fit') || 'bg';
        var slotW = parseInt(el.getAttribute('data-alf-w') || '0', 10);
        var slotH = parseInt(el.getAttribute('data-alf-h') || '0', 10);

        if (type === 'img') {
            if (el.complete && el.naturalWidth) {
                applyImg(el, decide(el.naturalWidth, el.naturalHeight, slotW, slotH));
                return;
            }
            el.addEventListener('load', function () {
                applyImg(el, decide(el.naturalWidth, el.naturalHeight, slotW, slotH));
            }, { once: true });
            return;
        }

        var url = el.getAttribute('data-alf-src') || extractUrl(el.style.backgroundImage || window.getComputedStyle(el).backgroundImage);
        if (!url) {
            applyBg(el, 'cover');
            return;
        }

        var probe = new Image();
        probe.onload = function () {
            applyBg(el, decide(probe.naturalWidth, probe.naturalHeight, slotW, slotH));
        };
        probe.onerror = function () {
            applyBg(el, 'cover');
        };
        probe.src = url;
    }

    function run() {
        document.querySelectorAll('[data-alf-fit]').forEach(fitElement);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', run);
    } else {
        run();
    }

    window.AlfMediaFit = { refresh: run };
})();
