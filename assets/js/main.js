// Mobile nav toggle
const navToggle = document.getElementById('navToggle');
const mainNav = document.getElementById('mainNav');

if (navToggle && mainNav) {
  navToggle.addEventListener('click', () => {
    mainNav.classList.toggle('open');
  });
}

// Dynamic year in footer
const yearSpan = document.getElementById('year');
if (yearSpan) {
  yearSpan.textContent = new Date().getFullYear();
}

const slider = document.querySelector('.testimonial-slider');
const leftArrow = document.querySelector('.testimonial-arrow.left');
const rightArrow = document.querySelector('.testimonial-arrow.right');

leftArrow.addEventListener('click', () => {
  slider.scrollBy({ left: -300, behavior: 'smooth' });
});

rightArrow.addEventListener('click', () => {
  slider.scrollBy({ left: 300, behavior: 'smooth' });
});
