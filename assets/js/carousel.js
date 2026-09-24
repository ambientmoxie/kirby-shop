import Flickity from "flickity";
import "flickity/css/flickity.css";

// Turns every [data-carousel] into a Flickity carousel driven by the
// [data-carousel-prev] / [data-carousel-next] buttons in its parent.
// Cell width is set in CSS, so how many slides are visible stays a style concern.
export default function initCarousels() {
  document.querySelectorAll("[data-carousel]").forEach((el) => {
    const flkty = new Flickity(el, {
      cellAlign: "left",
      contain: true,
      groupCells: false, // advance one cell at a time
      pageDots: false,
      prevNextButtons: false, // we supply our own
      draggable: true,
    });

    const scope = el.parentElement;
    const prev = scope.querySelector("[data-carousel-prev]");
    const next = scope.querySelector("[data-carousel-next]");

    prev?.addEventListener("click", () => flkty.previous());
    next?.addEventListener("click", () => flkty.next());

    // Flickity clamps at both ends when `contain` is on, so grey the buttons out
    const syncButtons = () => {
      if (prev) prev.disabled = flkty.selectedIndex === 0;
      if (next) next.disabled = flkty.selectedIndex >= flkty.slides.length - 1;
    };

    flkty.on("select", syncButtons);
    flkty.on("resize", syncButtons);
    syncButtons();

    // Let the lazy loader re-scan images that just slid into view
    flkty.on("settle", () => document.dispatchEvent(new CustomEvent("carousel:settled")));
  });
}
