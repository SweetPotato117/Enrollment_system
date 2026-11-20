// Enable submit only when certification is checked
const certify = document.getElementById('certify');
const submitBtn = document.getElementById('submitBtn');

function syncSubmit() {
  if (!certify || !submitBtn) return;
  submitBtn.disabled = !certify.checked;
}
if (certify && submitBtn) {
  certify.addEventListener('change', syncSubmit);
  syncSubmit();
}

// Optional: smooth open/close of <details> panes
document.querySelectorAll('details.pane').forEach(pane => {
  pane.addEventListener('toggle', () => {
    const content = Array.from(pane.children).slice(1);
    content.forEach(el => el.style.transition = 'opacity .18s ease');
    const open = pane.open;
    content.forEach(el => el.style.opacity = open ? '1' : '0');
  });
});

// Preserve segmented-radio behavior when clicking anywhere on the option
// Prevent native focus/scroll while preserving keyboard access
document.querySelectorAll('.segmented__option').forEach(opt => {
  const input = opt.querySelector('input[type="radio"]');
  if (!input) return;

  // Handle pointer interactions (mouse/touch/pen) and prevent native focus
  opt.addEventListener('pointerdown', (e) => {
    if (!input) return;
    e.preventDefault();
    input.checked = true;
    input.dispatchEvent(new Event('change', { bubbles: true }));
    // remove focus to avoid browser scrolling the input into view
    try { input.blur(); } catch (err) { /* ignore */ }
  }, {passive:false});

  // Keyboard support: allow Enter or Space to activate the option
  opt.addEventListener('keydown', (e) => {
    if (e.key === ' ' || e.key === 'Enter') {
      e.preventDefault();
      input.checked = true;
      input.dispatchEvent(new Event('change', { bubbles: true }));
      try { input.blur(); } catch (err) { /* ignore */ }
    }
  });

  // Prevent the default click behavior from re-focusing the input
  opt.addEventListener('click', (e) => {
    e.preventDefault();
  });
});

// Show entrance exam schedule only for Grade 7
const yearLevel = document.getElementById('yearLevel');
const grade7Schedule = document.getElementById('grade7Schedule');

function syncGrade7Schedule() {
  if (!yearLevel || !grade7Schedule) return;
  const isGrade7 = yearLevel.value.trim().toLowerCase() === 'grade 7';
  grade7Schedule.classList.toggle('is-visible', isGrade7);
}

if (yearLevel) {
  yearLevel.addEventListener('change', syncGrade7Schedule);
  syncGrade7Schedule();
}

// Helpers: format ISO dates (YYYY-MM-DD) and 24h times to readable strings
function formatDateReadable(dateStr) {
  if (!dateStr) return '';
  // Accept formats like YYYY-MM-DD or full ISO
  try {
    // If time portion present, Date can parse; else create from parts to avoid timezone shifts
    if (/^\d{4}-\d{2}-\d{2}$/.test(dateStr)) {
      const parts = dateStr.split('-').map(p => parseInt(p, 10));
      const dt = new Date(parts[0], parts[1] - 1, parts[2]);
      return dt.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
    }
    const dt = new Date(dateStr);
    if (!isNaN(dt)) return dt.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
  } catch (e) {
    // fallthrough
  }
  return dateStr;
}

function formatTimeReadable(timeStr) {
  if (!timeStr) return '';
  // Accept HH:MM or HH:MM:SS
  const m = timeStr.split(':');
  if (!m.length) return timeStr;
  const hh = parseInt(m[0], 10);
  const mm = parseInt(m[1] || '0', 10);
  if (Number.isNaN(hh) || Number.isNaN(mm)) return timeStr;
  const ampm = hh >= 12 ? 'PM' : 'AM';
  const hour12 = ((hh + 11) % 12) + 1;
  return `${hour12}:${String(mm).padStart(2, '0')} ${ampm}`;
}

// Render exam schedules for selected sections on the Application page
(function(){
  const sectionCheckboxes = document.querySelectorAll('input[name="sections[]"]');
  const sectionSchedulesContainer = document.getElementById('sectionSchedules');

  function renderSectionSchedules(){
    if (!sectionSchedulesContainer) return;
    const selected = Array.from(sectionCheckboxes).filter(c=>c.checked).map(c=>c.value);
    sectionSchedulesContainer.innerHTML = '';

    if (!selected.length){
      sectionSchedulesContainer.innerHTML = '<p class="helper">Select one or more sections to preview their exam schedules.</p>';
      return;
    }

    // For each selected curriculum, fetch schedules from server
    selected.forEach(async (sec) => {
      const box = document.createElement('div');
      box.className = 'inner-panel';
      box.style.marginBottom = '10px';
      const title = document.createElement('div');
      title.style.fontWeight = '700';
      title.style.marginBottom = '6px';
      title.textContent = sec.toUpperCase();
      box.appendChild(title);

      // Special cases for Regular / First
      if (sec === 'regular') {
        const p = document.createElement('p');
        p.textContent = 'No entrance exam schedule applies for the Regular track.';
        box.appendChild(p);
        sectionSchedulesContainer.appendChild(box);
        return;
      }
      if (sec === 'first') {
        const p = document.createElement('p');
        p.textContent = 'please submit a copy of report card';
        box.appendChild(p);
        sectionSchedulesContainer.appendChild(box);
        return;
      }

      // Fetch schedules from server for the curriculum
      try {
        const resp = await fetch('../controllers/exam_schedule.php?curriculum=' + encodeURIComponent(sec));
        const js = await resp.json();
        const secSchedules = (js && js.success && Array.isArray(js.schedules)) ? js.schedules : [];
        if (!secSchedules.length) {
          const p = document.createElement('p'); p.textContent = 'No schedule set for this track.'; box.appendChild(p);
        } else {
          const ul = document.createElement('ul'); ul.style.margin = '6px 0 0 18px';
          secSchedules.forEach(s => {
            const li = document.createElement('li');
            // prefer server-provided date/time fields (exam_date/exam_time)
            const d = s.exam_date || s.date || '';
            const t = s.exam_time || s.time || '';
            const v = s.venue || '';
            const dateText = formatDateReadable(d);
            const timeText = formatTimeReadable(t);
            const space = dateText && timeText ? ' ' : '';
            li.textContent = `${dateText}${space}${timeText}${dateText || timeText ? ' — ' : ''}${v}`;
            ul.appendChild(li);
          });
          box.appendChild(ul);
        }
      } catch (err) {
        const p = document.createElement('p'); p.textContent = 'Failed to load schedules.'; box.appendChild(p);
      }

      sectionSchedulesContainer.appendChild(box);
    });
  }

  if (sectionCheckboxes && sectionCheckboxes.length) {
    sectionCheckboxes.forEach(cb => cb.addEventListener('change', renderSectionSchedules));
    // initial render
    renderSectionSchedules();
  }
})();

// Populate Section select based on selected Curriculum in Enrollment page
(function(){
  const curriculumEl = document.getElementById('curriculumSelect');
  const sectionEl = document.getElementById('sectionSelect');

  if (!curriculumEl || !sectionEl) return;

  const makeSections = (prefix) => {
    const arr = [];
    for (let i = 1; i <= 15; i++) arr.push(`${prefix} Section ${i}`);
    return arr;
  };

  const map = {
    'regular': makeSections('Regular'),
    'first': makeSections('First'),
    'spict': makeSections('SPICT'),
    'ste': makeSections('STE'),
    'spj': makeSections('SPJ'),
    'spa': makeSections('SPA'),
    'ict': makeSections('ICT')
  };

  function populateSections() {
    const cur = (curriculumEl.value || 'regular').toString();
    const list = map[cur] || makeSections('Section');
    // If section element is a <select>, populate options. If it's an <input>,
    // set a helpful placeholder and do not attempt to write innerHTML (avoid errors).
    if (!sectionEl) return;
    if (sectionEl.tagName === 'SELECT') {
      sectionEl.innerHTML = '<option value="" selected>Select section</option>';
      list.forEach(s => {
        const opt = document.createElement('option');
        opt.value = s.toLowerCase().replace(/\s+/g,'-');
        opt.textContent = s;
        sectionEl.appendChild(opt);
      });
    } else if (sectionEl.tagName === 'INPUT' || sectionEl.tagName === 'TEXTAREA') {
      // For free-text section input, don't overwrite user value; set placeholder
      const first = list[0] || '';
      try {
        sectionEl.placeholder = first;
      } catch (e) { /* ignore */ }
    }
  }

  curriculumEl.addEventListener('change', populateSections);
  // initial populate
  populateSections();
})();
