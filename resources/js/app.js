import './bootstrap';

document.addEventListener("DOMContentLoaded", () => {
    const reveals = document.querySelectorAll(".reveal");
    const header = document.querySelector(".site-header");
    const navLinks = Array.from(document.querySelectorAll(".nav-links a[data-section]"));
    const sections = navLinks
        .map(link => document.getElementById(link.dataset.section))
        .filter(Boolean);

    // ── Scroll reveal ─────────────────────────────────────────────
    const revealObserver = new IntersectionObserver(
        entries => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add("is-visible");
                    revealObserver.unobserve(entry.target);
                }
            });
        },
        { threshold: 0.16 }
    );

    reveals.forEach(el => revealObserver.observe(el));

    // ── Active nav link tracking ──────────────────────────────────
    const syncActive = () => {
        const headerH = header ? header.getBoundingClientRect().height : 78;
        const scrollPos = window.scrollY + headerH + 8;
        let activeId = null;

        sections.forEach(section => {
            if (scrollPos >= section.offsetTop) {
                activeId = section.id;
            }
        });

        navLinks.forEach(link => {
            link.classList.toggle("active", link.dataset.section === activeId);
        });
    };

    // ── Sticky header shrink on scroll ───────────────────────────
    window.addEventListener("scroll", () => {
        if (header) {
            header.classList.toggle("shrink", window.scrollY > 40);
        }
        syncActive();
    });

    syncActive();

    // ── Hamburger menu toggle ─────────────────────────────────────
    const navToggle = document.querySelector(".nav-toggle");
    const navMenu = document.getElementById("nav-links");

    if (navToggle && navMenu) {
        navToggle.addEventListener("click", () => {
            const isOpen = navMenu.classList.toggle("is-open");
            navToggle.setAttribute("aria-expanded", isOpen ? "true" : "false");
            navToggle.setAttribute("aria-label", isOpen ? "Cerrar menú" : "Abrir menú");
        });

        // Close menu when a nav link is clicked
        navMenu.querySelectorAll("a").forEach(link => {
            link.addEventListener("click", () => {
                navMenu.classList.remove("is-open");
                navToggle.setAttribute("aria-expanded", "false");
                navToggle.setAttribute("aria-label", "Abrir menú");
            });
        });

        // Close menu when clicking outside
        document.addEventListener("click", e => {
            if (!header.contains(e.target) && navMenu.classList.contains("is-open")) {
                navMenu.classList.remove("is-open");
                navToggle.setAttribute("aria-expanded", "false");
                navToggle.setAttribute("aria-label", "Abrir menú");
            }
        });
    }
});
