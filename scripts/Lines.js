document.addEventListener("click", function (event) {
  const button = event.target.closest(".open-toggle");
  if (!button) return;
  const line = button.closest(".line");
  if (!line) return;
  const stopList = line.querySelector(".stop-list");
  if (!stopList) return;

  stopList.classList.toggle("open");
  button.classList.toggle("open");
});
