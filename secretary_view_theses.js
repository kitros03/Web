(function () {
  const listDiv = document.getElementById('thesesList');
  const detailsDiv = document.getElementById('thesisDetails');
  const searchBox = document.getElementById('searchBox');

  let cachedList = [];
  const detailsCache = new Map();
  let currentOpenId = null;

  const statusGR = s => ({
    'NOT_ASSIGNED': 'Μη Ανατεθειμένη',
    'ASSIGNED':     'Ανατεθειμένη',
    'ACTIVE':       'Ενεργή',
    'EXAM':         'Υπό Εξέταση',
    'DONE':         'Περατωμένη',
    'CANCELLED':    'Ακυρωμένη'
  }[s] || s);

  function daysLabel(days) {
    if (days == null) return '—';
    const d = Number(days);
    if (Number.isNaN(d)) return '—';
    if (d === 0) return '0 ημέρες';
    if (d === 1) return '1 ημέρα';
    return `${d} ημέρες`;
  }

  function hideDetails() {
    detailsDiv.innerHTML = '';
    detailsDiv.style.display = 'none';
    currentOpenId = null;
  }

  function renderList(items) {
    if (currentOpenId && !items.some(x => String(x.thesisID) === String(currentOpenId))) {
      hideDetails();
    }
    if (!items.length) {
      listDiv.innerHTML = '<p>Δεν βρέθηκαν διπλωματικές.</p>';
      return;
    }
    const rows = [];
    rows.push('<table class="table"><thead><tr>');
    rows.push('<th>Κωδ.</th><th>Τίτλος</th><th>Κατάσταση</th><th>Επιβλέπων</th><th>Φοιτητής</th><th>Από ανάθεση</th><th></th>');
    rows.push('</tr></thead><tbody>');
    items.forEach(it => {
      rows.push('<tr>');
      rows.push(`<td>${it.thesisID}</td>`);
      rows.push(`<td>${it.title ?? ''}</td>`);
      rows.push(`<td>${statusGR(it.th_status)}</td>`);
      rows.push(`<td>${it.supervisor_name ?? '—'}</td>`);
      rows.push(`<td>${it.student_name ?? '—'}</td>`);
      rows.push(`<td>${daysLabel(it.days_since_assignment)}</td>`);
      if (it.th_status === 'DONE') {
        rows.push(`<td><button class="sidebarButton btn-done" data-id="${it.thesisID}">Done</button></td>`);
      } else {
        rows.push(`<td><button class="sidebarButton btn-details" data-id="${it.thesisID}">Λεπτομέρειες</button></td>`);
      }
      rows.push('</tr>');
    });
    rows.push('</tbody></table>');
    listDiv.innerHTML = rows.join('');
  }

  async function loadList() {
    listDiv.innerHTML = '<p>Φόρτωση…</p>';
    try {
      const res = await fetch('secretary_theses_list.php', { credentials: 'same-origin' });
      const data = await res.json();
      cachedList = Array.isArray(data) ? data : [];
      renderList(cachedList);
    } catch (e) {
      listDiv.innerHTML = '<p class="error">Αποτυχία φόρτωσης λίστας.</p>';
      console.error(e);
    }
  }

  function filtered() {
    const q = (searchBox.value || '').toLowerCase().trim();
    if (!q) return cachedList;
    return cachedList.filter(x =>
      (x.title || '').toLowerCase().includes(q) ||
      (x.supervisor_name || '').toLowerCase().includes(q) ||
      (x.student_name || '').toLowerCase().includes(q) ||
      (String(x.thesisID)).includes(q)
    );
  }

  function buildDoneHTML(info) {
    const link = info.repository_url
      ? `<a href="${info.repository_url}" target="_blank" rel="noopener">Άνοιγμα αποθετηρίου</a>`
      : '—';
    const grade = (info.final_grade != null && !Number.isNaN(Number(info.final_grade)))
      ? Number(info.final_grade).toFixed(2)
      : '—';

    const canFinalize = info.th_status !== 'DONE' ? '' : 'disabled';

    return `
      <h3>Περατωμένη ΔΕ #${info.thesisID}</h3>
      <p><strong>Σύνδεσμος αποθετηρίου:</strong> ${link}</p>
      <p><strong>Τελικός βαθμός:</strong> ${grade}</p>
      <button class="submit-btn finalize-btn" data-id="${info.thesisID}" ${canFinalize}>Οριστική Περάτωση</button>
    `;
  }

  function buildDetailsHTML(d) {
    const committeeList = (d.committee || []).length ? d.committee.join(', ') : '—';
    return `
      <h3>Λεπτομέρειες ΔΕ #${d.thesisID}</h3>
      <p><strong>Τίτλος:</strong> ${d.title ?? ''}</p>
      <p><strong>Καθηγητής-Επιβλέπων:</strong> ${d.supervisor_name ?? '—'}</p>
      <p><strong>Επιτροπή:</strong> ${committeeList}</p>
      <p><strong>Στάδιο:</strong> ${statusGR(d.th_status)}</p>
      <p><strong>Χρόνος από επίσημη ανάθεση:</strong> ${d.days_since_assignment ?? '—'}</p>
      <p><strong>Περιγραφή:</strong><br>${d.th_description ?? ''}</p>
    `;
  }

  document.addEventListener('click', async (e) => {
    const btnDetails = e.target.closest('button.btn-details[data-id]');
    if (btnDetails) {
      const thesisID = btnDetails.dataset.id;
      if (currentOpenId && String(currentOpenId) === String(thesisID)) {
        hideDetails(); return;
      }
      currentOpenId = thesisID;
      detailsDiv.style.display = 'block';
      detailsDiv.innerHTML = '<p>Φόρτωση…</p>';
      try {
        const res = await fetch('secretary_thesis_details.php?thesisID=' + encodeURIComponent(thesisID), { credentials:'same-origin' });
        const d = await res.json();
        detailsDiv.innerHTML = buildDetailsHTML(d);
      } catch (err) {
        console.error(err);
        detailsDiv.innerHTML = '<p class="error">Αποτυχία φόρτωσης λεπτομερειών.</p>';
      }
      return;
    }

    const btnDone = e.target.closest('button.btn-done[data-id]');
    if (btnDone) {
      const thesisID = btnDone.dataset.id;
      if (currentOpenId && String(currentOpenId) === String(thesisID)) {
        hideDetails(); return;
      }
      currentOpenId = thesisID;
      detailsDiv.style.display = 'block';
      detailsDiv.innerHTML = '<p>Φόρτωση…</p>';
      try {
        const res = await fetch('secretary_done_info.php?thesisID=' + encodeURIComponent(thesisID), { credentials:'same-origin' });
        const info = await res.json();
        detailsDiv.innerHTML = buildDoneHTML(info);
      } catch (err) {
        console.error(err);
        detailsDiv.innerHTML = '<p class="error">Αποτυχία φόρτωσης πληροφοριών DONE.</p>';
      }
      return;
    }

    const finalizeBtn = e.target.closest('button.finalize-btn[data-id]');
    if (finalizeBtn) {
      const thesisID = Number(finalizeBtn.dataset.id);
      finalizeBtn.disabled = true;
      finalizeBtn.textContent = 'Ενημέρωση…';
      try {
        const res = await fetch('secretary_mark_done.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'same-origin',
          body: JSON.stringify({ thesisID })
        });
        const out = await res.json();
        if (!out.success) throw new Error(out.message || 'Αποτυχία');
        alert('Η διπλωματική χαρακτηρίστηκε ως Περατωμένη.');
        await loadList();
        const r2 = await fetch('secretary_done_info.php?thesisID=' + thesisID, { credentials:'same-origin' });
        const info2 = await r2.json();
        detailsDiv.innerHTML = buildDoneHTML(info2);
      } catch (err) {
        alert('Σφάλμα: ' + err.message);
        finalizeBtn.disabled = false;
        finalizeBtn.textContent = 'Οριστική Περάτωση';
      }
      return;
    }
  });

  searchBox?.addEventListener('input', () => renderList(filtered()));
  loadList();
})();
