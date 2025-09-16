// secretary_manage_theses.js
(function () {
  const listDiv = document.getElementById('manageList');
  const searchBox = document.getElementById('searchBox');
  let cached = [];

  const statusGR = s => ({
    'NOT_ASSIGNED': 'Μη Ανατεθειμένη',
    'ASSIGNED': 'Ανατεθειμένη',
    'ACTIVE': 'Ενεργή',
    'EXAM': 'Υπό Εξέταση',
    'DONE': 'Ολοκληρωμένη',
    'CANCELLED': 'Ακυρωμένη'
  }[s] || s);

  function daysLabel(days) {
    if (days == null) return '—';
    const d = Number(days);
    if (Number.isNaN(d)) return '—';
    if (d === 0) return '0 ημέρες';
    if (d === 1) return '1 ημέρα';
    return `${d} ημέρες`;
  }

  function filtered() {
    const q = (searchBox.value || '').toLowerCase().trim();
    if (!q) return cached;
    return cached.filter(x =>
      (x.title || '').toLowerCase().includes(q) ||
      (x.supervisor_name || '').toLowerCase().includes(q) ||
      (x.student_name || '').toLowerCase().includes(q) ||
      String(x.thesisID).includes(q)
    );
  }

  function render() {
    const items = filtered();
    if (!items.length) {
      listDiv.innerHTML = '<p>Δεν βρέθηκαν ενεργές διπλωματικές.</p>';
      return;
    }

    const rows = [];
    rows.push('<table class="table"><thead><tr>');
    rows.push('<th>Κωδ.</th><th>Τίτλος</th><th>Κατάσταση</th><th>Επιβλέπων</th><th>Φοιτητής</th><th>Από ανάθεση</th><th>GS</th><th>Ενέργειες</th>');
    rows.push('</tr></thead><tbody>');

    items.forEach(it => {
      rows.push('<tr>');
      rows.push(`<td>${it.thesisID}</td>`);
      rows.push(`<td>${it.title ?? ''}</td>`);
      rows.push(`<td>${statusGR(it.th_status)}</td>`);
      rows.push(`<td>${it.supervisor_name ?? '—'}</td>`);
      rows.push(`<td>${it.student_name ?? '—'}</td>`);
      rows.push(`<td>${daysLabel(it.days_since_assignment)}</td>`);
      rows.push(`<td>${it.gs_numb ?? '—'}</td>`);
      rows.push(`<td>
        <button class="sidebarButton act-toggle" data-id="${it.thesisID}">Ενέργειες</button>
      </td>`);
      rows.push('</tr>');

      // κρυφό row για φόρμες (ΠΑΡΑΜΕΝΕΙ ΟΠΩΣ ΗΤΑΝ)
      rows.push(`<tr class="row-forms" data-for="${it.thesisID}" style="display:none;">
        <td colspan="8">
          <div class="form-group" style="margin-bottom:12px;">
            <h4>Καταχώριση GS Number</h4>
            <label>GS Number</label>
            <input type="text" class="gs-input" placeholder="π.χ. 12345" value="${it.gs_numb ?? ''}">
            <button class="submit-btn do-start" data-id="${it.thesisID}">Καταχώριση GS</button>
          </div>
          <hr>
          <div class="form-group">
            <h4>Ακύρωση Διπλωματικής</h4>
            <label>GA Number</label>
            <input type="text" class="ga-input" placeholder="π.χ. 2025-42">
            <label>GA Date</label>
            <input type="date" class="ga-date">
            <label>Αιτιολογία</label>
            <input type="text" class="ga-reason" placeholder="π.χ. από Διδάσκοντα">
            <button class="submit-btn danger do-cancel" data-id="${it.thesisID}">Καταχώριση Ακύρωσης</button>
          </div>
        </td>
      </tr>`);
    });

    rows.push('</tbody></table>');
    listDiv.innerHTML = rows.join('');
  }

  async function loadList() {
    listDiv.innerHTML = '<p>Φόρτωση…</p>';
    try {
      const res = await fetch('secretary_manage_theses_list.php', { credentials: 'same-origin' });
      if (!res.ok) {
        const text = await res.text();
        listDiv.innerHTML = `<p class="error">Αποτυχία φόρτωσης (HTTP ${res.status}).<br>${text}</p>`;
        return;
      }
      const data = await res.json();
      cached = Array.isArray(data) ? data : [];
      render();
    } catch (e) {
      console.error(e);
      listDiv.innerHTML = '<p class="error">Αποτυχία φόρτωσης (δικτύου/JSON).</p>';
    }
  }

  searchBox?.addEventListener('input', render);

  document.addEventListener('click', async (e) => {
    // ΜΟΝΟ ένα κουμπί: Ενέργειες (toggle)
    const toggleBtn = e.target.closest('button.act-toggle[data-id]');
    const doStart   = e.target.closest('button.do-start[data-id]');
    const doCancel  = e.target.closest('button.do-cancel[data-id]');

    if (toggleBtn) {
      const id = toggleBtn.dataset.id;
      const row = document.querySelector(`tr.row-forms[data-for="${id}"]`);
      if (row) row.style.display = (row.style.display === 'none' ? 'table-row' : 'none');
      return;
    }

    if (doStart) {
      const id = doStart.dataset.id;
      const row = document.querySelector(`tr.row-forms[data-for="${id}"]`);
      const gs = (row?.querySelector('.gs-input')?.value || '').trim();
      if (!gs) { alert('Συμπλήρωσε GS Number.'); return; }
      try {
        const res = await fetch('secretary_manage_thesis_actions.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'same-origin',
          body: JSON.stringify({ action: 'startExam', thesisID: Number(id), gs_numb: gs })
        });
        const out = await res.json();
        if (!out.success) throw new Error(out.message || 'Αποτυχία');
        alert('Καταχωρήθηκε το GS Number.');
        await loadList();
      } catch (err) { alert('Σφάλμα: ' + err.message); }
      return;
    }

    if (doCancel) {
      const id = doCancel.dataset.id;
      const row = document.querySelector(`tr.row-forms[data-for="${id}"]`);
      const gaNumber = (row?.querySelector('.ga-input')?.value || '').trim();
      const gaDate   = (row?.querySelector('.ga-date')?.value || '').trim();
      const reason   = (row?.querySelector('.ga-reason')?.value || '').trim() || 'από Διδάσκοντα';
      if (!gaNumber || !gaDate) { alert('Συμπλήρωσε GA Number και GA Date.'); return; }
      try {
        const res = await fetch('secretary_manage_thesis_actions.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'same-origin',
          body: JSON.stringify({ action: 'cancelThesis', thesisID: Number(id), gaNumber, gaDate, reason })
        });
        const out = await res.json();
        if (!out.success) throw new Error(out.message || 'Αποτυχία');
        alert('Η διπλωματική ακυρώθηκε.');
        await loadList();
      } catch (err) { alert('Σφάλμα: ' + err.message); }
      return;
    }
  });

  loadList();
})();
