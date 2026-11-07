document.addEventListener('click', (event) => {
  const toggle = event.target.closest('[data-toggle-password]');
  if (toggle) {
    const input = document.getElementById(toggle.dataset.togglePassword);
    if (input) { input.type = input.type === 'password' ? 'text' : 'password'; toggle.textContent = input.type === 'password' ? 'Afficher' : 'Masquer'; }
  }
  const submit = event.target.closest('[data-confirm]');
  if (submit && !window.confirm(submit.dataset.confirm)) event.preventDefault();
});

document.querySelectorAll('[data-image-preview]').forEach((input) => {
  input.addEventListener('change', () => {
    const preview = input.parentElement.querySelector('.upload-preview');
    const file = input.files && input.files[0];
    if (!preview || !file) return;
    preview.src = URL.createObjectURL(file);
    preview.hidden = false;
  });
});

const picker = document.querySelector('[data-picker]');
if (picker) {
  const cards = [...picker.querySelectorAll('[data-picker-card]')];
  const count = document.querySelector('[data-selection-count]');
  const summary = document.querySelector('[data-selected-summary]');
  const updateSelection = () => {
    const checked = cards.filter((card) => card.querySelector('input[type="checkbox"]').checked);
    if (count) count.textContent = checked.length;
    if (summary) {
      summary.replaceChildren(...checked.map((card) => {
        const chip = document.createElement('span');
        chip.className = 'selected-chip';
        chip.textContent = card.querySelector('.picker-info b').textContent;
        return chip;
      }));
    }
    cards.forEach((card) => card.classList.toggle('selected', card.querySelector('input').checked));
  };
  picker.addEventListener('change', (event) => { if (event.target.matches('input[type="checkbox"]')) updateSelection(); });
  document.querySelector('[data-clothing-search]')?.addEventListener('input', (event) => {
    const term = event.target.value.trim().toLocaleLowerCase('fr');
    cards.forEach((card) => { card.hidden = !card.dataset.search.toLocaleLowerCase('fr').includes(term); });
  });
  cards.forEach((card) => card.addEventListener('dragstart', (event) => {
    event.dataTransfer.setData('text/plain', card.querySelector('input').value);
    event.dataTransfer.effectAllowed = 'copy';
  }));
  const dropZone = document.querySelector('[data-drop-zone]');
  dropZone?.addEventListener('dragover', (event) => { event.preventDefault(); dropZone.classList.add('drag-over'); });
  dropZone?.addEventListener('dragleave', () => dropZone.classList.remove('drag-over'));
  dropZone?.addEventListener('drop', (event) => {
    event.preventDefault(); dropZone.classList.remove('drag-over');
    const id = event.dataTransfer.getData('text/plain');
    const checkbox = cards.find((card) => card.querySelector('input').value === id)?.querySelector('input');
    if (checkbox) { checkbox.checked = true; updateSelection(); }
  });
  updateSelection();
}

const labelSelect = document.querySelector('select[name="label_id"]');
const categorySelect = document.querySelector('[data-category-select]');
const subcategorySelect = document.querySelector('[data-subcategory-select]');
const filterCategories = () => {
  if (!labelSelect || !categorySelect) return;
  [...categorySelect.options].forEach((option) => {
    if (option.value) option.hidden = option.dataset.labelId !== labelSelect.value;
  });
  const selected = categorySelect.selectedOptions[0];
  if (selected?.value && selected.dataset.labelId !== labelSelect.value) categorySelect.value = '';
  filterSubcategories();
};
const filterSubcategories = () => {
  if (!categorySelect || !subcategorySelect) return;
  [...subcategorySelect.options].forEach((option) => {
    if (option.value) option.hidden = option.dataset.categoryId !== categorySelect.value;
  });
  const selected = subcategorySelect.selectedOptions[0];
  if (selected?.value && selected.dataset.categoryId !== categorySelect.value) subcategorySelect.value = '';
};
labelSelect?.addEventListener('change', filterCategories);
categorySelect?.addEventListener('change', filterSubcategories);
if (labelSelect && categorySelect) filterCategories();

const weekPlanner = document.querySelector('[data-week-planner]');
if (weekPlanner) {
  const status = weekPlanner.querySelector('[data-week-status]');
  let pendingStatus = '';
  try { pendingStatus = sessionStorage.getItem('gringo-week-status') || ''; } catch (_) {}
  if (pendingStatus && status) { status.textContent = pendingStatus; try { sessionStorage.removeItem('gringo-week-status'); } catch (_) {} }

  const saveWeekChange = async (fields, source) => {
    if (source) source.disabled = true;
    if (status) status.textContent = 'Enregistrement…';
    const body = new URLSearchParams({ _token: weekPlanner.dataset.token, ...fields });
    try {
      const response = await fetch(weekPlanner.dataset.endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
        body
      });
      const result = await response.json();
      if (!response.ok || !result.ok) throw new Error(result.message || 'Enregistrement impossible.');
      let message = 'Modifications enregistrées';
      if (result.unavailable_count > 0) message += ` · attention, ${result.unavailable_count} pièce(s) indisponible(s)`;
      try { sessionStorage.setItem('gringo-week-status', message); } catch (_) {}
      const destination = new URL(weekPlanner.dataset.weekUrl, window.location.href);
      destination.searchParams.set('date', fields.event_date);
      window.location.href = destination.toString();
    } catch (error) {
      if (status) status.textContent = error.message || 'Erreur de connexion. Réessayez.';
      if (source) source.disabled = false;
    }
  };

  weekPlanner.addEventListener('change', (event) => {
    const add = event.target.closest('[data-add-outfit]');
    if (add && add.value) {
      const day = add.closest('[data-week-day]');
      saveWeekChange({ action: 'save', event_date: day.dataset.date, outfit_id: add.value }, add);
      return;
    }
    const outfit = event.target.closest('[data-event-outfit]');
    const date = event.target.closest('[data-event-date]');
    if (outfit) {
      const day = outfit.closest('[data-week-day]');
      saveWeekChange({ action: 'save', event_id: outfit.dataset.eventId, event_date: day.dataset.date, outfit_id: outfit.value }, outfit);
    } else if (date) {
      const day = date.closest('[data-week-day]');
      const card = date.closest('[data-event-card]');
      const outfitSelect = card.querySelector('[data-event-outfit]');
      saveWeekChange({ action: 'save', event_id: date.dataset.eventId, event_date: date.value, outfit_id: outfitSelect.value }, date);
    }
  });

  weekPlanner.addEventListener('click', (event) => {
    const remove = event.target.closest('[data-delete-event]');
    if (!remove) return;
    const card = remove.closest('[data-event-card]');
    const day = remove.closest('[data-week-day]');
    saveWeekChange({ action: 'delete', event_id: remove.dataset.deleteEvent, event_date: day.dataset.date }, remove);
  });
}
