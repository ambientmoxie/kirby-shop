import "../scss/main.scss";
import LazyLoad from "vanilla-lazyload";
import KStore from "./kstore";
import initCartDrawer from "./drawer";

// Lazy-load `.lazy` images, and re-scan whenever new ones show up
const lazyLoad = new LazyLoad();
document.addEventListener("cart:refreshed", () => lazyLoad.update());

new KStore();
initCartDrawer();
