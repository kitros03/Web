document.addEventListener('DOMContentLoaded', () => {
  // Navigation
  const thesisBtn  = document.getElementById('thesisviewBtn');
  if (thesisBtn) thesisBtn.addEventListener('click', e => { e.preventDefault(); window.location.href = 'thesisview.php'; });
  const profileBtn = document.getElementById('profileBtn');
  if (profileBtn) profileBtn.addEventListener('click', e => { e.preventDefault(); window.location.href = 'profile.php'; });
  const logoutBtn  = document.getElementById('logoutBtn');
  if (logoutBtn) logoutBtn.addEventListener('click', e => { e.preventDefault(); window.location.href = '../logout.php'; });

  const panel = document.querySelector('.announcements');
  const listEl = document.getElementById('announcementsList');

  // Announcements (AJAX)
  async function loadAnnouncements() {
    try {
      const r = await fetch('announcements_fetch.php', { credentials:'same-origin', cache:'no-store', headers:{'Accept':'application/json'} });
      if (!r.ok) throw new Error('HTTP '+r.status);
      const data = await r.json();
      const items = data.items || [];
      if (!items.length) { listEl.innerHTML = '<p>Δεν υπάρχουν ανακοινώσεις αυτή τη στιγμή.</p>'; return; }
      listEl.innerHTML = `
        <ul class="announcement-list">
          ${items.map(x => `
            <li class="announcement-item">
              <h3>Ανακοίνωση Παρουσίασης</h3>
              <p><strong>Φοιτητής:</strong> ${escapeHtml(x.student)}</p>
              <p><strong>Θέμα:</strong> ${escapeHtml(x.title)}</p>
              <p><strong>Ημερομηνία & Ώρα:</strong> ${x.exam_datetime ? fmtDate(x.exam_datetime) : '-'}</p>
              ${x.exam_meeting_url ? `<p><strong>Σύνδεσμος Συνάντησης:</strong> <a href="${escapeAttr(x.exam_meeting_url)}" target="_blank">${escapeHtml(x.exam_meeting_url)}</a></p>` :
                 x.exam_room ? `<p><strong>Αίθουσα:</strong> ${escapeHtml(x.exam_room)}</p>` : ``}
            </li>
          `).join('')}
        </ul>`;
    } catch (err) {
      console.error(err);
      listEl.innerHTML = '<p>Σφάλμα φόρτωσης ανακοινώσεων.</p>';
    }
  }

  // Manage Thesis (AJAX JSON)
  const manageBtn = document.getElementById('managethesesBtn');
  if (manageBtn && panel) {
    manageBtn.addEventListener('click', e => { e.preventDefault(); loadManage(); });
    panel.addEventListener('submit', onPanelSubmit, false); // delegation
  }

  async function loadManage() {
    try {
      const r = await fetch('manage_thesis_content.php', { credentials:'same-origin', cache:'no-store', headers:{'Accept':'application/json'} });
      if (!r.ok) throw new Error('HTTP '+r.status);
      const data = await r.json();
      renderManage(panel, data);
    } catch (e) {
      console.error('loadManage error:', e);
      panel.innerHTML = '<section class="card"><p class="muted">Σφάλμα φόρτωσης.</p></section>';
    }
  }

  async function onPanelSubmit(e) {
    const form = e.target; if (!form) return;
    const fid = form.id;
    if (fid==='inviteForm' || fid==='examDraftForm' || fid==='examScheduleForm' || fid==='examAfterForm') {
      e.preventDefault();
      try {
        const fd = new FormData(form);
        const r = await fetch('student_manage_thesis_content.php', { method:'POST', credentials:'same-origin', body: fd, headers:{'Accept':'application/json'} });
        const data = await r.json();
        if (!data.success && data.message) alert(data.message);
        await loadManage();
      } catch (err) {
        alert('Σφάλμα αποθήκευσης.');
        console.error(err);
      }
    }
  }

  function renderManage(container, data) {
    if (!data || !data.view) { container.innerHTML = '<section class="card"><p class="muted">Μη έγκυρη απόκριση.</p></section>'; return; }
    if (data.view==='ASSIGNED') {
      container.innerHTML = `
        <h3>${escapeHtml(data.title || 'Διαχείριση Διπλωματικής — Υπό Ανάθεση')}</h3>
        <p>${escapeHtml(data.note || '')}</p>
        <form id="inviteForm" class="form">
          <input type="hidden" name="action" value="invite">
          <label for="teacherUsername">Διδάσκων (username)</label>
          <select id="teacherUsername" name="teacherUsername" required>
            <option value="">Επέλεξε διδάσκοντα</option>
            ${(data.teachers || []).map(t => `<option value="${escapeAttr(t.val)}">${escapeHtml(t.label)}</option>`).join('')}
          </select>
          <button type="submit" class="submit-btn">Αποστολή Πρόσκλησης</button>
        </form>
        <h3>Προσκλήσεις Επιτροπής</h3>
        <div id="inviteTable">${data.inviteTable || '<p>Καμία πρόσκληση.</p>'}</div>
      `;
      return;
    }
    if (data.view==='ACTIVE') {
      container.innerHTML = `
        <h3>${escapeHtml(data.title || 'Διαχείριση Διπλωματικής — Ενεργή')}</h3>
        ${data.html || '<div class="announcements" style="min-height:220px;"></div>'}
      `;
      return;
    }
    if (data.view==='EXAM') {
      const m = data.meta || {};
      const linksText = (m.external_links || []).join('\n');
      const hasFile = !!m.has_file;
      const fileName = m.draft_file ? m.draft_file.split('/').pop() : '';
      container.innerHTML = `
        <h3>${escapeHtml(data.title || 'Διαχείριση Διπλωματικής — Υπό Εξέταση (EXAM)')}</h3>
        <div class="grid-2 mt-16">
          <section class="card">
            <h4>1) Πρόχειρο κείμενο & σύνδεσμοι</h4>
            <p class="small">Ανέβασε αρχείο και πρόσθεσε συνδέσμους (Drive, YouTube κ.λπ.).</p>
            <form id="examDraftForm" enctype="multipart/form-data">
              <input type="hidden" name="action" value="save_draft">
              <input type="hidden" name="thesisID" value="${escapeAttr(m.thesisID)}">
              <label>Πρόχειρο αρχείο:</label>
              <input class="w-100" type="file" name="draft_file" accept=".pdf,.doc,.docx,.ppt,.pptx">
              <label class="mt-8">Σύνδεσμοι (ένας ανά γραμμή):</label>
              <textarea class="w-100" name="external_links" rows="4">${escapeHtml(linksText)}</textarea>
              <button class="submit-btn mt-12" type="submit">Αποθήκευση</button>
              ${hasFile ? `<p class="small mt-8">Αρχείο: <a class="mono" target="_blank" href="${escapeAttr(m.draft_file)}">${escapeHtml(fileName)}</a></p>` : ``}
            </form>
          </section>
          <section class="card">
            <h4>2) Δήλωση εξέτασης</h4>
            <form id="examScheduleForm">
              <input type="hidden" name="action" value="save_schedule">
              <input type="hidden" name="thesisID" value="${escapeAttr(m.thesisID)}">
              <label>Ημερομηνία & Ώρα:</label>
              <input class="w-100" type="datetime-local" name="exam_datetime" value="${m.exam_datetime ? toLocalInput(m.exam_datetime) : ''}">
              <label class="mt-8">Αίθουσα (προαιρετικό):</label>
              <input class="w-100" type="text" name="exam_room" value="${escapeAttr(m.exam_room || '')}">
              <label class="mt-8">Σύνδεσμος σύσκεψης (προαιρετικό):</label>
              <input class="w-100" type="url" name="exam_meeting_url" value="${escapeAttr(m.exam_meeting_url || '')}">
              <button class="submit-btn mt-12" type="submit">Αποθήκευση</button>
            </form>
          </section>
          <section class="card ${data.after_enabled ? '' : 'disabled-block'}">
            <h4>3) Μετά τη βαθμολόγηση</h4>
            ${data.after_enabled ? '' : '<p class="small">Το βήμα αυτό ενεργοποιείται αφού ο επιβλέπων οριστικοποιήσει τη βαθμολόγηση.</p>'}
            <form id="examAfterForm">
              <input type="hidden" name="action" value="save_after">
              <input type="hidden" name="thesisID" value="${escapeAttr(m.thesisID)}">
              <div style="display:flex;align-items:center;gap:8px;">
                <label style="margin:0;">Πρακτικό εξέτασης:</label>
                <button type="button" class="preview-btn" id="reportPreviewBtn" ${data.after_enabled ? '' : 'disabled'} onclick="window.open('praktiko_form.php?thesisID=${encodeURIComponent(m.thesisID)}','_blank','width=800,height=900,scrollbars=yes')">Προβολή</button>
              </div>
              <label class="mt-16">Σύνδεσμος αποθετηρίου (Νημερτής):</label>
              <input class="w-100" type="url" name="repository_url" value="${escapeAttr(m.repository_url || '')}" ${data.after_enabled ? '' : 'disabled'}>
              <button class="submit-btn mt-12" type="submit" ${data.after_enabled ? '' : 'disabled'}>Αποθήκευση</button>
            </form>
          </section>
        </div>
      `;
      return;
    }
    if (data.view==='DONE') {
      const th = data.thesis || {}; const ex = data.exam || {}; const history = data.history || [];
      container.innerHTML = `
        <h3>${escapeHtml(data.title || 'Διαχείριση Διπλωματικής — Ολοκληρωμένη (DONE)')}</h3>
        <div class="done-grid">
          <section class="card">
            <h4>Προβολή Θέματος</h4>
            <div class="kv"><b>Θέμα:</b> ${escapeHtml(th.title || '-')}</div>
            <div class="kv"><b>Περιγραφή:</b> ${escapeHtml(th.description || '-')}</div>
            <div class="kv"><b>Συνημμένο αρχείο:</b> ${th.pdf ? `<a href="${escapeAttr(th.pdf)}" target="_blank">Προβολή ή Λήψη</a>` : '-'}</div>
            <div class="kv"><b>Κατάσταση:</b> ${escapeHtml(th.status || 'DONE')}</div>
            <div class="kv"><b>Επιτροπή:</b></div>
            <ul class="ul">
              ${(th.committee || []).map(c => `<li>${escapeHtml(c)}</li>`).join('') || '<li>-</li>'}
            </ul>
            <div class="kv"><b>Χρόνος από ανάθεση:</b> ${th.days_since_assigned ?? '-'}</div>
          </section>
          <section class="card">
            <h4>Στοιχεία Εξέτασης</h4>
            <div class="kv"><b>Ημ/νία & ώρα:</b> ${escapeHtml(ex.date || '-')}</div>
            <div class="kv"><b>Αίθουσα:</b> ${escapeHtml(ex.room || '-')}</div>
            <div class="kv"><b>Τελικός βαθμός:</b> ${ex.final_grade ?? '-'}</div>
            <div class="kv" style="margin-top:10px;">
              <button class="btn" type="button" onclick="window.open('praktiko_form.php?thesisID=${encodeURIComponent(data.thesisID)}','_blank','width=800,height=900,scrollbars=yes')">Πρακτικό</button>
            </div>
          </section>
          <section class="card" style="grid-column:1 / -1;">
            <h4>Ιστορικό κατάστασης</h4>
            ${history.length ? `<ul class="small">${history.map(h=>`<li>${escapeHtml(h.date)} — ${escapeHtml(h.status)}</li>`).join('')}</ul>` : `<p class="small">Δεν βρέθηκε ιστορικό αλλαγών.</p>`}
          </section>
          <section class="card" style="grid-column:1 / -1;">
            <h4>Αποθετήριο</h4>
            ${data.repository_url ? `<p class="small">Σύνδεσμος: <a target="_blank" href="${escapeAttr(data.repository_url)}">${escapeHtml(data.repository_url)}</a></p>` : `<p class="small">Δεν έχει δηλωθεί σύνδεσμος αποθετηρίου.</p>`}
          </section>
        </div>
      `;
      return;
    }
    container.innerHTML = `
      <h3>${escapeHtml(data.title || 'Διαχείριση Διπλωματικής — Ενεργή')}</h3>
      ${data.html || '<div class="announcements" style="min-height:220px;"></div>'}
    `;
  }

  function escapeHtml(s){ return (s??'').toString().replace(/[&<>"']/g, m=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[m])); }
  function escapeAttr(s){ return escapeHtml(s); }
  function fmtDate(dt){ try { const d=new Date(dt.replace(' ','T')); return d.toLocaleString('el-GR'); } catch { return dt; } }
  function toLocalInput(dt){ try { const d=new Date(dt.replace(' ','T')); const off=d.getTimezoneOffset(); const d2=new Date(d.getTime()-off*60000); return d2.toISOString().slice(0,16); } catch { return ''; } }

  // Init
  loadAnnouncements();
});
