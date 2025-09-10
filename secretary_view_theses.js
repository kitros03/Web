// secretary_view_theses.js
(function () {
  const listDiv = document.getElementById('thesesList');
  const detailsDiv = document.getElementById('thesisDetails');
  const searchBox = document.getElementById('searchBox');
  let cached = [];

  function daysLabel(days) {
    if (days === null || typeof days === 'undefined') return '—';
    const d = Number(days);
    if (Number.isNaN(d)) return '—';
    if (d === 0) return '0 ημέρες';
    if (d === 1) return '1 ημέρα';
    return `${d} ημέρες`;
  }

  function renderList(items) {
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
      rows.push(`<td>${it.th_status}</td>`);
      rows.push(`<td>${it.supervisor_name ?? '—'}</td>`);
      rows.push(`<td>${it.student_name ?? '—'}</td>`);
      rows.push(`<td>${daysLabel(it.days_since_assignment)}</td>`);
      rows.push(`<td><button class="sidebarButton" data-id="${it.thesisID}">Λεπτομέρειες</button></td>`);
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
      cached = Array.isArray(data) ? data : [];
      renderList(cached);
    } catch {
      listDiv.innerHTML = '<p class="error">Αποτυχία φόρτωσης λίστας.</p>';
    }
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

  searchBox?.addEventListener('input', () => renderList(filtered()));

  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('button.sidebarButton[data-id]');
    if (!btn) return;
    const thesisID = btn.getAttribute('data-id');
    detailsDiv.innerHTML = '<p>Φόρτωση λεπτομερειών…</p>';
    try {
      const res = await fetch('secretary_thesis_details.php?thesisID=' + encodeURIComponent(thesisID), { credentials: 'same-origin' });
      const d = await res.json();

      const committeeList = (d.committee || []).length
        ? `<ul>${d.committee.map(m => `<li>${m}</li>`).join('')}</ul>`
        : '—';

      detailsDiv.innerHTML = `
        <h3>Λεπτομέρειες ΔΕ #${d.thesisID}</h3>
        <ul>
          <li><strong>Θέμα:</strong> ${d.title ?? ''}</li>
          <li><strong>Περιγραφή:</strong> ${d.th_description ?? ''}</li>
          <li><strong>Τρέχουσα κατάσταση:</strong> ${d.th_status}</li>
          <li><strong>Επιβλέπων:</strong> ${d.supervisor_name ?? '—'}</li>
          <li><strong>Φοιτητής:</strong> ${d.student_name ?? '—'}</li>
          <li><strong>Μέλη Τριμελούς:</strong> ${committeeList}</li>
          <li><strong>Ημερομηνία επίσημης ανάθεσης:</strong> ${d.assigned_date ?? '—'}</li>
          <li><strong>Χρόνος από ανάθεση:</strong> ${daysLabel(d.days_since_assignment)}</li>
        </ul>
      `;
    } catch {
      detailsDiv.innerHTML = '<p class="error">Αποτυχία φόρτωσης λεπτομερειών.</p>';
    }
  });

  loadList();
})();
