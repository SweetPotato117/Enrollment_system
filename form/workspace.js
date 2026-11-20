(() => {
  const icons = {
    dashboard: 'M3 3h7v7H3Zm11 0h7v7h-7ZM3 14h7v7H3Zm11 0h7v7h-7Z',
    applicants: 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2m20 0v-2a4 4 0 0 0-3-3.9M13 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0m4-4a4 4 0 0 1 0 8',
    application: 'M13 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V10Zm0 0v7h7M8 15h8m-8 3h5',
    exam: 'M5 5h14a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Zm3-2v4m8-4v4M3 10h18m-13 5h2m4 0h2',
    enrollment: 'M9 4H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2h-3m-6-2h6v5H9Zm-1 12 3 3 5-6',
    students: 'm2 10 10-5 10 5-10 5-10-5Zm4 2v6c4 3 8 3 12 0v-6m4-2v7',
    sections: 'M4 3h6v6H4Zm10 0h6v6h-6ZM4 15h6v6H4Zm10 0h6v6h-6ZM7 9v3h10V9m-10 3v3m10-3v3',
    admins: 'M12 22s8-3 8-10V5l-8-3-8 3v7c0 7 8 10 8 10Zm-3-10 2 2 4-4',
    profile: 'M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2M16 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0',
    logout: 'M10 17l5-5-5-5m5 5H3m9-8h7a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-7',
    menu: 'M4 6h16M4 12h16M4 18h16',
    arrow: 'M5 12h14m-6-6 6 6-6 6'
  };
  function icon(name) {
    return `<svg class="workspace-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="${icons[name]}"/></svg>`;
  }

  const navigation = [
    ['', [['dashboard.html', 'Overview', 'dashboard']]],
    ['Admissions', [
      ['applicants.html', 'Applicants', 'applicants', 'Review applicant records, documents and entrance exam results.', ['application.html', 'New application']],
      ['application.html', 'New application', 'application', 'Enter a learner’s application and attach supporting documents.'],
      ['exam_schedule.html', 'Exam schedules', 'exam', 'Manage entrance exam dates, times and venues by curriculum.']
    ]],
    ['Registration', [
      ['main.html', 'Enrollment', 'enrollment', 'Look up a learner by LRN, confirm their information and assign a section.'],
      ['students.html', 'Student records', 'students', 'Find students cleared for registration and update their enrollment records.', ['main.html', 'Open enrollment']],
      ['section_management.html', 'Sections', 'sections', 'Manage class sections, advisers and student lists.']
    ]],
    ['Administration', [
      ['admin_management.html', 'Administrator accounts', 'admins', 'Manage staff access to the admissions and registration workspace.'],
      ['admin_profile.html', 'My profile', 'profile', 'Update your account details and password.']
    ]]
  ];

  const currentFile = location.pathname.split('/').pop().toLowerCase() || 'dashboard.html';
  const main = document.querySelector('main.workspace-page');
  const shell = document.querySelector('[data-workspace-shell]');
  if (!main || !shell) return;

  const activeGroup = navigation.find(([, group]) => group.some(([file]) => file === currentFile));
  if (!activeGroup) return;
  const [currentGroup, pages] = activeGroup;
  const [, title, pageIcon, description, action] = pages.find(([file]) => file === currentFile);
  const navLink = ([file, label, symbol]) => `<a href="${file}"${file === currentFile ? ' aria-current="page"' : ''}>${icon(symbol)}<span>${label}</span></a>`;

  shell.innerHTML = `
    <a class="workspace-skip" href="#workspace-main">Skip to content</a>
    <header class="workspace-bar">
      <button type="button" class="workspace-menu" aria-label="Workspace navigation" aria-controls="workspace-sidebar" aria-expanded="false">${icon('menu')}</button>
      <a class="workspace-brand" href="dashboard.html">
        <img src="../assets/logo1.png" width="36" height="36" alt="">
        <span><strong>Bongabon National High School</strong><small>Admissions &amp; Registration</small></span>
      </a>
      <a class="workspace-account" href="admin_profile.html" aria-label="My profile"${currentFile === 'admin_profile.html' ? ' aria-current="page"' : ''}>${icon('profile')}<span>My profile</span></a>
    </header>
    <div class="workspace-scrim" hidden></div>
    <aside class="workspace-sidebar" id="workspace-sidebar" aria-label="Admin workspace">
      <nav class="workspace-navigation" aria-label="Workspace navigation">
        ${navigation.map(([label, group]) => `<div class="workspace-nav-group">${label ? `<p>${label}</p>` : ''}${group.map(navLink).join('')}</div>`).join('')}
      </nav>
      <a class="workspace-signout" href="../controllers/logout_admin.php">${icon('logout')}<span>Sign out</span></a>
    </aside>`;

  if (description) {
    main.insertAdjacentHTML('afterbegin', `
      <div class="workspace-intro">
        <nav class="workspace-crumb" aria-label="Breadcrumb"><a href="dashboard.html">Overview</a><span aria-hidden="true">/</span><span>${currentGroup}</span></nav>
        <div class="workspace-title-row">
          <div><h1>${title}</h1><p>${description}</p></div>
          ${action ? `<a class="workspace-button" href="${action[0]}">${icon(pageIcon)}${action[1]}</a>` : ''}
        </div>
      </div>`);
  }

  const menu = shell.querySelector('.workspace-menu');
  const sidebar = shell.querySelector('.workspace-sidebar');
  const scrim = shell.querySelector('.workspace-scrim');
  const compact = matchMedia('(max-width: 960px)');

  function setNavigation(open, restoreFocus = false) {
    const expanded = compact.matches && open;
    menu.setAttribute('aria-expanded', String(expanded));
    sidebar.inert = compact.matches && !expanded;
    document.body.classList.toggle('workspace-navigation-open', expanded);
    scrim.hidden = !expanded;
    if (expanded) (sidebar.querySelector('[aria-current="page"]') || sidebar.querySelector('a')).focus();
    if (restoreFocus) menu.focus();
  }

  menu.addEventListener('click', () => setNavigation(menu.getAttribute('aria-expanded') !== 'true'));
  scrim.addEventListener('click', () => setNavigation(false, true));
  compact.addEventListener('change', () => setNavigation(false));
  setNavigation(false);

  document.addEventListener('keydown', event => {
    if (menu.getAttribute('aria-expanded') !== 'true') return;
    if (event.key === 'Escape') setNavigation(false, true);
    if (event.key !== 'Tab') return;
    const links = sidebar.querySelectorAll('a');
    const first = links[0];
    const last = links[links.length - 1];
    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  });

  shell.querySelector('.workspace-signout').addEventListener('click', event => {
    if (!window.confirm('Sign out of the admin workspace?')) event.preventDefault();
  });

  const formNav = main.querySelector('[data-workspace-form-nav]');
  if (formNav) {
    const index = formNav.closest('details');
    const wideLayout = matchMedia('(min-width: 1151px)');
    index.open = wideLayout.matches;
    wideLayout.addEventListener('change', event => { index.open = event.matches; });
    main.querySelectorAll('.card__header h2').forEach((heading, index) => {
      const card = heading.closest('.card');
      card.id ||= `workspace-section-${index + 1}`;
      const link = document.createElement('a');
      link.href = `#${card.id}`;
      link.textContent = heading.textContent;
      formNav.append(link);
    });
  }

  main.querySelectorAll('.field > label:not([for])').forEach((label, index) => {
    const field = label.parentElement;
    const inputs = field.querySelectorAll('input:not([type="radio"]):not([type="checkbox"]):not([type="hidden"]), select, textarea');
    if (inputs.length !== 1 || label.contains(inputs[0])) return;
    inputs[0].id ||= `workspace-field-${index + 1}`;
    label.htmlFor = inputs[0].id;
  });

  if (currentFile === 'dashboard.html') {
    main.querySelectorAll('[data-workspace-icon]').forEach(node => {
      node.innerHTML = icon(node.dataset.workspaceIcon);
    });
  }
})();
