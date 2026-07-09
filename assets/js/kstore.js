// Handles all store interactions: cart mutations and DOM updates
class KStore {
  constructor() {
    this.count = document.querySelector(".header__bag-count");
    this.list  = document.getElementById("cart-items");

    // A page can show the subtotal more than once (drawer + checkout title)
    this.subtotals = document.querySelectorAll("[data-cart-subtotal]");
  }

  // JSON POST helper
  #post = async (url, body) => {
    const res = await fetch(url, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(body),
    });
    return res.json();
  };

  // Fetch rendered cart from server and update the DOM
  refreshCart = async () => {
    const { html, subtotal, count } = await fetch("/kstore/cart/render").then((r) => r.json());
    if (this.list)  this.list.innerHTML    = html;
    if (this.count) this.count.textContent = `(${count})`;
    this.subtotals.forEach((el) => (el.textContent = subtotal));

    // Let the lazy loader pick up any images in the freshly injected markup
    document.dispatchEvent(new CustomEvent("cart:refreshed"));
  };

  // Add a product to the cart from a button's dataset
  addToCart = async (btn) => {
    const { id, title, price, stock, thumb, color } = btn.dataset;
    await this.#post("/kstore/cart/add", {
      id,
      title,
      price:    parseFloat(price),
      stock:    parseInt(stock, 10),
      thumb,
      quantity: 1,
      color:    color ?? "",
    });
    await this.refreshCart();
  };

  // Increase or decrease the quantity of a cart item
  updateQty = async (id, color, quantity, action) => {
    const delta = action === "increase" ? 1 : -1;
    await this.#post("/kstore/cart/update", { id, color, quantity: quantity + delta });
    await this.refreshCart();
  };

  // Remove an item from the cart
  removeItem = async (id, color) => {
    await this.#post("/kstore/cart/remove", { id, color });
    await this.refreshCart();
  };
}

export default KStore;
