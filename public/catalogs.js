(() => {
  const search = document.getElementById('catalog-search');
  if (!search) return;
  const groups = Array.from(document.querySelectorAll('.catalog-group'));
  const empty = document.getElementById('catalog-empty');
  const normalize = value => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();
  const filter = () => {
    const terms = normalize(search.value).split(/\s+/).filter(Boolean);
    let total = 0;
    groups.forEach(group => {
      let visible = 0;
      group.querySelectorAll('.catalog-card').forEach(card => {
        const text = normalize(card.dataset.search || card.textContent);
        card.hidden = !terms.every(term => text.includes(term));
        if (!card.hidden) visible++;
      });
      group.hidden = visible === 0;
      const count = group.querySelector('.catalog-count');
      if (count) {
        let label = count.querySelector('[data-catalog-count]');
        if (!label) {
          Array.from(count.childNodes).filter(node => node.nodeType === 3).forEach(node => node.remove());
          label = document.createElement('span');
          label.dataset.catalogCount = '';
          count.append(label);
        }
        label.textContent = String(visible);
      }
      total += visible;
    });
    if (empty) empty.hidden = total !== 0;
  };
  search.addEventListener('input', filter);
  search.addEventListener('search', filter);
  filter();
})();
