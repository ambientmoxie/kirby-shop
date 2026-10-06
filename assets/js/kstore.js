// Cart: add, update and remove through the kstore plugin routes, then re-render
// the drawer list, bag count and subtotals from the server's response.
export default class KStore {
  constructor() {
    this.count = document.querySelector(".header__bag-count");
    this.list = document.getElementById("cart-items");

    // A page can show the subtotal more than once (drawer + checkout title)
    this.subtotals = document.querySelectorAll("[data-cart-subtotal]");

    this.bindEvents();
  }

  bindEvents() {
    // "Add to cart" buttons on product cards
    document.addEventListener("click", (e) => {
      const btn = e.target.closest("[data-action='add-to-cart']");
      if (btn) this.add(btn.dataset.id);
    });

    // Decrease / increase / remove buttons inside the cart list
    this.list?.addEventListener("click", (e) => {
      const btn = e.target.closest("[data-cart-action]");
      if (!btn) return;

      const item = btn.closest("[data-cart-item]");
      const id = item.dataset.cartItem;
      const quantity = parseInt(item.querySelector("[data-cart-qty]")?.dataset.cartQty ?? "1", 10);
      const action = btn.dataset.cartAction;

      if (action === "remove") this.remove(id);
      else this.update(id, quantity + (action === "increase" ? 1 : -1));
    });
  }

  async #post(url, body) {
    const res = await fetch(url, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(body),
    });
    return res.json();
  }

  async refresh() {
    const { html, subtotal, count } = await fetch("/kstore/cart/render").then((r) => r.json());
    if (this.list) this.list.innerHTML = html;
    if (this.count) this.count.textContent = `(${count})`;
    this.subtotals.forEach((el) => (el.textContent = subtotal));

    // Let the lazy loader pick up images in the freshly injected markup
    document.dispatchEvent(new CustomEvent("cart:refreshed"));
  }

  // Only the id is sent: price, title and stock are read from the product page server side
  async add(id) {
    await this.#post("/kstore/cart/add", { id, quantity: 1 });
    await this.refresh();
  }

  async update(id, quantity) {
    await this.#post("/kstore/cart/update", { id, quantity });
    await this.refresh();
  }

  async remove(id) {
    await this.#post("/kstore/cart/remove", { id });
    await this.refresh();
  }
}
