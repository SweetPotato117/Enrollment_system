(() => {
  const storageKey = 'sectionsData_v1';
  const search = document.getElementById('sectionSearch');
  const curriculumFilter = document.getElementById('sectionCurriculum');
  const count = document.getElementById('sectionsCount');
  const directory = document.getElementById('sectionsContainer');
  const directoryMessage = document.getElementById('sectionDirectoryMessage');
  const template = document.getElementById('sectionCardTemplate');
  const createButton = document.getElementById('openCreateSectionBtn');
  const createDialog = document.getElementById('createSectionModal');
  const createForm = document.getElementById('createSectionForm');
  const createCurriculum = document.getElementById('createCurriculum');
  const gradeInput = document.getElementById('createGradeLevel');
  const sectionInput = document.getElementById('createSectionName');
  const newAdviserInput = document.getElementById('createAdviserName');
  const createError = document.getElementById('createSectionError');
  const viewDialog = document.getElementById('viewSectionModal');
  const adviserInput = document.getElementById('viewAdviserInput');
  const nameInput = document.getElementById('addStudentName');
  const roster = document.getElementById('studentsCards');
  const rosterCount = document.getElementById('sectionRosterCount');
  const rosterEmpty = document.getElementById('sectionRosterEmpty');
  const rosterFeedback = document.getElementById('sectionRosterFeedback');
  const curricula = new Map(Array.from(createCurriculum.options, option => [option.value, option.label]));
  let activeSection = null;
  let savedAdviser = '';

  function loadSections() {
    const raw = localStorage.getItem(storageKey);
    if (raw === null) {
      const names = ['SPICT', 'STE', 'SPJ', 'SPA', 'ICT'];
      const defaults = names.map((name, index) => ({ id: 's' + (index + 1), name, adviser: '', students: [] }));
      const data = { regular: { 'Grade 7': defaults } };
      saveSections(data);
      return data;
    }
    const data = JSON.parse(raw);
    if (!data || typeof data !== 'object' || Array.isArray(data)) throw new Error('Invalid section records');
    return data;
  }

  function saveSections(data) {
    localStorage.setItem(storageKey, JSON.stringify(data));
  }

  function curriculumLabel(value) {
    return curricula.get(value) || value.toUpperCase();
  }

  function studentCount(students) {
    return `${students.length} ${students.length === 1 ? 'student' : 'students'}`;
  }

  function recordStudents(section) {
    return Array.isArray(section.students) ? section.students : [];
  }

  function setDirectoryOptions(data, selected) {
    const options = document.createDocumentFragment();
    options.append(new Option('All curricula', ''));
    const values = new Set([...curricula.keys(), ...Object.keys(data)]);
    values.forEach(value => options.append(new Option(curriculumLabel(value), value)));
    curriculumFilter.replaceChildren(options);
    curriculumFilter.value = values.has(selected) ? selected : '';
  }

  function showDirectoryMessage(message) {
    directoryMessage.textContent = message;
    directoryMessage.hidden = !message;
  }

  function renderSections({ curriculum = curriculumFilter.value, restoreSection = null } = {}) {
    let data;
    try {
      data = loadSections();
    } catch (error) {
      directory.hidden = true;
      count.textContent = '';
      showDirectoryMessage('Section records could not be loaded from this browser.');
      if (restoreSection) createButton.focus();
      return;
    }

    setDirectoryOptions(data, curriculum);
    const term = search.value.trim().toLocaleLowerCase();
    const cards = document.createDocumentFragment();
    let matchCount = 0;
    let restoredButton = null;

    for (const [code, grades] of Object.entries(data)) {
      if (!grades || (curriculum && curriculum !== code)) continue;
      for (const [grade, records] of Object.entries(grades)) {
        if (!Array.isArray(records)) continue;
        for (const record of records) {
          if (!record || !record.name) continue;
          const text = [record.name, grade, record.adviser].join(' ').toLocaleLowerCase();
          if (!text.includes(term)) continue;

          const card = template.content.cloneNode(true);
          card.querySelector('[data-card-curriculum]').textContent = curriculumLabel(code);
          card.querySelector('[data-card-grade]').textContent = grade;
          card.querySelector('[data-card-name]').textContent = record.name;
          card.querySelector('[data-card-adviser]').textContent = record.adviser ? `Adviser · ${record.adviser}` : 'Adviser unassigned';
          card.querySelector('[data-card-size]').textContent = studentCount(recordStudents(record));
          const openButton = card.querySelector('[data-open-section]');
          openButton.setAttribute('aria-label', `View roster for ${record.name}, ${grade}, ${curriculumLabel(code)}`);
          openButton.addEventListener('click', () => openSection({ curriculum: code, grade, id: record.id }));
          if (restoreSection && restoreSection.id === record.id && restoreSection.grade === grade && restoreSection.curriculum === code) {
            restoredButton = openButton;
          }
          cards.append(card);
          matchCount++;
        }
      }
    }

    directory.replaceChildren(cards);
    directory.hidden = matchCount === 0;
    count.textContent = `${matchCount} ${matchCount === 1 ? 'section' : 'sections'}`;
    showDirectoryMessage(matchCount ? '' : 'No sections match these filters.');
    if (restoreSection) (restoredButton || search).focus();
  }

  function findSection(data, { curriculum, grade, id }) {
    const records = data[curriculum]?.[grade];
    return Array.isArray(records) ? records.find(record => record?.id === id) : undefined;
  }

  function renderRoster(section) {
    const students = recordStudents(section);
    const names = document.createDocumentFragment();
    for (const name of students) {
      const item = document.createElement('li');
      item.textContent = name;
      names.append(item);
    }
    roster.replaceChildren(names);
    roster.hidden = students.length === 0;
    rosterEmpty.hidden = students.length > 0;
    rosterCount.textContent = studentCount(students);
  }

  function showRosterFeedback(message, error = false) {
    rosterFeedback.textContent = message;
    rosterFeedback.classList.toggle('section-form-error', error);
    rosterFeedback.hidden = !message;
  }

  function openSection(ref) {
    let section;
    try {
      section = findSection(loadSections(), ref);
      if (!section) throw new Error('Section unavailable');
    } catch (error) {
      showDirectoryMessage('This section could not be opened. Reload the page and try again.');
      return;
    }
    activeSection = ref;
    document.getElementById('viewSectionTitle').textContent = section.name;
    document.getElementById('viewSectionContext').textContent = `${curriculumLabel(ref.curriculum)} · ${ref.grade}`;
    savedAdviser = section.adviser || '';
    adviserInput.value = savedAdviser;
    nameInput.value = '';
    nameInput.setCustomValidity('');
    showRosterFeedback('');
    renderRoster(section);
    viewDialog.showModal();
  }

  function updateActiveSection(update, confirmation) {
    if (!activeSection) return null;
    try {
      const data = loadSections();
      const section = findSection(data, activeSection);
      if (!section) throw new Error('Section unavailable');
      update(section);
      saveSections(data);
      showRosterFeedback(confirmation);
      return section;
    } catch (error) {
      showRosterFeedback('The updated section could not be saved in this browser.', true);
      return null;
    }
  }

  function saveAdviser() {
    const adviser = adviserInput.value.trim();
    if (adviser === savedAdviser) return;
    if (updateActiveSection(section => { section.adviser = adviser; }, 'Adviser updated.')) {
      savedAdviser = adviser;
      adviserInput.value = adviser;
    }
  }

  function requireText(input, message) {
    if (input.value.trim()) return true;
    input.setCustomValidity(message);
    input.reportValidity();
    return false;
  }

  [gradeInput, sectionInput, nameInput].forEach(input => {
    input.addEventListener('input', () => input.setCustomValidity(''));
  });

  [createDialog, viewDialog].forEach(dialog => {
    dialog.addEventListener('click', event => {
      if (event.target === dialog || event.target.closest('[data-close-section-dialog]')) dialog.close();
    });
  });

  createButton.addEventListener('click', () => {
    createForm.reset();
    gradeInput.setCustomValidity('');
    sectionInput.setCustomValidity('');
    createError.hidden = true;
    createCurriculum.value = curricula.has(curriculumFilter.value) ? curriculumFilter.value : 'regular';
    createDialog.showModal();
  });

  createForm.addEventListener('submit', event => {
    event.preventDefault();
    if (!requireText(gradeInput, 'Enter a grade level.') || !requireText(sectionInput, 'Enter a section name.')) return;
    const curriculum = createCurriculum.value;
    const grade = gradeInput.value.trim();
    const section = {
      id: 's' + Date.now().toString(36) + Math.random().toString(36).slice(2, 8),
      name: sectionInput.value.trim(),
      adviser: newAdviserInput.value.trim(),
      students: []
    };

    try {
      const data = loadSections();
      const grades = data[curriculum] || {};
      const records = Array.isArray(grades[grade]) ? grades[grade] : [];
      saveSections({ ...data, [curriculum]: { ...grades, [grade]: [...records, section] } });
    } catch (error) {
      createError.textContent = 'This section could not be saved in this browser.';
      createError.hidden = false;
      return;
    }
    search.value = '';
    createDialog.close();
    renderSections({ curriculum });
  });

  document.getElementById('sectionAdviserForm').addEventListener('submit', event => {
    event.preventDefault();
    saveAdviser();
  });
  adviserInput.addEventListener('change', saveAdviser);

  document.getElementById('sectionStudentForm').addEventListener('submit', event => {
    event.preventDefault();
    if (!requireText(nameInput, 'Enter a student name.')) return;
    const name = nameInput.value.trim();
    const section = updateActiveSection(record => { record.students = [...recordStudents(record), name]; }, 'Student added to the roster.');
    if (section) {
      renderRoster(section);
      nameInput.value = '';
      nameInput.focus();
    }
  });

  viewDialog.addEventListener('close', () => {
    renderSections({ restoreSection: activeSection });
    activeSection = null;
  });
  search.addEventListener('input', () => renderSections());
  curriculumFilter.addEventListener('change', () => renderSections());
  window.addEventListener('storage', event => {
    if (event.key === storageKey && !viewDialog.open) renderSections();
  });
  renderSections();
})();
