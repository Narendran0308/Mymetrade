    // ✨ NAVBAR ACTIVE LINK HIGHLIGHTING ON SCROLL
    window.addEventListener("scroll", function () {
        const timeline = document.querySelector(".timeline");
        const progress = document.querySelector(".progress");

        if (!timeline || !progress) {
            updateActiveNavLink();
            return;
        }

        const rect = timeline.getBoundingClientRect();
        const windowHeight = window.innerHeight;

        let progressHeight = ((windowHeight - rect.top) / rect.height) * 100;

        if (progressHeight < 0) progressHeight = 0;
        if (progressHeight > 100) progressHeight = 100;

        progress.style.height = progressHeight + "%";

        // Update active navbar link on scroll
        updateActiveNavLink();
    });

    function updateActiveNavLink() {
        const scrollPosition = window.scrollY + 150; // Offset for navbar
        let activeLink = null;

        // Get all nav links
        const navLinks = document.querySelectorAll(".nav-links a:not(.join-btn)");
        
        // Reset all active classes
        navLinks.forEach(link => {
            link.classList.remove("active");
        });

        // Check each section
        const sections = [
            { id: "benefits", href: "#benefits" },
            { id: "process", href: "#process" },
            { id: "pricing", href: "#pricing" },
            { id: "testimonials", href: "#testimonials" },
            { id: "faq", href: "#faq" },
            { id: "contact-section", href: "#contact-section" }
        ];

        for (let section of sections) {
            const element = document.getElementById(section.id);
            if (element) {
                const elementTop = element.offsetTop;
                const elementBottom = elementTop + element.offsetHeight;

                if (scrollPosition >= elementTop && scrollPosition < elementBottom) {
                    activeLink = document.querySelector(`.nav-links a[href="${section.href}"]`);
                    break;
                }
            }
        }

        // If no section is active, highlight Home (when at top)
        if (!activeLink && scrollPosition < 500) {
            activeLink = document.querySelector('.nav-links a[href="#"]');
        }

        // Apply active class
        if (activeLink) {
            activeLink.classList.add("active");
        }
    }

    function handleClickPricingBtn(plan) {
        sessionStorage.setItem("selectedPlan", plan);
        return true;
    }

    document.addEventListener("DOMContentLoaded", function () {
        trackWebsiteVisit();
        initContactForm();
        initLoginPopup();

        // ✨ SCROLL FADE-IN ANIMATION WITH INTERSECTION OBSERVER
        const observerOptions = {
            threshold: 0.1,
            rootMargin: "0px 0px -50px 0px"
        };

        const observer = new IntersectionObserver(function(entries) {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add("visible");
                    observer.unobserve(entry.target);
                }
            });
        }, observerOptions);

        // Add scroll-fade class to sections that can safely animate in.
        // Benefit cards stay visible immediately so their images never disappear after load.
        const cardsAndSections = document.querySelectorAll(".card1, .process-section, .testimonials-section, .faq-section");
        cardsAndSections.forEach(el => {
            el.classList.add("scroll-fade");

            const rect = el.getBoundingClientRect();
            const isAlreadyVisible = rect.top < window.innerHeight && rect.bottom > 0;

            if (isAlreadyVisible) {
                el.classList.add("visible");
            } else {
                observer.observe(el);
            }
        });

        // Update active nav link on page load
        updateActiveNavLink();

        // FAQ section
        const faqItems = document.querySelectorAll(".faq-item");
        faqItems.forEach((item) => {
            item.addEventListener("toggle", () => {
                if (item.open) {
                    faqItems.forEach((otherItem) => {
                        if (otherItem !== item) {
                            otherItem.open = false;
                        }
                    });
                }
            });
        });

        // Mobile menu functionality
        const menuBtn = document.getElementById("menu-toggle");
        const closeBtn = document.getElementById("close-menu");
        const mobileMenu = document.getElementById("mobile-menu");

        if (mobileMenu) {
            // Close menu when clicking nav links
            const navLinks = mobileMenu.querySelectorAll("a");
            navLinks.forEach(link => {
                link.addEventListener("click", () => {
                    mobileMenu.classList.remove("active");
                });
            });

            // Open menu
            if (menuBtn) {
                menuBtn.addEventListener("click", () => {
                    mobileMenu.classList.add("active");
                });
            }

            // Close menu
            if (closeBtn) {
                closeBtn.addEventListener("click", () => {
                    mobileMenu.classList.remove("active");
                });
            }

            // Reset when resizing to desktop
            window.addEventListener("resize", () => {
                if (window.innerWidth > 768) {
                    mobileMenu.classList.remove("active");
                }
            });
        }
    });

    function getVisitSessionKey() {
        let key = localStorage.getItem("mymetrades_visit_session");

        if (!key) {
            key = `visit_${Date.now()}_${Math.random().toString(36).slice(2)}`;
            localStorage.setItem("mymetrades_visit_session", key);
        }

        return key;
    }

    function trackWebsiteVisit() {
        const payload = {
            session_key: getVisitSessionKey(),
            page_url: window.location.href,
            page_title: document.title,
            referrer: document.referrer
        };

        const body = JSON.stringify(payload);

        if (navigator.sendBeacon) {
            navigator.sendBeacon("backend/track_visit.php", new Blob([body], { type: "application/json" }));
            return;
        }

        fetch("backend/track_visit.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body,
            keepalive: true
        }).catch(() => {});
    }

    function initContactForm() {
        const form = document.getElementById("contact-form");
        const messageBox = document.getElementById("contact-form-message");

        if (!form || !messageBox) {
            return;
        }

        form.addEventListener("submit", async (event) => {
            event.preventDefault();

            const submitButton = form.querySelector(".submit-btn");
            const payload = {
                name: form.querySelector('[name="name"]').value.trim(),
                email: form.querySelector('[name="email"]').value.trim(),
                message: form.querySelector('[name="message"]').value.trim()
            };

            submitButton.disabled = true;
            messageBox.className = "form-message";
            messageBox.textContent = "Submitting...";

            try {
                const response = await fetch("backend/contact.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify(payload)
                });
                const result = await response.json();

                messageBox.textContent = result.message || "Something went wrong.";
                messageBox.classList.add(result.success ? "success" : "error");

                if (result.success) {
                    form.reset();
                }
            } catch (error) {
                messageBox.textContent = "Unable to submit your message right now.";
                messageBox.classList.add("error");
            } finally {
                submitButton.disabled = false;
            }
        });
    }

    function initLoginPopup() {
        const popup = document.getElementById("login-popup");

        if (!popup || sessionStorage.getItem("showLoginPopup") !== "true") {
            return;
        }

        const closeButton = document.getElementById("login-popup-close");
        const continueButton = document.getElementById("login-popup-continue");
        const planButton = popup.querySelector(".login-popup-primary");

        const closePopup = () => {
            popup.classList.remove("show");
            popup.setAttribute("aria-hidden", "true");
            document.body.classList.remove("login-popup-open");
            sessionStorage.removeItem("showLoginPopup");
        };

        popup.classList.add("show");
        popup.setAttribute("aria-hidden", "false");
        document.body.classList.add("login-popup-open");

        closeButton?.addEventListener("click", closePopup);
        continueButton?.addEventListener("click", closePopup);
        planButton?.addEventListener("click", closePopup);

        popup.addEventListener("click", (event) => {
            if (event.target === popup) {
                closePopup();
            }
        });

        document.addEventListener("keydown", (event) => {
            if (event.key === "Escape" && popup.classList.contains("show")) {
                closePopup();
            }
        });
    }
