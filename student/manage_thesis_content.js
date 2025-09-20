// manage_thesis_content.js – φορτώνει HTML partial της Διαχείρισης ΔΕ και «δένει» φόρμες
document.addEventListener('DOMContentLoaded', () => {
  const panel = document.querySelector('.announcements');
  const manageBtn = document.getElementById('managethesesBtn');
  if (!manageBtn) { console.warn('managethesesBtn not found'); return; }
  if (manageBtn.tagName === 'A') manageBtn.setAttribute('href', '#manage');

  const PARTIAL_URL = 'manage_thesis_content.php';

  manageBtn.addEventListener('click', async (e) => {
    e.preventDefault();
    await loadManage();
  });

  async function loadManage() {
    try {
      const resp = await fetch(PARTIAL_URL, { credentials: 'same-origin', cache: 'no-store' });
      const html = await resp.text();
      if (!panel) return;
      panel.innerHTML = resp.ok ? html
        : `<section class="card"><p class="muted">Σφάλμα φόρτωσης (${resp.status}).</p></section>`;
      if (resp.ok) bindHandlers();   // Δένουμε τους handlers τώρα που το HTML υπάρχει στο DOM
    } catch (e2) {
      console.error('manage_thesis_content fetch error:', e2);
      if (panel) panel.innerHTML = '<section class="card"><p class="muted">Σφάλμα φόρτωσης.</p></section>';
    }
  }

  function bindHandlers() {
    // 1) Πρόσκληση (ASSIGNED)
    const inviteForm = document.getElementById('inviteForm');
    if (inviteForm) {
      inviteForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        try {
          const fd = new FormData(inviteForm);               // περιέχει action=invite
          const r = await fetch('student_manage_thesis_content.php', {
            method: 'POST', credentials: 'same-origin', body: fd, headers: { 'Accept':'application/json' }
          });
          const ct = r.headers.get('content-type') || '';
          const data = ct.includes('application/json') ? await r.json() : { success:false, message:'Μη έγκυρη απόκριση' };
          if (!data.success && data.message) alert(data.message);
          await loadManage();
        } catch (err) {
          console.error(err);
          alert('Σφάλμα αποθήκευσης.');
        }
      }, { once:true });
    }

    // 2) Draft (EXAM)
    const draftForm = document.getElementById('examDraftForm');
    if (draftForm) {
      draftForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const submitBtn = draftForm.querySelector('button[type="submit"]');
        try {
          if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Αποθήκευση...'; }
          const fd = new FormData(draftForm);                // πρέπει να έχει action=save_draft & thesisID
          const r = await fetch('thesis_exam_actions.php', {
            method:'POST', credentials:'same-origin', body: fd, headers:{ 'Accept':'application/json' }
          });
          const ct = r.headers.get('content-type') || '';
          if (!ct.includes('application/json')) throw new Error('Server did not return JSON');
          const data = await r.json();
          alert(data.message || (data.success ? 'Αποθηκεύτηκε επιτυχώς!' : 'Σφάλμα'));
          if (data.success) await loadManage();              // επαναφόρτωση για να εμφανιστεί το αρχείο/links
        } catch (err) {
          console.error(err);
          alert('Σφάλμα δικτύου: ' + err.message);
        } finally {
          if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Αποθήκευση'; }
        }
      }, { once:true });
    }

    // 3) Schedule (EXAM)
    const scheduleForm = document.getElementById('examScheduleForm');
    if (scheduleForm) {
      scheduleForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        try {
          const fd = new FormData(scheduleForm);            // action=save_schedule & thesisID
          const r = await fetch('thesis_exam_actions.php', {
            method:'POST', credentials:'same-origin', body: fd, headers:{ 'Accept':'application/json' }
          });
          const ct = r.headers.get('content-type') || '';
          if (!ct.includes('application/json')) throw new Error('Server did not return JSON');
          const data = await r.json();
          alert(data.message || (data.success ? 'Αποθηκεύτηκε επιτυχώς!' : 'Σφάλμα'));
          if (data.success) await loadManage();             // ώστε να δούμε το νέο exam_datetime/room/url
        } catch (err) {
          console.error(err);
          alert('Σφάλμα δικτύου: ' + err.message);
        }
      }, { once:true });
    }

    // 4) After (EXAM)
    const afterForm = document.getElementById('examAfterForm');
    if (afterForm) {
      afterForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        try {
          const fd = new FormData(afterForm);               // action=save_after & thesisID
          const r = await fetch('thesis_exam_actions.php', {
            method:'POST', credentials:'same-origin', body: fd, headers:{ 'Accept':'application/json' }
          });
          const ct = r.headers.get('content-type') || '';
          if (!ct.includes('application/json')) throw new Error('Server did not return JSON');
          const data = await r.json();
          alert(data.message || (data.success ? 'Αποθηκεύτηκε επιτυχώς!' : 'Σφάλμα'));
          if (data.success) await loadManage();
        } catch (err) {
          console.error(err);
          alert('Σφάλμα δικτύου: ' + err.message);
        }
      }, { once:true });
    }
  }
});
