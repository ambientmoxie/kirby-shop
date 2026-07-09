// Slide-over cart. Opened by [data-cart-open], closed by [data-cart-close],
// the backdrop, or Escape. Contents are kept in sync by KStore.refreshCart().
export default function initCartDrawer() {
  const drawer = document.getElementById("cart-drawer");
  if (!drawer) return;

  const isOpen = () => drawer.classList.contains("is-open");

  const open = () => {
    drawer.classList.add("is-open");
    drawer.setAttribute("aria-hidden", "false");
    document.body.classList.add("is-drawer-open");
    drawer.querySelector("[data-cart-close]")?.focus();
  };

  const close = () => {
    drawer.classList.remove("is-open");
    drawer.setAttribute("aria-hidden", "true");
    document.body.classList.remove("is-drawer-open");
  };

  document.addEventListener("click", (e) => {
    if (e.target.closest("[data-cart-open]")) return open();
    if (e.target.closest("[data-cart-close]")) return close();
  });

  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape" && isOpen()) close();
  });
}
