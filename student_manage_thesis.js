async function fetchJSON(url, opts={}) {
  const r = await fetch(url, { credentials:'same-origin', ...opts });
  if (!r.ok) throw new Error('HTTP '+r.status);
  return r.json();
}

function renderAssigned(container, data){
  const { title, note, teachers, inviteTable } = data;
  container.innerHTML = `
    <h3>${title}</h3>
    <p>${note}</p>
    <form id="inviteForm" class="form">
      <input type="hidden" name="action" value="invite">
      <label for="teacherUsername">Διδάσκων (username)</label>
      <select id="teacherUsername" name="teacherUsername" required>
        <option value="">Επέλεξε διδάσκοντα</option>
        ${teachers.map(t=>`<option value="${t.val}">${t.label}</option>`).join('')}
      </select>
      <button type="submit" class="submit-btn">Αποστολή Πρόσκλησης</button>
    </form>
    <h3>Προσκλήσεις Επιτροπής</h3>
    <div id="inviteTable">${inviteTable}</div>
  `;

  document.getElementById('inviteForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    try {
      const fd = new FormData(e.target);
      const resp = await fetchJSON('student_manage_thesis_content.php', { method:'POST', body: fd });
      if (!resp.success && resp.message) alert(resp.message);
      if (resp.inviteTable) {
        document.getElementById('inviteTable').innerHTML = resp.inviteTable;
      }
    } catch (err) {
      alert('Σφάλμα αποθήκευσης.');
      console.error(err);
    }
  });
}

function renderActive(container, data){
  const { title, html } = data;
  container.innerHTML = `
    <h3>${title}</h3>
    <p>Η πτυχιακή είναι σε κατάσταση <strong>Ενεργή</strong>. Αναμένεται ο επιβλέπων/τριμελής να θέσουν την κατάσταση <strong>Υπό Εξέταση (EXAM)</strong>.</p>
    ${html || '<div class="announcements" style="min-height:220px;"></div>'}
  `;
}

function renderExam(container, data){
  const { title, meta, after_enabled } = data;
  const linksText = (meta.external_links || []).join('\n');
  const hasFile = meta.has_file;
  const fileName = meta.draft_file ? meta.draft_file.split('/').pop() : '';
  const fileSizeHtml = ''; // προαιρετικό async HEAD για μέγεθος

  container.innerHTML = `
    <h3>${title}</h3>
    <style>
      .grid-2 { display:grid; grid-template-columns: 1fr 1fr; gap:16px; }
      .card { background:#d1d1d1; padding:16px; border-radius:6px; }
      .w-100 { width:100%; }
      .mt-8{margin-top:8px;} .mt-12{margin-top:12px;} .mt-16{margin-top:16px;}
      .small{font-size:13px;} .mono{font-family:ui-monospace,Menlo,monospace;}
      .disabled-block { opacity:.6; pointer-events:none; }
      .preview-btn { background:#4CAF50; color:white; border:none; padding:8px 16px; border-radius:4px; cursor:pointer; }
      .preview-btn:hover { background:#45a049; }
      .preview-btn:disabled { background:#cccccc; cursor:not-allowed; }
    </style>
    <div class="grid-2 mt-16">
      <section class="card">
        <h4>1) Πρόχειρο κείμενο & σύνδεσμοι</h4>
        <p class="small">Ανέβασε αρχείο και πρόσθεσε συνδέσμους (Drive, YouTube κ.λπ.).</p>
        <form id="examDraftForm" enctype="multipart/form-data">
          <input type="hidden" name="action" value="save_draft">
          <input type="hidden" name="thesisID" value="${meta.thesisID}">
          <label>Πρόχειρο αρχείο:</label>
          <input class="w-100" type="file" name="draft_file" accept=".pdf,.doc,.docx,.ppt,.pptx">
          <label class="mt-8">Σύνδεσμοι (ένας ανά γραμμή):</label>
          <textarea class="w-100" name="external_links" rows="4">${linksText}</textarea>
          <button class="submit-btn mt-12" type="submit">Αποθήκευση</button>
          ${hasFile ? `<p class="small mt-8">Αρχείο: <a class="mono" target="_blank" href="${meta.draft_file}">${fileName}</a> ${fileSizeHtml}</p>` : ``}
        </form>
      </section>

      <section class="card">
        <h4>2) Δήλωση εξέτασης</h4>
        <form id="examScheduleForm">
          <input type="hidden" name="action" value="save_schedule">
          <input type="hidden" name="thesisID" value="${meta.thesisID}">
          <label>Ημερομηνία & Ώρα:</label>
          <input class="w-100" type="datetime-local" name="exam_datetime" value="${meta.exam_datetime && meta.exam_datetime!=='0000-00-00 00:00:00' ? new Date(meta.exam_datetime.replace(' ','T')).toISOString().slice(0,16) : ''}">
          <label class="mt-8">Αίθουσα (προαιρετικό):</label>
          <input class="w-100" type="text" name="exam_room" value="${meta.exam_room || ''}">
          <label class="mt-8">Σύνδεσμος σύσκεψης (προαιρετικό):</label>
          <input class="w-100" type="url" name="exam_meeting_url" value="${meta.exam_meeting_url || ''}">
          <button class="submit-btn mt-12" type="submit">Αποθήκευση</button>
        </form>
      </section>

      <section class="card ${after_enabled ? '' : 'disabled-block'}">
        <h4>3) Μετά τη βαθμολόγηση</h4>
        ${after_enabled ? '' : '<p class="small">Το βήμα αυτό ενεργοποιείται αφού ο επιβλέπων οριστικοποιήσει τη βαθμολόγηση.</p>'}
        <form id="examAfterForm">
          <input type="hidden" name="action" value="save_after">
          <input type="hidden" name="thesisID" value="${meta.thesisID}">
          <div style="display:flex; align-items:center; gap:8px;">
            <label style="margin:0;">Πρακτικό εξέτασης:</label>
            <button type="button" class="preview-btn" id="reportPreviewBtn" ${after_enabled ? '' : 'disabled'} onclick="window.open('praktiko_form.php?thesisID=${meta.thesisID}','_blank','width=800,height=900,scrollbars=yes')">Προβολή</button>
          </div>
          <label class="mt-16">Σύνδεσμος αποθετηρίου (Νημερτής):</label>
          <input class="w-100" type="url" name="repository_url" value="${meta.repository_url || ''}" ${after_enabled ? '' : 'disabled'}>
          <button class="submit-btn mt-12" type="submit" ${after_enabled ? '' : 'disabled'}>Αποθήκευση</button>
        </form>
      </section>

      <section class="card">
        <h4>Χρήσιμες πληροφορίες</h4>
        <ul class="small">
          <li>Οι ενέργειες είναι ορατές/διαθέσιμες στον/στη φοιτητή/τρια και στα μέλη της τριμελούς επιτροπής.</li>
          <li>Το 3ο βήμα ενεργοποιείται αυτόματα μόλις οριστικοποιηθεί η βαθμολόγηση.</li>
        </ul>
      </section>
    </div>
  `;

  document.getElementById('examDraftForm')?.addEventListener('submit', async (e)=>{
    e.preventDefault();
    const fd = new FormData(e.target);
    const res = await fetchJSON('thesis_exam_actions.php', { method:'POST', body: fd });
    alert(res.message || (res.success ? 'Αποθηκεύτηκε' : 'Σφάλμα'));
  });

  document.getElementById('examScheduleForm')?.addEventListener('submit', async (e)=>{
    e.preventDefault();
    const fd = new FormData(e.target);
    const res = await fetchJSON('thesis_exam_actions.php', { method:'POST', body: fd });
    alert(res.message || (res.success ? 'Αποθηκεύτηκε' : 'Σφάλμα'));
  });

  document.getElementById('examAfterForm')?.addEventListener('submit', async (e)=>{
    e.preventDefault();
    const fd = new FormData(e.target);
    const res = await fetchJSON('thesis_exam_actions.php', { method:'POST', body: fd });
    alert(res.message || (res.success ? 'Αποθηκεύτηκε' : 'Σφάλμα'));
  });
}

function renderDone(container, data){
  const { title, thesis, exam, repository_url, history, thesisID } = data;
  container.innerHTML = `
    <h3>${title}</h3>
    <style>
      .done-grid { display:grid; grid-template-columns: 1fr 1fr; gap:16px; }
      .card { background:#d1d1d1; padding:16px; border-radius:6px; }
      .small{font-size:13px;}
      .btn { background:#4CAF50; color:#fff; border:0; border-radius:4px; padding:8px 14px; cursor:pointer; }
      .btn:hover { background:#3e9444; }
      .muted { color:#333; }
      .kv { margin: 6px 0; }
      .kv b { display:inline-block; min-width:120px; }
      .ul { margin:6px 0 0 18px; }
    </style>
    <div class="done-grid">
      <section class="card">
        <h4>Προβολή Θέματος</h4>
        <div class="kv"><b>Θέμα:</b> ${thesis.title}</div>
        <div class="kv"><b>Περιγραφή:</b> ${thesis.description}</div>
        <div class="kv">
          <b>Συνημμένο αρχείο:</b>
          ${thesis.pdf ? `<a href="${thesis.pdf}" target="_blank">Προβολή ή Λήψη</a>` : '-'}
        </div>
        <div class="kv"><b>Κατάσταση:</b> ${thesis.status}</div>
        <div class="kv"><b>Επιτροπή:</b></div>
        <ul class="ul">
          ${(thesis.committee && thesis.committee.length ? thesis.committee.map(c=>`<li>${c}</li>`).join('') : '<li>-</li>')}
        </ul>
        <div class="kv"><b>Χρόνος από ανάθεση:</b> ${thesis.days_since_assigned ?? '-'}</div>
      </section>

      <section class="card">
        <h4>Στοιχεία Εξέτασης</h4>
        <div class="kv"><b>Ημ/νία & ώρα:</b> ${exam.date}</div>
        <div class="kv"><b>Αίθουσα:</b> ${exam.room}</div>
        <div class="kv"><b>Τελικός βαθμός:</b> ${exam.final_grade ?? '-'}</div>
        <div class="kv" style="margin-top:10px;">
          <button class="btn" type="button" onclick="window.open('praktiko_form.php?thesisID=${thesisID}','_blank','width=800,height=900,scrollbars=yes')">Πρακτικό</button>
        </div>
      </section>

      <section class="card" style="grid-column:1 / -1;">
        <h4>Ιστορικό κατάστασης</h4>
        ${history && history.length ? `<ul class="small">${history.map(h=>`<li>${h.date} — ${h.status}</li>`).join('')}</ul>` : `<p class="small">Δεν βρέθηκε ιστορικό αλλαγών.</p>`}
      </section>

      <section class="card" style="grid-column:1 / -1;">
        <h4>Αποθετήριο</h4>
        ${repository_url ? `<p class="small">Σύνδεσμος: <a target="_blank" href="${repository_url}">${repository_url}</a></p>` : `<p class="small">Δεν έχει δηλωθεί σύνδεσμος αποθετηρίου.</p>`}
      </section>
    </div>
  `;
}

export async function loadStudentManageThesis(containerSelector){
  const root = document.querySelector(containerSelector);
  if (!root) return;
  try {
    const data = await fetchJSON('student_manage_thesis_content.php');
    if (data.view === 'ASSIGNED') return renderAssigned(root, data);
    if (data.view === 'EXAM') return renderExam(root, data);
    if (data.view === 'DONE') return renderDone(root, data);
    return renderActive(root, data);
  } catch (e) {
    root.innerHTML = `<p>Σφάλμα φόρτωσης περιεχομένου.</p>`;
    console.error(e);
  }
}

// Προαιρετικά auto-init
document.addEventListener('DOMContentLoaded', ()=> {
  // Καλέστε loadStudentManageThesis('#studentManageThesisRoot') από την σελίδα όπου θα τοποθετηθεί το panel
});
