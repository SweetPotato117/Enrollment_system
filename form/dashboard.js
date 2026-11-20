(async () => {
  const message = document.getElementById('dashboardStatus');
  const body = document.getElementById('recentApplications');
  const { name, examStatus, applicationStatus, badge, row, cell } = workspaceRecords;
  try {
    const profile = await fetch('../controllers/admin_profile.php');
    if (profile.status === 401) {
      const link = document.createElement('a');
      link.href = '../index.html';
      link.className = 'workspace-link';
      link.textContent = 'Sign in to view applications';
      message.replaceChildren(link);
      return;
    }
    if (!profile.ok || !(await profile.json()).ok) throw new Error('Admin profile unavailable');

    const response = await fetch('../controllers/get_applicants.php');
    if (!response.ok) throw new Error('Applicant records unavailable');
    const result = await response.json();
    if (!result.success || !Array.isArray(result.applicants)) throw new Error('Applicant records unavailable');
    const records = result.applicants;
    const number = new Intl.NumberFormat();
    document.getElementById('metricApplicants').textContent = number.format(records.length);
    document.getElementById('metricPending').textContent = number.format(records.filter(record => applicationStatus(record) === 'Pending').length);
    document.getElementById('metricCleared').textContent = number.format(records.filter(record => examStatus(record) === 'passed').length);
    document.getElementById('metricEnrolled').textContent = number.format(records.filter(record => examStatus(record) === 'enrolled').length);
    document.getElementById('dashboardLimit').hidden = records.length < 1000;

    if (!records.length) {
      message.textContent = 'No applications have been submitted.';
      return;
    }
    records.slice(0, 5).forEach(record => {
      const line = row(body);
      cell(line, '', name(record));
      cell(line, 'Curriculum', String(record.curriculum || '—').toUpperCase());
      cell(line, 'Status', badge(applicationStatus(record)));
      const link = document.createElement('a');
      link.className = 'workspace-link';
      link.href = `applicants.html?applicant=${encodeURIComponent(record.id)}`;
      link.textContent = 'View record';
      link.setAttribute('aria-label', `View record for ${name(record)}`);
      cell(line, '', link);
    });
    document.getElementById('dashboardRecords').hidden = false;
    message.hidden = true;
  } catch (error) {
    message.textContent = 'Application records could not be loaded. Refresh to try again.';
  }
})();
