document.addEventListener("DOMContentLoaded", function() {
    initCountdowns();
    initTabs();
});

/**
 * Initializes and runs real-time countdown timers for all active investments.
 */
function initCountdowns() {
    const rows = document.querySelectorAll(".active-investment-row");
    if (rows.length === 0) return;

    function updateTimers() {
        const now = Math.floor(Date.now() / 1000); // Current client time in seconds

        rows.forEach(row => {
            const startTs = parseInt(row.getAttribute("data-start"));
            const endTs = parseInt(row.getAttribute("data-end"));
            const countdownText = row.querySelector(".countdown-text");
            const progressBar = row.querySelector(".progress-bar");

            const total = endTs - startTs;
            const remaining = endTs - now;
            const elapsed = now - startTs;

            if (remaining > 0) {
                // Calculate percentage
                let pct = 0;
                if (total > 0) {
                    pct = Math.min(100, Math.max(0, (elapsed / total) * 100));
                }
                
                // Update Progress Bar
                if (progressBar) {
                    progressBar.style.width = pct + "%";
                }

                // Format and update text
                if (countdownText) {
                    countdownText.textContent = formatRemainingTime(remaining);
                }
            } else {
                // Investment has finished
                if (progressBar) {
                    progressBar.style.width = "100%";
                    progressBar.style.background = "var(--success)";
                }
                if (countdownText) {
                    countdownText.textContent = "Terminé (Actualiser)";
                    countdownText.style.color = "var(--success)";
                }
            }
        });
    }

    // Run immediately and then every second
    updateTimers();
    setInterval(updateTimers, 1000);
}

/**
 * Format remaining seconds into a readable string (e.g. 2j 04h 12m 45s)
 */
function formatRemainingTime(seconds) {
    if (seconds <= 0) return "Terminé";

    const days = Math.floor(seconds / (3600 * 24));
    const hours = Math.floor((seconds % (3600 * 24)) / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const secs = Math.floor(seconds % 60);

    let result = [];
    if (days > 0) {
        result.push(days + "j");
    }
    if (hours > 0 || days > 0) {
        result.push(String(hours).padStart(2, '0') + "h");
    }
    result.push(String(minutes).padStart(2, '0') + "m");
    result.push(String(secs).padStart(2, '0') + "s");

    return result.join(" ");
}

/**
 * Switch tabs dynamically on desktop and mobile bottom bars
 */
function initTabs() {
    const tabLinks = document.querySelectorAll(".tab-link, .tab-btn");
    const tabContents = document.querySelectorAll(".tab-content");

    tabLinks.forEach(link => {
        link.addEventListener("click", function() {
            const targetTab = this.getAttribute("data-tab");

            // Remove active class from all links and contents
            tabLinks.forEach(l => l.classList.remove("active"));
            tabContents.forEach(c => c.classList.remove("active"));

            // Add active class to all elements associated with this target tab
            document.querySelectorAll(`[data-tab="${targetTab}"]`).forEach(l => l.classList.add("active"));
            
            const activeContent = document.getElementById(targetTab);
            if (activeContent) {
                activeContent.classList.add("active");
            }

            // Sync hash in URL
            window.location.hash = targetTab;
        });
    });

    // Check if hash exists in URL on load
    const hash = window.location.hash;
    if (hash) {
        const cleanHash = hash.replace("#", "");
        const targetBtn = document.querySelector(`[data-tab="${cleanHash}"]`);
        if (targetBtn) {
            targetBtn.click();
        }
    }
}
