(() => {
  const endpoint = '../controllers/exam_schedule.php';
  const createForm = document.getElementById('newExamForm');
  const createMessage = document.getElementById('examScheduleError');
  const dialog = document.getElementById('examEditDialog');
  const editForm = document.getElementById('examEditForm');
  const editMessage = document.getElementById('examEditError');
  const curriculumFilter = document.getElementById('examFilterCurriculum');
  const dateFilter = document.getElementById('examFilterDate');
  const table = document.getElementById('examRecords');
  const body = document.getElementById('examTableBody');
  const count = document.getElementById('examCount');
  const listMessage = document.getElementById('examStatus');
  const dateFormat = new Intl.DateTimeFormat(undefined, { year: 'numeric', month: 'short', day: 'numeric', timeZone: 'UTC' });
  const timeFormat = new Intl.DateTimeFormat(undefined, { hour: 'numeric', minute: '2-digit', timeZone: 'UTC' });
  let schedules = [];

  async function request(query = '', options) {
    const response = await fetch(endpoint + query, options);
    const result = await response.json();
    if (!response.ok || !result.success) throw new Error(result.message || 'The schedule request could not be completed.');
    return result;
  }

  function feedback(message, text, error = false) {
    message.textContent = text;
    message.hidden = !text;
    message.classList.toggle('is-error', error);
  }

  function curriculum(schedule) {
    return String(schedule.section_curriculum || schedule.section_name || schedule.section_code || schedule.section_id || '').toUpperCase();
  }

  function displayDate(value) {
    const date = new Date(`${value}T00:00:00Z`);
    return Number.isNaN(date.getTime()) ? (value || '—') : dateFormat.format(date);
  }

  function displayTime(value) {
    const time = new Date(`2000-01-01T${value}Z`);
    return Number.isNaN(time.getTime()) ? (value || '—') : timeFormat.format(time);
  }

  function renderSchedules() {
    const visible = schedules.filter(schedule => (!curriculumFilter.value || curriculum(schedule) === curriculumFilter.value)
      && (!dateFilter.value || schedule.exam_date === dateFilter.value));
    body.replaceChildren();
    visible.forEach(schedule => {
      const line = workspaceRecords.row(body);
      const label = curriculum(schedule) || '—';
      workspaceRecords.cell(line, '', label);
      workspaceRecords.cell(line, 'Date', displayDate(schedule.exam_date));
      workspaceRecords.cell(line, 'Time', displayTime(schedule.exam_time));
      workspaceRecords.cell(line, 'Venue', schedule.venue || '—');
      const actions = document.createElement('div');
      actions.className = 'exam-row-actions';
      const edit = document.createElement('button');
      edit.type = 'button';
      edit.className = 'btn btn--secondary';
      edit.textContent = 'Edit';
      edit.setAttribute('aria-label', `Edit ${label} exam on ${displayDate(schedule.exam_date)}`);
      edit.addEventListener('click', () => openEditor(schedule.id, edit));
      const remove = document.createElement('button');
      remove.type = 'button';
      remove.className = 'btn btn--danger';
      remove.textContent = 'Delete';
      remove.setAttribute('aria-label', `Delete ${label} exam on ${displayDate(schedule.exam_date)}`);
      remove.addEventListener('click', () => deleteSchedule(schedule.id, remove));
      actions.append(edit, remove);
      workspaceRecords.cell(line, '', actions);
    });
    count.textContent = `${visible.length} of ${schedules.length} schedules`;
    table.hidden = !visible.length;
    feedback(listMessage, visible.length ? '' : (schedules.length ? 'No schedules match your filters.' : 'No exams have been scheduled.'));
  }

  async function loadSchedules() {
    try {
      const result = await request();
      schedules = result.schedules;
      const selected = curriculumFilter.value;
      const options = [...new Set(schedules.map(curriculum).filter(Boolean))].sort();
      curriculumFilter.replaceChildren(new Option('All curricula', ''), ...options.map(value => new Option(value, value)));
      if (options.includes(selected)) curriculumFilter.value = selected;
      renderSchedules();
    } catch (error) {
      feedback(listMessage, 'Exam schedules could not be loaded. Refresh to try again.', true);
    }
  }

  async function openEditor(id, trigger) {
    trigger.disabled = true;
    try {
      const { schedule } = await request(`?id=${encodeURIComponent(id)}`);
      if (!schedule) throw new Error('This exam is no longer available.');
      editForm.elements.id.value = schedule.id;
      editForm.elements.exam_date.value = schedule.exam_date;
      editForm.elements.exam_time.value = schedule.exam_time;
      editForm.elements.venue.value = schedule.venue || '';
      feedback(editMessage, '');
      trigger.disabled = false;
      dialog.showModal();
    } catch (error) {
      feedback(listMessage, error.message, true);
      trigger.disabled = false;
    }
  }

  async function saveSchedule(event, method, message) {
    event.preventDefault();
    const form = event.currentTarget;
    const button = form.querySelector('button[type="submit"]');
    const values = Object.fromEntries(new FormData(form));
    if (values.exam_time.length === 5) values.exam_time += ':00';
    values.venue = values.venue.trim();
    feedback(message, '');
    button.disabled = true;
    try {
      await request('', { method, headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(values) });
      if (method === 'POST') {
        form.reset();
        feedback(message, 'Exam schedule saved.');
      } else {
        dialog.close();
      }
      await loadSchedules();
    } catch (error) {
      feedback(message, error.message, true);
    } finally {
      button.disabled = false;
    }
  }

  async function deleteSchedule(id, trigger) {
    if (!window.confirm('Delete this exam schedule?')) return;
    trigger.disabled = true;
    try {
      await request(`?id=${encodeURIComponent(id)}`, { method: 'DELETE' });
      await loadSchedules();
    } catch (error) {
      feedback(listMessage, error.message, true);
      trigger.disabled = false;
    }
  }

  createForm.addEventListener('submit', event => saveSchedule(event, 'POST', createMessage));
  editForm.addEventListener('submit', event => saveSchedule(event, 'PUT', editMessage));
  ['closeExamEdit', 'cancelExamEdit'].forEach(id => document.getElementById(id).addEventListener('click', () => dialog.close()));
  curriculumFilter.addEventListener('change', renderSchedules);
  dateFilter.addEventListener('change', renderSchedules);
  document.getElementById('clearExamFilters').addEventListener('click', () => {
    curriculumFilter.value = '';
    dateFilter.value = '';
    renderSchedules();
  });
  loadSchedules();
})();
