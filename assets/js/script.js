// assets/js/script.js
// Scroll Reveal Animation
const reveals = document.querySelectorAll('.reveal');

const observer = new IntersectionObserver(entries => {
  entries.forEach((entry, index) => {
    if (entry.isIntersecting) {
      setTimeout(() => {
        entry.target.classList.add('visible');
      }, index * 60);
      observer.unobserve(entry.target);
    }
  });
}, { threshold: 0.08 });

reveals.forEach(el => observer.observe(el));

// Active nav highlight on scroll
const sections = document.querySelectorAll('section[id], div[id]');
const navLinks = document.querySelectorAll('nav a');

window.addEventListener('scroll', () => {
  let current = '';
  sections.forEach(section => {
    const sectionTop = section.offsetTop;
    const sectionHeight = section.clientHeight;
    if (window.scrollY >= sectionTop - 100) {
      current = section.getAttribute('id');
    }
  });
  
  navLinks.forEach(link => {
    const href = link.getAttribute('href');
    if (href && href === '#' + current) {
      link.style.color = 'var(--purple-l)';
    } else if (link.style.color) {
      link.style.color = '';
    }
  });
});

// Smooth animation for score bars
document.addEventListener('DOMContentLoaded', () => {
  const scoreFills = document.querySelectorAll('.score-fill');
  scoreFills.forEach(fill => {
    const width = fill.style.width;
    if (width) {
      fill.style.width = '0%';
      setTimeout(() => {
        fill.style.width = width;
      }, 100);
    }
  });
});

// Form input effects
const inputs = document.querySelectorAll('.field-input');
inputs.forEach(input => {
  input.addEventListener('focus', function() {
    this.parentElement.style.transform = 'translateY(-1px)';
  });
  input.addEventListener('blur', function() {
    this.parentElement.style.transform = '';
  });
});

// Button click feedback
const buttons = document.querySelectorAll('.btn');
buttons.forEach(btn => {
  btn.addEventListener('click', function(e) {
    if (this.type !== 'submit') {
      this.style.transform = 'scale(0.97)';
      setTimeout(() => {
        this.style.transform = '';
      }, 150);
    }
  });
});

// Auto-dismiss alerts after 5 seconds
const alerts = document.querySelectorAll('.alert, .error');
alerts.forEach(alert => {
  setTimeout(() => {
    alert.style.opacity = '0';
    setTimeout(() => {
      alert.remove();
    }, 300);
  }, 5000);
});