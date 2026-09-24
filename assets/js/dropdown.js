// Accordion: each `li.dropdown-item` opens and closes its body with a height animation.
//
// Options:
//   isOpen             start with every item open (default false)
//   allowSingleOpen    opening an item closes the others (default false)
//   animationDuration  CSS duration (default "0.3s")
//   animationEasing    CSS easing (default "ease")
export default class DropdownHandler {
  constructor(selector, config = {}) {
    this.wrapper = document.querySelector(selector);
    if (!this.wrapper) return;

    this.items = this.wrapper.querySelectorAll("li.dropdown-item");
    this.config = {
      isOpen: config.isOpen ?? false,
      allowSingleOpen: config.allowSingleOpen ?? false,
      animationDuration: config.animationDuration ?? "0.3s",
      animationEasing: config.animationEasing ?? "ease",
    };

    // Ignore clicks while an item animates, so heights can't be read mid-transition
    this.isTransitioning = false;

    this.init();
  }

  get transition() {
    return `${this.config.animationDuration} ${this.config.animationEasing}`;
  }

  init() {
    this.items.forEach((item) => {
      item.dataset.isOpen = String(this.config.isOpen);
      item.querySelector(".dropdown-item__body").style.transition = `height ${this.transition}`;
      this.setBodyVisibility(item);

      // Only the head toggles, so clicking or selecting text in an open body keeps it open
      item.querySelector(".dropdown-item__head").addEventListener("click", () => {
        if (this.isTransitioning) return;
        this.isTransitioning = true;
        this.toggle(item);
      });
    });
  }

  toggle(item) {
    const onEnd = () => {
      this.isTransitioning = false;
      item.removeEventListener("transitionend", onEnd);
    };
    item.addEventListener("transitionend", onEnd);

    if (this.config.allowSingleOpen) {
      this.items.forEach((other) => {
        if (other !== item) {
          other.dataset.isOpen = "false";
          this.setBodyVisibility(other);
        }
      });
    }

    item.dataset.isOpen = String(item.dataset.isOpen !== "true");
    this.setBodyVisibility(item);
  }

  setBodyVisibility(item) {
    const body = item.querySelector(".dropdown-item__body");
    const icon = item.querySelector(".dropdown-item__toggle svg");
    const isOpen = item.dataset.isOpen === "true";

    // Animate from the current pixel height (height: auto can't be transitioned)
    body.style.height = body.scrollHeight + "px";

    if (isOpen) {
      // Release to auto once open, so the body follows content or width changes
      const onExpand = () => {
        body.style.height = "auto";
        body.removeEventListener("transitionend", onExpand);
      };
      body.addEventListener("transitionend", onExpand);
    } else {
      body.offsetHeight; // force a reflow so the collapse starts from the pixel height
      body.style.height = "0px";
    }

    icon.style.transition = `transform ${this.transition}`;
    icon.style.transform = isOpen ? "rotate(180deg)" : "rotate(0deg)";
  }
}
