// studentdashboard.js (updated για Manage)
document.addEventListener('DOMContentLoaded', () => {
  const panel = document.querySelector('.announcements');

  const thesisBtn = document.getElementById('thesisviewBtn');
  if (thesisBtn) thesisBtn.addEventListener('click', () => { window.location.href = 'thesisview.php'; });

  const profileBtn = document.getElementById('studentprofileBtn');
  if (profileBtn) profileBtn.addEventListener('click', () => { window.location.href = 'studentprofile.php'; });

  const manageBtn = document.getElementById('managethesesBtn');
  if (!panel || !manageBtn) return;

  const partialUrl = new URL('manage_thesis_content.php', window.location.href).toString();

  async function loadManage() {
    const resp = await fetch(partialUrl, { credentials: 'same-origin' });
    panel.innerHTML = await resp.text();
    attachManageHandlers();
  }

  function attachManageHandlers() {
    const bindAjax = (id) => {
      const form = document.getElementById(id);
      if (!form) return;
      form.addEventListener('submit', async (e) => {
        e.preventDefault();
        try {
          const fd = new FormData(form);
          const resp = await fetch('student_manage_thesis.php', {
            method: 'POST', credentials: 'same-origin', body: fd
          });
          await resp.json().catch(() => ({}));
          await loadManage();
        } catch (err) {
          alert('Σφάλμα αποθήκευσης.');
          console.error(err);
        }
      });
    };
    bindAjax('inviteForm');
    bindAjax('draftForm');
    bindAjax('examForm');
    bindAjax('libraryForm');
  }

  manageBtn.addEventListener('click', (e) => { e.preventDefault(); loadManage(); });

  const params = new URLSearchParams(window.location.search);
  if (params.get('tab') === 'manage') loadManage();

  const logoutBtn = document.getElementById('logoutBtn');
  if (logoutBtn) logoutBtn.addEventListener('click', () => { window.location.href = 'logout.php'; });
});
