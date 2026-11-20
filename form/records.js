const workspaceRecords = (() => {
  function name(record) {
    return [record.first_name, record.middle_name, record.last_name].filter(Boolean).join(' ').replace(/\s+/g, ' ').trim() || '(no name)';
  }

  function examStatus(record) {
    return String(record.exam_status || '').trim().toLowerCase();
  }

  const tones = {
    Passed: 'enrolled', Enrolled: 'enrolled', Failed: 'not-enrolled',
    Pending: 'pending', 'Missing Documents': 'missing'
  };

  function documentStatus(status, file) {
    const value = String(status || '').trim().toLowerCase();
    if (value.includes('approve') || value === 'passed') return 'Passed';
    if (value.includes('reject') || value === 'failed') return 'Failed';
    return file ? 'Pending' : 'Missing';
  }

  function applicationStatus(record) {
    if (examStatus(record) === 'enrolled') return 'Enrolled';
    const remark = String(record.exam_remark || record.remark || record.remarks || '').trim().toLowerCase();
    if (remark === 'passed') return 'Passed';
    if (remark === 'failed') return 'Failed';

    const documents = [
      documentStatus(record.report_card_status, record.report_card),
      documentStatus(record.good_moral_status || record.moral_status, record.moral_character || record.moralCharacter),
      documentStatus(record.health_status || record.health_declaration_status, record.health_declaration || record.healthDeclaration)
    ];
    if (documents.includes('Failed')) return 'Missing Documents';
    if (documents.every(status => status === 'Passed')) return 'Passed';
    return 'Pending';
  }

  function badge(label) {
    const span = document.createElement('span');
    span.className = 'status-badge';
    if (tones[label]) span.classList.add(`status-badge--${tones[label]}`);
    span.textContent = label;
    return span;
  }

  function cell(row, label, content) {
    const td = row.insertCell();
    td.setAttribute('role', 'cell');
    if (label) td.dataset.label = label;
    td.append(content);
    return td;
  }

  function row(body) {
    const tr = body.insertRow();
    tr.setAttribute('role', 'row');
    return tr;
  }

  const collator = new Intl.Collator(undefined, { numeric: true, sensitivity: 'base' });
  function sorted(records, sort, value) {
    if (!sort) return records;
    const [key, direction] = sort.split(' ');
    return [...records].sort((a, b) => (direction === 'desc' ? -1 : 1) * collator.compare(value(a, key), value(b, key)));
  }

  return { name, examStatus, applicationStatus, badge, cell, row, sorted };
})();
