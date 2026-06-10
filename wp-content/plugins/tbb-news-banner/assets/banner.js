(() => {
  const banner = document.getElementById("tbb-news-banner");
  if (!banner) return;

  const getAdminBarHeight = () => {
    if (!document.body.classList.contains("admin-bar")) {
      return 0;
    }
    const bar = document.getElementById("wpadminbar");
    return bar ? bar.offsetHeight : 0;
  };

  const syncBannerLayout = () => {
    if (document.body.classList.contains("tbb-news-banner-dismissed")) {
      document.documentElement.style.removeProperty("--tbb-nb-height");
      document.documentElement.style.removeProperty("--tbb-nb-offset-top");
      document.documentElement.style.removeProperty("--tbb-nb-total-offset");
      return;
    }

    const adminTop = getAdminBarHeight();
    const bannerH = banner.offsetHeight;

    document.documentElement.style.setProperty("--tbb-nb-offset-top", `${adminTop}px`);
    document.documentElement.style.setProperty("--tbb-nb-height", `${bannerH}px`);
    document.documentElement.style.setProperty(
      "--tbb-nb-total-offset",
      `${adminTop + bannerH}px`
    );
  };

  const items = Array.from(banner.querySelectorAll("[data-tbb-nb-item]"));
  const dismiss = banner.querySelector("[data-tbb-nb-dismiss]");

  document.documentElement.classList.add("tbb-news-banner-active");

  if (dismiss) {
    dismiss.addEventListener("click", () => {
      document.body.classList.add("tbb-news-banner-dismissed");
      document.documentElement.classList.remove("tbb-news-banner-active");
      syncBannerLayout();
      try {
        sessionStorage.setItem("tbb_news_banner_dismissed", "1");
      } catch (e) {
        /* ignore */
      }
    });
  }

  try {
    if (sessionStorage.getItem("tbb_news_banner_dismissed") === "1") {
      document.body.classList.add("tbb-news-banner-dismissed");
      return;
    }
  } catch (e) {
    /* ignore */
  }

  syncBannerLayout();
  window.addEventListener("resize", syncBannerLayout);
  window.addEventListener("load", syncBannerLayout);

  if (items.length <= 1) {
    return;
  }

  let index = 0;
  const rotateMs = 6000;

  window.setInterval(() => {
    items[index].classList.remove("is-active");
    index = (index + 1) % items.length;
    items[index].classList.add("is-active");
    syncBannerLayout();
  }, rotateMs);
})();
