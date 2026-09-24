import "../scss/main.scss";
import LazyLoad from "vanilla-lazyload";
import KStore from "./kstore";
import initCartDrawer from "./drawer";
import initCarousels from "./carousel";
import DropdownHandler from "./dropdown";

// Lazy-load `.lazy` images, and re-scan whenever new ones show up
const lazyLoad = new LazyLoad();
document.addEventListener("cart:refreshed", () => lazyLoad.update());
document.addEventListener("carousel:settled", () => lazyLoad.update());

new KStore();
initCartDrawer();
initCarousels();

// FAQ (does nothing on pages without a .dropdown)
new DropdownHandler(".dropdown", {
  allowSingleOpen: true,
  animationDuration: "0.15s",
});

// Checkout: stamp when the form was loaded, the controller rejects
// submissions sent less than 3 seconds later (bots)
const formStart = document.getElementById("form-start");
if (formStart) formStart.value = Date.now();
