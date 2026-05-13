(() => {
  const qs = (sel, root = document) => root.querySelector(sel);
  const qsa = (sel, root = document) => Array.from(root.querySelectorAll(sel));

  function openModal(modal) {
    modal.setAttribute("aria-hidden", "false");
    document.documentElement.classList.add("tbb-cf-open");
    const panel = qs(".tbb-cf-panel", modal);
    const scope = panel || modal;
    const first = qs(
      "input:not([type=hidden]):not([type=button]):not([type=submit]), textarea, select",
      scope
    );
    if (first) {
      window.requestAnimationFrame(() => {
        first.focus({ preventScroll: true });
      });
    }
  }

  function closeModal(modal) {
    modal.setAttribute("aria-hidden", "true");
    document.documentElement.classList.remove("tbb-cf-open");
  }

  function setStatus(modal, text, kind) {
    const el = qs("[data-tbb-cf-status]", modal);
    if (!el) return;
    el.textContent = text || "";
    el.dataset.kind = kind || "";
  }

  function init(root) {
    const openBtn = qs("[data-tbb-cf-open]", root);
    const modal = qs("[data-tbb-cf-modal]", root);
    if (!openBtn || !modal) return;

    const closeEls = qsa("[data-tbb-cf-close]", modal);
    const form = qs("[data-tbb-cf-form]", modal);

    openBtn.addEventListener("click", () => openModal(modal));
    closeEls.forEach((el) => el.addEventListener("click", () => closeModal(modal)));

    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape" && modal.getAttribute("aria-hidden") === "false") {
        closeModal(modal);
        openBtn.focus({ preventScroll: true });
      }
    });

    if (form) {
      form.addEventListener("submit", async (e) => {
        e.preventDefault();
        setStatus(modal, "", "");

        const submitBtn = qs("button[type=submit]", form);
        if (submitBtn) submitBtn.disabled = true;

        try {
          const pageUrlInput = qs("[data-tbb-cf-page-url]", form);
          if (pageUrlInput) {
            pageUrlInput.value = window.location.href;
          }

          const fd = new FormData(form);
          // Prefer localized nonce, fallback to hidden input.
          if (window.TBBContactForm?.nonce && !fd.get("nonce")) {
            fd.set("nonce", window.TBBContactForm.nonce);
          }
          const ajaxUrl = window.TBBContactForm?.ajaxUrl || form.action || "/wp-admin/admin-ajax.php";

          const res = await fetch(ajaxUrl, {
            method: "POST",
            credentials: "same-origin",
            body: fd,
          });

          const json = await res.json().catch(() => null);
          if (!res.ok || !json) {
            throw new Error(json?.data?.message || "Submission failed.");
          }

          if (json.success) {
            setStatus(modal, json.data?.message || "Sent.", "success");
            form.reset();
            setTimeout(() => {
              closeModal(modal);
              openBtn.focus({ preventScroll: true });
            }, 700);
          } else {
            setStatus(modal, json.data?.message || "Submission failed.", "error");
          }
        } catch (err) {
          setStatus(modal, err?.message || "Submission failed.", "error");
        } finally {
          if (submitBtn) submitBtn.disabled = false;
        }
      });
    }
  }

  function boot() {
    qsa(".tbb-cf-button").forEach((btn) => init(btn.parentElement || document));
    // Also initialize any modals even if button class customized.
    qsa("[data-tbb-cf-modal]").forEach((modal) => init(modal.parentElement || document));
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
  } else {
    boot();
  }
})();

