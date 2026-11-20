(() => {
  const body = document.getElementById('studentsTableBody');
  const table = document.getElementById('studentRecords');
  const count = document.getElementById('studentCount');
  const message = document.getElementById('studentMessage');
  const grade = document.getElementById('gradeFilter');
  const curriculum = document.getElementById('sectionFilter');
  const search = document.getElementById('studentSearchInput');
  const status = document.getElementById('studentStatus');
  const sort = document.getElementById('studentSort');
  const params = new URLSearchParams(location.search);
  if (['cleared', 'enrolled'].includes(params.get('status'))) status.value = params.get('status');

  let students = [];

  function sectionName(student) {
    const section = String(student.section_text || student.section || '').trim();
    if (section.startsWith('[')) {
      try {
        const parsed = JSON.parse(section);
        if (Array.isArray(parsed) && parsed.length) return String(parsed[0]);
      } catch (error) { /* Older records may use plain text. */ }
    }
    return section.split(/[,|;]/).map(part => part.trim()).find(Boolean) || 'To be assigned';
  }

  function renderStudents() {
    const terms = search.value.trim().toLowerCase().split(/\s+/).filter(Boolean);
    const visible = workspaceRecords.sorted(students.filter(student => {
      return (!grade.value || student.grade === grade.value)
        && (!curriculum.value || student.curriculum === curriculum.value)
        && (!status.value || student.status === status.value)
        && terms.every(term => student.name.toLowerCase().includes(term) || student.lrn.includes(term));
    }), sort.value, (record, key) => record[key]);
    body.replaceChildren();
    visible.forEach(student => {
      const line = workspaceRecords.row(body);
      const section = document.createElement('span');
      section.className = 'record-secondary';
      section.textContent = [student.grade, 'Section: ' + student.section].filter(Boolean).join(' · ');
      workspaceRecords.cell(line, '', student.name).append(section);
      workspaceRecords.cell(line, 'Curriculum', student.curriculum || '—');
      workspaceRecords.cell(line, 'LRN', student.lrn || '—');
      const statusCell = workspaceRecords.cell(line, 'Status', workspaceRecords.badge(student.status === 'enrolled' ? 'Enrolled' : 'Passed'));
      if (student.remark && student.status !== 'enrolled') {
        const remark = document.createElement('span');
        remark.className = 'record-secondary';
        remark.textContent = student.remark;
        statusCell.append(remark);
      }
      const edit = document.createElement('a');
      edit.className = 'workspace-link';
      edit.href = 'main.html';
      edit.textContent = 'Edit enrollment';
      edit.setAttribute('aria-label', 'Edit enrollment for ' + student.name);
      edit.addEventListener('click', event => {
        try {
          if (student.lrn) localStorage.setItem('editStudentLrn', student.lrn);
        } catch (error) {
          event.preventDefault();
          message.hidden = false;
          message.textContent = 'Student lookup could not be opened.';
        }
      });
      workspaceRecords.cell(line, '', edit);
    });
    count.textContent = visible.length + ' of ' + students.length + ' records';
    table.hidden = !visible.length;
    message.hidden = visible.length > 0;
    message.textContent = students.length ? 'No students match your filters.' : 'No students are cleared for enrollment.';
  }

  function populateFilter(select, values) {
    const options = [...new Set(values.filter(Boolean))].sort((a, b) => a.localeCompare(b, undefined, { numeric: true }));
    options.forEach(value => select.add(new Option(value, value)));
  }

  async function loadStudents() {
    try {
      const response = await fetch('../controllers/get_applicants.php');
      if (!response.ok) throw new Error('Students unavailable');
      const result = await response.json();
      if (!result.success || !Array.isArray(result.applicants)) throw new Error('Students unavailable');
      students = result.applicants.filter(record => ['passed', 'enrolled'].includes(workspaceRecords.examStatus(record))).map(record => ({
        name: workspaceRecords.name(record),
        lrn: String(record.lrn || ''),
        grade: String(record.year_level || '').trim(),
        curriculum: String(record.curriculum || '').trim().toUpperCase(),
        section: sectionName(record),
        status: workspaceRecords.examStatus(record) === 'enrolled' ? 'enrolled' : 'cleared',
        remark: String(record.exam_remark || '').trim()
      }));
      populateFilter(grade, students.map(student => student.grade));
      populateFilter(curriculum, students.map(student => student.curriculum));
      renderStudents();
    } catch (error) {
      message.hidden = false;
      message.textContent = 'Student records could not be loaded. Refresh to try again.';
    }
  }

  [grade, curriculum, status, sort].forEach(control => control.addEventListener('change', renderStudents));
  search.addEventListener('input', renderStudents);
  loadStudents();
})();
