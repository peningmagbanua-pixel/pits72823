<template>
    <div class="signature-pad">
        <canvas
            ref="canvas"
            class="signature-pad__canvas"
            @pointerdown="startStroke"
            @pointermove="moveStroke"
            @pointerup="endStroke"
            @pointercancel="endStroke"
            @pointerleave="endStroke"
        ></canvas>
        <div class="signature-pad__hint">
            <i class="bi bi-pen me-1"></i>
            Draw your signature using your mouse, finger, or stylus.
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';

// Fixed internal resolution; CSS scales the canvas to its container and the
// pointer coordinates are mapped back through the same ratio, so the modal
// can be opened/closed and resized without touching the bitmap.
const WIDTH  = 800;
const HEIGHT = 240;

const canvas = ref(null);
const hasInk = ref(false);

let ctx      = null;
let drawing  = false;

const point = (event) => {
    const rect = canvas.value.getBoundingClientRect();
    return {
        x: (event.clientX - rect.left) * (WIDTH / rect.width),
        y: (event.clientY - rect.top) * (HEIGHT / rect.height),
    };
};

const resetCanvas = () => {
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, WIDTH, HEIGHT);
    hasInk.value = false;
};

const startStroke = (event) => {
    if (!ctx) return;
    if (event.pointerType === 'mouse' && event.button !== 0) return;

    drawing = true;
    canvas.value.setPointerCapture(event.pointerId);

    const p = point(event);
    ctx.beginPath();
    ctx.moveTo(p.x, p.y);
    ctx.lineTo(p.x + 0.1, p.y + 0.1);
    ctx.stroke();
    hasInk.value = true;
};

const moveStroke = (event) => {
    if (!drawing || !ctx) return;

    const p = point(event);
    ctx.lineTo(p.x, p.y);
    ctx.stroke();
};

const endStroke = (event) => {
    if (!drawing) return;

    drawing = false;
    if (canvas.value.hasPointerCapture(event.pointerId)) {
        canvas.value.releasePointerCapture(event.pointerId);
    }
};

const clear = () => {
    if (ctx) resetCanvas();
};

const toDataURL = () => (hasInk.value ? canvas.value.toDataURL('image/png') : null);

onMounted(() => {
    canvas.value.width  = WIDTH;
    canvas.value.height = HEIGHT;

    ctx = canvas.value.getContext('2d');
    ctx.lineCap   = 'round';
    ctx.lineJoin  = 'round';
    ctx.strokeStyle = '#1a1a5e';
    ctx.lineWidth = 2.5;
    resetCanvas();
});

defineExpose({ clear, toDataURL, hasInk });
</script>

<style scoped>
.signature-pad__canvas {
    display: block;
    width: 100%;
    height: auto;
    border: 1.5px dashed #ced4da;
    border-radius: .5rem;
    background: #fff;
    /* Let pointer events draw instead of scrolling/zooming the page. */
    touch-action: none;
    cursor: crosshair;
}

.signature-pad__hint {
    margin-top: .5rem;
    font-size: 12px;
    color: #6c757d;
}
</style>
