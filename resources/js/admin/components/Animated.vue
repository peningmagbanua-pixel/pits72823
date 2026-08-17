<template>
    <canvas ref="canvas" class="animated"></canvas>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';

const canvas = ref(null);
let ctx = null;
let stars = [];
let animationId = null;
const numStars = 150;

const createStars = () => {
    stars = [];
    const width = canvas.value.width;
    const height = canvas.value.height;
    
    for (let i = 0; i < numStars; i++) {
        stars.push({
            x: Math.random() * width,
            y: Math.random() * height,
            radius: Math.random() * 0.8 + 0.3,
            vx: (Math.random() - 0.5) * 0.3,
            vy: (Math.random() - 0.5) * 0.3,
            opacity: Math.random() * 0.5 + 0.3,
            twinkleSpeed: Math.random() * 0.02 + 0.01
        });
    }
};

const drawStar = (star) => {
    ctx.beginPath();
    ctx.arc(star.x, star.y, star.radius, 0, Math.PI * 2);
    ctx.fillStyle = `gray`;
    ctx.fill();
};

const updateStar = (star) => {
    star.x += star.vx;
    star.y += star.vy;

    star.opacity += star.twinkleSpeed;
    if (star.opacity > 1 || star.opacity < 0.3) {
        star.twinkleSpeed *= -1;
    }

    if (star.x < 0) star.x = canvas.value.width;
    if (star.x > canvas.value.width) star.x = 0;
    if (star.y < 0) star.y = canvas.value.height;
    if (star.y > canvas.value.height) star.y = 0;
};

const animate = () => {
    ctx.clearRect(0, 0, canvas.value.width, canvas.value.height);

    stars.forEach(star => {
        updateStar(star);
        drawStar(star);
    });

    animationId = requestAnimationFrame(animate);
};

const resize = () => {
    canvas.value.width = window.innerWidth;
    canvas.value.height = window.innerHeight;
    createStars();
};

onMounted(() => {
    ctx = canvas.value.getContext('2d');
    resize();
    animate();
    window.addEventListener('resize', resize);
});

onUnmounted(() => {
    window.removeEventListener('resize', resize);
    if (animationId) {
        cancelAnimationFrame(animationId);
    }
});
</script>

<style scoped>
.animated {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: -1;
    pointer-events: none;
}
</style>
