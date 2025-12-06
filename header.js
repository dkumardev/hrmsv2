// header.js
document.addEventListener('DOMContentLoaded', () => {
    const toggle = document.getElementById('mobileToggle');
    const nav = document.getElementById('mainNav');

    if (!toggle || !nav) {
        return;
    }

    // Toggle mobile nav open/close
    toggle.addEventListener('click', () => {
        nav.classList.toggle('active');
    });

    // Optional: close nav when a link is clicked (better UX on mobile)
    nav.addEventListener('click', (e) => {
        const target = e.target;
        if (target && target.classList.contains('nav-link')) {
            nav.classList.remove('active');
        }
    });

    // Optional: ensure nav is reset when resizing from mobile to desktop
    window.addEventListener('resize', () => {
        if (window.innerWidth > 768 && nav.classList.contains('active')) {
            nav.classList.remove('active');
        }
    });
});
