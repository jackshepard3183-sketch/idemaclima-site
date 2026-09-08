(() => {
  const button = document.querySelector('.menu-toggle');
  const menu = document.getElementById('mobile-nav');

  if (!button || !menu) return;

  const closeMenu = () => {
    menu.classList.remove('open');
    button.setAttribute('aria-expanded', 'false');
    button.setAttribute('aria-label', 'Apri menu');
    button.textContent = '☰';
  };

  button.addEventListener('click', () => {
    const open = menu.classList.toggle('open');
    button.setAttribute('aria-expanded', open ? 'true' : 'false');
    button.setAttribute('aria-label', open ? 'Chiudi menu' : 'Apri menu');
    button.textContent = open ? '×' : '☰';
  });

  menu.addEventListener('click', (event) => {
    if (event.target.closest('a')) closeMenu();
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') closeMenu();
  });
})();


document.addEventListener('DOMContentLoaded', function () {
  const input = document.getElementById('catalog-search');
  if (!input) return;
  const cards = Array.from(document.querySelectorAll('.catalog-card'));
  const groups = Array.from(document.querySelectorAll('.catalog-group'));
  const empty = document.getElementById('catalog-empty');
  input.addEventListener('input', function () {
    const query = input.value.trim().toLocaleLowerCase('it');
    cards.forEach(function (card) { card.hidden = query !== '' && !card.dataset.search.includes(query); });
    let visibleGroups = 0;
    groups.forEach(function (group) {
      const visible = Array.from(group.querySelectorAll('.catalog-card')).some(function (card) { return !card.hidden; });
      group.hidden = !visible;
      if (visible) visibleGroups += 1;
    });
    if (empty) empty.hidden = visibleGroups !== 0;
  });
});