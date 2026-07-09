import "../scss/main.scss";
import LazyLoad from "vanilla-lazyload";
import KStore from "./kstore";
import initCarousels from "./carousel";
import initCartDrawer from "./drawer";

// Lazy-load images (targets `.lazy`, the default selector)
const lazyLoad = new LazyLoad();

// Re-scan after the cart is re-rendered via AJAX (new `.lazy` images injected)
document.addEventListener("cart:refreshed", () => lazyLoad.update());

// Re-scan once a carousel stops moving (cells that just slid into view)
document.addEventListener("carousel:settled", () => lazyLoad.update());

initCarousels();
initCartDrawer();

const Store = new KStore();

// Add to cart buttons (delegated)
document.addEventListener("click", (e) => {
  const btn = e.target.closest("[data-action='add-to-cart']");
  if (!btn) return;
  Store.addToCart(btn);
});

// Update and remove items — the list lives in the drawer, or on the cart page
const cartList = document.getElementById("cart-items");

if (cartList) {
  cartList.addEventListener("click", async (e) => {
    const btn = e.target.closest("[data-cart-action]");
    if (!btn) return;

    const article = btn.closest("[data-cart-item]");
    const id = article.dataset.cartItem;
    const color = article.dataset.cartColor ?? "";
    const qtyEl = article.querySelector("[data-cart-qty]");
    const currentQty = qtyEl ? parseInt(qtyEl.dataset.cartQty, 10) : 1;

    const action = btn.dataset.cartAction;

    action === "remove"
      ? await Store.removeItem(id, color)
      : await Store.updateQty(id, color, currentQty, action);
  });
}

// Stamp form submission time for bot detection on checkout page
const formStart = document.getElementById("form-start");
if (formStart) formStart.value = Date.now();