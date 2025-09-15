document.addEventListener('DOMContentLoaded', () => {
  // Sidebar bindings
  const thesisBtn  = document.getElementById('thesisviewBtn');
  if (thesisBtn) {
    thesisBtn.addEventListener('click', (e) => {
      e.preventDefault();
      window.location.href = 'thesisview.php';
    });
    if (thesisBtn.tagName === 'A') thesisBtn.setAttribute('href', 'thesisview.php');
  }

  const profileBtn = document.getElementById('profileBtn');
  if (profileBtn) {
    profileBtn.addEventListener('click', (e) => {
      e.preventDefault();
      window.location.href = 'profile.php';
    });
    if (profileBtn.tagName === 'A') profileBtn.setAttribute('href', 'profile.php');
  }

  const logoutBtn  = document.getElementById('logoutBtn');
  if (logoutBtn) {
    logoutBtn.addEventListener('click', (e) => {
      e.preventDefault();
      window.location.href = 'logout.php';
    });
    if (logoutBtn.tagName === 'A') logoutBtn.setAttribute('href', 'logout.php');
  }

  // Διαχείριση ΔΕ (partial loader)
  const panel = document.querySelector('.announcements');
  const manageBtn = document.getElementById('managethesesBtn');
  if (!panel || !manageBtn) return;

  if (manageBtn.tagName === 'A') manageBtn.setAttribute('href', '#manage');

  const partialUrl = new URL('manage_thesis_content.php', window.location.href).toString();

  async function loadManage() {
    try {
      const resp = await fetch(partialUrl, { credentials: 'same-origin', cache: 'no-store' });
      panel.innerHTML = resp.ok ? await resp.text() : `<section class="card"><p class="muted">Σφάλμα φόρτωσης (${resp.status}).</p></section>`;
      
      if (resp.ok) {
        loadThesisHandlers();
      }
    } catch (e2) {
      panel.innerHTML = '<section class="card"><p class="muted">Σφάλμα φόρτωσης.</p></section>';
      console.error('manage_thesis_content fetch error:', e2);
    }
  }

  function loadThesisHandlers() {
    async function postForm(url, form) {
      try {
        const r = await fetch(url, { method:'POST', body: form, credentials:'same-origin' });
        if (!r.ok) {
          throw new Error(`HTTP ${r.status}: ${r.statusText}`);
        }
        const contentType = r.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
          throw new Error('Server did not return JSON');
        }
        return await r.json();
      } catch (error) {
        console.error('postForm error:', error);
        throw error;
      }
    }

    // Handler για draft form
    const draftForm = document.getElementById('examDraftForm');
    if (draftForm) {
      draftForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const submitBtn = e.target.querySelector('button[type="submit"]');
        
        try {
          submitBtn.disabled = true;
          submitBtn.textContent = 'Αποθήκευση...';
          
          const fd = new FormData(e.target);
          const res = await postForm('thesis_exam_actions.php', fd);
          
          alert(res.message || (res.success ? 'Αποθηκεύτηκε επιτυχώς!' : 'Σφάλμα'));
          
          if (res.success && res.file_uploaded) {
            loadManage();
          }
        } catch (err) {
          console.error('Draft form error:', err);
          alert('Σφάλμα δικτύου: ' + err.message);
        } finally {
          submitBtn.disabled = false;
          submitBtn.textContent = 'Αποθήκευση';
        }
      });
    }

    // Handler για schedule form
    const scheduleForm = document.getElementById('examScheduleForm');
    if (scheduleForm) {
      scheduleForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(e.target);
        try {
          const res = await postForm('thesis_exam_actions.php', fd);
          alert(res.message || (res.success ? 'Αποθηκεύτηκε επιτυχώς!' : 'Σφάλμα'));
        } catch (err) {
          alert('Σφάλμα δικτύου: ' + err.message);
        }
      });
    }

    // Handler για after form
    const afterForm = document.getElementById('examAfterForm');
    if (afterForm) {
      afterForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(e.target);
        try {
          const res = await postForm('thesis_exam_actions.php', fd);
          alert(res.message || (res.success ? 'Αποθηκεύτηκε επιτυχώς!' : 'Σφάλμα'));
        } catch (err) {
          alert('Σφάλμα δικτύου: ' + err.message);
        }
      });
    }

    // Handler για invite form
    const inviteForm = document.getElementById('inviteForm');
    if (inviteForm) {
      inviteForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        try {
          const fd = new FormData(e.target);
          const resp = await fetch('student_manage_thesis_content.php', {
            method: 'POST',
            credentials: 'same-origin',
            body: fd
          });
          const data = await resp.json();
          if (!data.success && data.message) alert(data.message);
          loadManage();
        } catch (err) {
          alert('Σφάλμα αποθήκευσης.');
          console.error(err);
        }
      });
    }
  }

  manageBtn.addEventListener('click', (e) => {
    e.preventDefault();
    loadManage();
  });

  // Αυτόματη φόρτωση tab=manage
  const params = new URLSearchParams(window.location.search);
  if (params.get('tab') === 'manage') loadManage();
});
