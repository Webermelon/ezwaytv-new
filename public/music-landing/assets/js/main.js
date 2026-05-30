/* ============================================================
   eZWay Music — Main JS
   PerformersResource.com
   ============================================================ */

(function () {
  "use strict";

  /* ── Navbar scroll behavior ─────────────────────────────── */
  const nav = document.getElementById("ez-nav");
  if (nav) {
    const onScroll = () => {
      nav.classList.toggle("scrolled", window.scrollY > 40);
    };
    window.addEventListener("scroll", onScroll, { passive: true });
    onScroll();
  }

  /* ── Mobile menu toggle ──────────────────────────────────── */
  const toggle = document.getElementById("ez-nav-toggle");
  const navLinks = document.getElementById("ez-nav-links");

  if (toggle && navLinks) {
    toggle.addEventListener("click", () => {
      const isOpen = navLinks.classList.toggle("open");
      toggle.setAttribute("aria-expanded", String(isOpen));
    });

    // Close menu when a link is clicked
    navLinks.querySelectorAll("a").forEach((link) => {
      link.addEventListener("click", () => {
        navLinks.classList.remove("open");
        toggle.setAttribute("aria-expanded", "false");
      });
    });
  }

  /* ── Scroll Reveal ───────────────────────────────────────── */
  const revealObserver = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add("is-visible");
          revealObserver.unobserve(entry.target);
        }
      });
    },
    { threshold: 0.12, rootMargin: "0px 0px -40px 0px" },
  );

  document.querySelectorAll("[data-reveal]").forEach((el) => {
    revealObserver.observe(el);
  });

  /* ── Animated counters ───────────────────────────────────── */
  function formatCount(value, suffix) {
    if (suffix === "M+") {
      return (value / 1000000).toFixed(1) + "M+";
    }
    if (suffix === "K+") {
      return (value / 1000).toFixed(1) + "K+";
    }
    return value.toLocaleString() + (suffix || "");
  }

  function animateCounter(el) {
    const target = parseInt(el.dataset.count, 10);
    const suffix = el.dataset.suffix || "";
    const duration = 2000;
    const startTime = performance.now();

    function step(currentTime) {
      const elapsed = currentTime - startTime;
      const progress = Math.min(elapsed / duration, 1);
      // Ease out cubic
      const eased = 1 - Math.pow(1 - progress, 3);
      const current = Math.floor(eased * target);
      el.textContent = formatCount(current, suffix);
      if (progress < 1) {
        requestAnimationFrame(step);
      } else {
        el.textContent = formatCount(target, suffix);
      }
    }

    requestAnimationFrame(step);
  }

  const counterObserver = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          animateCounter(entry.target);
          counterObserver.unobserve(entry.target);
        }
      });
    },
    { threshold: 0.5 },
  );

  document.querySelectorAll("[data-count]").forEach((el) => {
    counterObserver.observe(el);
  });

  /* ── Analytics progress bars ─────────────────────────────── */
  const barObserver = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          const fill = entry.target;
          const width = fill.dataset.width;
          // Small delay so the section is visible first
          setTimeout(() => {
            fill.style.width = width + "%";
          }, 200);
          barObserver.unobserve(fill);
        }
      });
    },
    { threshold: 0.3 },
  );

  document.querySelectorAll(".ez-bar-fill").forEach((bar) => {
    barObserver.observe(bar);
  });

  /* ── Smooth scroll for anchor links ──────────────────────── */
  document.querySelectorAll('a[href^="#"]').forEach((anchor) => {
    anchor.addEventListener("click", function (e) {
      const target = document.querySelector(this.getAttribute("href"));
      if (target) {
        e.preventDefault();
        const navHeight = nav ? nav.offsetHeight : 0;
        const top =
          target.getBoundingClientRect().top + window.scrollY - navHeight - 16;
        window.scrollTo({ top, behavior: "smooth" });
      }
    });
  });

  /* ── Stagger reveal for genre cards ──────────────────────── */
  document.querySelectorAll(".ez-genre-card").forEach((card, i) => {
    card.style.transitionDelay = i * 0.08 + "s";
  });

  /* ── Stagger reveal for stat cards ───────────────────────── */
  document.querySelectorAll(".ez-stat-card").forEach((card, i) => {
    card.style.transitionDelay = i * 0.1 + "s";
  });
})();
