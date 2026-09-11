(() => {
  'use strict';
  const region = document.getElementById('warranty-region');
  const province = document.getElementById('warranty-province');
  const city = document.getElementById('warranty-city');
  const postalCode = document.getElementById('warranty-postal-code');
  if (!region || !province || !city || !postalCode) return;

  const initial = {
    region: region.dataset.initial || '',
    province: province.dataset.initial || '',
    city: city.dataset.initial || '',
    postalCode: postalCode.dataset.initial || ''
  };

  const setOptions = (select, values, placeholder, selected = '') => {
    select.replaceChildren();
    const empty = document.createElement('option');
    empty.value = '';
    empty.textContent = placeholder;
    select.appendChild(empty);
    values.forEach(value => {
      const option = document.createElement('option');
      option.value = value;
      option.textContent = value;
      option.selected = value === selected;
      select.appendChild(option);
    });
    select.disabled = values.length === 0;
  };

  const failSafe = () => {
    [region, province, city, postalCode].forEach(select => {
      select.disabled = false;
      select.setCustomValidity('Impossibile caricare l’elenco delle località. Ricarica la pagina e riprova.');
    });
  };

  fetch('/idemaclima/public/idemaclima-locations-2026.json', { credentials: 'same-origin' })
    .then(response => {
      if (!response.ok) throw new Error(`HTTP ${response.status}`);
      return response.json();
    })
    .then(data => {
      const provincesFor = value => value && data[value] ? Object.keys(data[value]).sort((a, b) => a.localeCompare(b, 'it')) : [];
      const citiesFor = (r, p) => r && p && data[r] && data[r][p] ? Object.keys(data[r][p]).sort((a, b) => a.localeCompare(b, 'it')) : [];
      const capsFor = (r, p, c) => r && p && c && data[r] && data[r][p] && data[r][p][c] ? data[r][p][c] : [];

      const updatePostalCodes = selected => setOptions(postalCode, capsFor(region.value, province.value, city.value), 'Seleziona CAP', selected);
      const updateCities = (selectedCity = '', selectedCap = '') => {
        setOptions(city, citiesFor(region.value, province.value), 'Seleziona città', selectedCity);
        updatePostalCodes(selectedCap);
      };
      const updateProvinces = (selectedProvince = '', selectedCity = '', selectedCap = '') => {
        setOptions(province, provincesFor(region.value), 'Seleziona provincia', selectedProvince);
        updateCities(selectedCity, selectedCap);
      };

      setOptions(region, Object.keys(data).sort((a, b) => a.localeCompare(b, 'it')), 'Seleziona regione', initial.region);
      if (initial.region) updateProvinces(initial.province, initial.city, initial.postalCode);
      else updateProvinces();

      region.addEventListener('change', () => updateProvinces());
      province.addEventListener('change', () => updateCities());
      city.addEventListener('change', () => updatePostalCodes());
    })
    .catch(failSafe);
})();
