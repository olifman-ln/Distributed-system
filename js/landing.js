document.addEventListener("DOMContentLoaded", function () {
    const menuToggle = document.querySelector(".menu-toggle");
    const navLinks = document.querySelector(".nav-links");
    if (menuToggle) {
        menuToggle.addEventListener("click", function () {
            navLinks.classList.toggle("active");
            this.innerHTML = navLinks.classList.contains("active")
                ? '<i class="fas fa-times"></i>'
                : '<i class="fas fa-bars"></i>';
        });
    }
    document.querySelectorAll('a[href^="#"]').forEach((anchor) => {
        anchor.addEventListener("click", function (e) {
            const href = this.getAttribute("href");
            if (href === "#") return;
            if (href.startsWith("#") && href.length > 1) {
                e.preventDefault();
                const targetId = this.getAttribute("href").substring(1);
                const targetElement = document.getElementById(targetId);
                if (targetElement) {
                    if (navLinks.classList.contains("active")) {
                        navLinks.classList.remove("active");
                        menuToggle.innerHTML = '<i class="fas fa-bars"></i>';
                    }
                    window.scrollTo({
                        top: targetElement.offsetTop - 80,
                        behavior: "smooth",
                    });
                }
            }
        });
    });
    const observerOptions = {
        threshold: 0.1,
        rootMargin: "0px 0px -50px 0px",
    };

    const observer = new IntersectionObserver(function (entries) {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add("animate-in");
            }
        });
    }, observerOptions);
    document.querySelectorAll(".feature-card, .step, .benefit").forEach((el) => {
        observer.observe(el);
    });
    const style = document.createElement("style");
    style.textContent = `
.feature-card, .step, .benefit {
 opacity: 0;
transform: translateY(20px);
transition: opacity 0.6s ease, transform 0.6s ease;
}
.feature-card.animate-in, 
.step.animate-in, 
.benefit.animate-in {
 opacity: 1;
 transform: translateY(0);
        }
        .feature-card:nth-child(1) { transition-delay: 0.1s; }
        .feature-card:nth-child(2) { transition-delay: 0.2s; }
        .feature-card:nth-child(3) { transition-delay: 0.3s; }
        .feature-card:nth-child(4) { transition-delay: 0.4s; }
        .feature-card:nth-child(5) { transition-delay: 0.5s; }
        .feature-card:nth-child(6) { transition-delay: 0.6s; }
    `;
    document.head.appendChild(style);
    // Add scroll effect to navbar
    let lastScroll = 0;
    const navbar = document.querySelector(".navbar");
    window.addEventListener("scroll", function () {
        const currentScroll = window.pageYOffset;
        if (currentScroll <= 0) {
            navbar.classList.remove("scroll-up");
            return;
        }
        if (
            currentScroll > lastScroll &&
            !navbar.classList.contains("scroll-down")
        ) {
            // Scroll Down
            navbar.classList.remove("scroll-up");
            navbar.classList.add("scroll-down");
        } else if (
            currentScroll < lastScroll &&
            navbar.classList.contains("scroll-down")
        ) {
            navbar.classList.remove("scroll-down");
            navbar.classList.add("scroll-up");
        }
        lastScroll = currentScroll;
    });
    // Add shadow to navbar on scroll
    window.addEventListener("scroll", function () {
        if (window.scrollY > 10) {
            navbar.style.boxShadow = "0 4px 12px rgba(0, 0, 0, 0.1)";
        } else {
            navbar.style.boxShadow = "0 4px 6px -1px rgba(0, 0, 0, 0.1)";
        }
    });
    // System stats counter animation (optional)
    const stats = document.querySelectorAll(".stat h3");
    stats.forEach((stat) => {
        const target = parseInt(stat.textContent);
        let current = 0;
        const increment = target / 50;
        const timer = setInterval(() => {
            current += increment;
            if (current >= target) {
                current = target;
                clearInterval(timer);
            }
            stat.textContent =
                Math.floor(current) + (stat.textContent.includes("%") ? "%" : "");
        }, 30);
    });
    // Update copyright year
    const yearSpan = document.querySelector("footer .footer-bottom p");
    if (yearSpan) {
        const currentYear = new Date().getFullYear();
        yearSpan.innerHTML = yearSpan.innerHTML.replace("2024", currentYear);
    }
});
