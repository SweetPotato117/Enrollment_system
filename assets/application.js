(() => {
  const form = document.getElementById('applicationForm');
  const cards = [...form.querySelectorAll('[data-application-step]')];
  const fields = [...form.querySelectorAll('input, select')];
  const stepMenu = document.getElementById('applicationStepMenu');
  const stepButtons = [...stepMenu.querySelectorAll('button')];
  const stepHeadings = stepButtons.map((_, index) =>
    cards.find(card => Number(card.dataset.applicationStep) === index).querySelector('h2'));
  const stepCount = document.getElementById('applicationStepCount');
  const stepLabel = document.getElementById('applicationStepLabel');
  const progress = document.getElementById('applicationProgressBar');
  const actions = form.querySelector('.application-actions');
  const back = document.getElementById('applicationBack');
  const next = document.getElementById('applicationNext');
  const submit = document.getElementById('submitBtn');
  const originalSubmitArea = submit.parentElement;
  const certify = document.getElementById('certify');
  const lrn = document.getElementById('lrnInput');
  const password = document.getElementById('password');
  const confirmation = document.getElementById('confirmPassword');
  const schedulePreview = document.getElementById('sectionSchedules');
  const trackInputs = [...form.querySelectorAll('input[name="sections[]"]')];
  const conditionalFields = [...form.querySelectorAll('[data-application-conditional]')];
  const familyPanes = [...form.querySelectorAll('details.pane[open]')];
  const overlay = document.getElementById('appMessageOverlay');
  const closeMessage = document.getElementById('appMessageClose');
  const viewport = window.matchMedia('(max-width: 760px)');

  const finalStep = stepButtons.length - 1;
  const fileInputs = fields.filter(field => field.type === 'file');
  const dateFormat = new Intl.DateTimeFormat('en-PH', { dateStyle: 'medium' });
  const timeFormat = new Intl.DateTimeFormat('en-PH', { hour: 'numeric', minute: '2-digit' });
  let step = 0;
  let furthestStep = 0;
  let submitting = false;
  let returnFocus;

  function fieldError(field) {
    if (field === lrn && !/^[0-9]{12}$/.test(lrn.value)) return 'Enter your 12-digit LRN.';
    if (field === certify && !certify.checked) return 'Confirm that your information is correct before submitting.';
    if (field.required && !field.value.trim()) {
      const label = field.labels[0].textContent.replace('*', '').trim();
      return `Enter your ${label.toLowerCase()}.`;
    }
    if ((field === password || field === confirmation) && field.value.length < 8) {
      return 'Use at least 8 characters.';
    }
    if (field === confirmation && confirmation.value !== password.value) return 'The passwords do not match.';
    return field.validity.valid ? '' : field.validationMessage;
  }

  function renderError(field, message) {
    const errorId = `${field.id}-error`;
    let error = document.getElementById(errorId);
    if (!message) {
      error?.remove();
      field.removeAttribute('aria-invalid');
      field.removeAttribute('aria-describedby');
      return;
    }

    if (!error) {
      error = document.createElement('p');
      error.className = 'application-field-error';
      error.id = errorId;
      if (field === certify) field.closest('label').after(error);
      else field.closest('.field').append(error);
    }
    error.textContent = message;
    field.setAttribute('aria-invalid', 'true');
    field.setAttribute('aria-describedby', errorId);
  }

  function validate(inputs) {
    let invalidField = null;
    for (const field of inputs) {
      const message = fieldError(field);
      renderError(field, message);
      if (message && !invalidField) invalidField = field;
    }
    if (!invalidField) return true;

    if (viewport.matches) showStep(Number(invalidField.closest('[data-application-step]').dataset.applicationStep), false);
    const pane = invalidField.closest('details');
    if (pane) pane.open = true;
    invalidField.focus({ preventScroll: true });
    invalidField.scrollIntoView({ block: 'center' });
    return false;
  }

  function renderConditionals() {
    for (const field of conditionalFields) {
      const answer = form.elements.namedItem(field.dataset.applicationConditional);
      field.hidden = viewport.matches && answer.value !== 'yes';
    }
  }

  function selectedTracks() {
    return trackInputs.filter(input => input.checked);
  }

  function renderReview() {
    const value = name => form.elements.namedItem(name).value.trim();
    const applicant = ['first_name', 'middle_name', 'last_name'].map(value).filter(Boolean).join(' ');
    const tracks = selectedTracks().map(input => input.value.toUpperCase()).join(', ');
    const family = ['father_first', 'father_last', 'father_contact', 'mother_first', 'mother_last', 'mother_contact', 'guardian_name', 'guardian_contact'].some(name => value(name));
    const attached = fileInputs.filter(input => input.files.length).length;
    document.getElementById('reviewApplicant').textContent = applicant;
    document.getElementById('reviewEmail').textContent = value('email') || 'Not provided';
    document.getElementById('reviewLrn').textContent = lrn.value;
    document.getElementById('reviewTracks').textContent = tracks || 'None selected';
    document.getElementById('reviewFamily').textContent = family ? 'Details provided' : 'Not provided';
    document.getElementById('reviewDocuments').textContent = attached ? `${attached} of ${fileInputs.length} attached` : 'None attached';
  }

  function renderControls() {
    const mobile = viewport.matches;
    submit.disabled = submitting || (!mobile && (!lrn.validity.valid || !certify.checked));
    submit.hidden = mobile && step !== finalStep;
    submit.textContent = submitting ? 'Submitting…' : mobile ? 'Submit application' : 'Submit Application';
    next.hidden = step === finalStep;
    back.hidden = step === 0;
    next.disabled = submitting;
    back.disabled = submitting;
    stepButtons.forEach((button, index) => {
      button.disabled = submitting || index > furthestStep;
      if (index === step) button.setAttribute('aria-current', 'step');
      else button.removeAttribute('aria-current');
    });
  }

  function showStep(index, focus = true) {
    step = index;
    furthestStep = Math.max(furthestStep, step);
    stepMenu.open = false;
    cards.forEach(card => {
      card.hidden = viewport.matches && Number(card.dataset.applicationStep) !== step;
    });
    stepCount.textContent = `Step ${step + 1} of ${stepButtons.length}`;
    stepLabel.textContent = stepButtons[step].textContent;
    progress.value = step + 1;
    if (step === finalStep) renderReview();
    renderControls();

    if (focus) {
      stepHeadings[step].focus({ preventScroll: true });
      window.scrollTo(0, 0);
    }
  }

  function syncViewport() {
    if (viewport.matches) actions.append(submit);
    else originalSubmitArea.append(submit);
    renderConditionals();
    showStep(step, false);
  }

  function navigate(index) {
    if (!viewport.matches || submitting) return;
    if (index > step) {
      const requiredSteps = fields.filter(field => {
        const fieldStep = Number(field.closest('[data-application-step]').dataset.applicationStep);
        return fieldStep >= step && fieldStep < index;
      });
      if (!validate(requiredSteps)) return;
    }
    showStep(index);
  }

  function scheduleLine(schedule) {
    const date = schedule.exam_date || schedule.date || '';
    const time = schedule.exam_time || schedule.time || '';
    const startDate = new Date(`${date}T00:00:00`);
    const startTime = new Date(`2000-01-01T${time}`);
    const dateText = Number.isNaN(startDate.getTime()) ? date : dateFormat.format(startDate);
    const timeText = Number.isNaN(startTime.getTime()) ? time : timeFormat.format(startTime);
    return [[dateText, timeText].filter(Boolean).join(' at '), schedule.venue].filter(Boolean).join(' — ');
  }

  async function loadSchedule(track, content) {
    const endpoint = new URL('exam_schedule.php', form.action);
    endpoint.searchParams.set('curriculum', track);
    try {
      const response = await fetch(endpoint);
      const result = await response.json();
      if (!response.ok || !result.success || !Array.isArray(result.schedules)) throw new Error('Missing exam schedule response');
      if (!result.schedules.length) {
        content.textContent = 'No exam schedule has been posted for this track.';
        return;
      }
      const list = document.createElement('ul');
      list.className = 'application-schedule-list';
      for (const schedule of result.schedules) {
        const item = document.createElement('li');
        item.textContent = scheduleLine(schedule);
        list.append(item);
      }
      content.replaceChildren(list);
    } catch {
      content.textContent = 'Exam schedules are unavailable right now.';
    }
  }

  function renderSchedules() {
    schedulePreview.replaceChildren();
    const tracks = selectedTracks();
    if (!tracks.length) {
      const hint = document.createElement('p');
      hint.className = 'helper';
      hint.textContent = 'Select a track to preview its exam schedule.';
      schedulePreview.append(hint);
    }

    for (const input of tracks) {
      const card = document.createElement('div');
      card.className = 'inner-panel application-schedule';
      const title = document.createElement('strong');
      title.textContent = input.value.toUpperCase();
      const content = document.createElement('div');
      content.textContent = 'Loading exam schedule…';
      card.append(title, content);
      schedulePreview.append(card);
      loadSchedule(input.value, content);
    }
  }

  function showMessage(title, message, focusOnClose) {
    returnFocus = focusOnClose;
    document.getElementById('appMessageTitle').textContent = title;
    document.getElementById('appMessageBody').textContent = message;
    overlay.style.display = 'flex';
    overlay.removeAttribute('aria-hidden');
    closeMessage.focus();
  }

  function hideMessage() {
    overlay.style.display = 'none';
    overlay.setAttribute('aria-hidden', 'true');
    returnFocus.focus();
  }

  form.addEventListener('input', event => {
    const field = event.target;
    if (field === lrn) lrn.value = lrn.value.replace(/\D/g, '').slice(0, 12);
    if (field.hasAttribute('aria-invalid')) renderError(field, fieldError(field));
    if (field === password && confirmation.hasAttribute('aria-invalid')) renderError(confirmation, fieldError(confirmation));
    if (field === lrn || field === certify) renderControls();
  });

  form.addEventListener('change', event => {
    if (trackInputs.includes(event.target)) renderSchedules();
    if (event.target.matches('[name="ip"], [name="fourps"]')) renderConditionals();
    if (event.target === certify) renderControls();
  });

  back.addEventListener('click', () => navigate(step - 1));
  next.addEventListener('click', () => navigate(step + 1));
  form.querySelectorAll('[data-go-to-step]').forEach(button => {
    button.addEventListener('click', () => navigate(Number(button.dataset.goToStep)));
  });
  document.addEventListener('click', event => {
    if (stepMenu.open && !stepMenu.contains(event.target)) stepMenu.open = false;
    if (event.target === overlay) hideMessage();
  });
  closeMessage.addEventListener('click', hideMessage);
  overlay.addEventListener('keydown', event => {
    if (event.key === 'Escape') hideMessage();
    if (event.key === 'Tab') event.preventDefault();
  });

  form.addEventListener('submit', async event => {
    event.preventDefault();
    if (submitting) return;
    if (viewport.matches && step < finalStep) {
      navigate(step + 1);
      return;
    }
    if (!validate(fields)) return;

    const data = new FormData(form);
    submitting = true;
    form.setAttribute('aria-busy', 'true');
    renderControls();
    try {
      const response = await fetch(form.action, { method: 'POST', body: data });
      const succeeded = response.ok && response.redirected && new URL(response.url).searchParams.get('submitted') === '1';
      if (!succeeded) throw new Error('Application submission failed');
      form.reset();
      furthestStep = 0;
      renderSchedules();
      renderConditionals();
      showStep(0, false);
      if (viewport.matches) window.scrollTo(0, 0);
      const message = data.get('email')?.trim()
        ? 'Check the email address you provided for your account details.'
        : 'You can sign in to the Student Portal with your LRN and the password you created.';
      showMessage('Application submitted', message, stepHeadings[0]);
    } catch {
      showMessage('Submission failed', 'Your entries are still here. Please try submitting again.', submit);
    } finally {
      submitting = false;
      form.removeAttribute('aria-busy');
      renderControls();
    }
  });

  form.noValidate = true;
  stepHeadings.forEach(heading => { heading.tabIndex = -1; });
  document.body.classList.add('application-enhanced');
  if (viewport.matches) familyPanes.forEach(pane => { pane.open = false; });
  viewport.addEventListener('change', syncViewport);
  window.addEventListener('pageshow', () => {
    renderSchedules();
    renderConditionals();
    renderControls();
  });
  syncViewport();
  renderSchedules();
})();
