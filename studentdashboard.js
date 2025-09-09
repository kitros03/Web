// studentdashboard.js (τελικό)
document.addEventListener('DOMContentLoaded', () => {
  // Sidebar bindings
  const thesisBtn = document.getElementById('thesisviewBtn');
  if (thesisBtn) thesisBtn.addEventListener('click', (e) => { e.preventDefault(); window.location.href = 'thesisview.php'; });

  const profileBtn = document.getElementById('studentprofileBtn');
  if (profileBtn) profileBtn.addEventListener('click', (e) => { e.preventDefault(); window.location.href = 'studentprofile.php'; });

  const logoutBtn = document.getElementById('logoutBtn');
  if (logoutBtn) logoutBtn.addEventListener('click', (e) => { e.preventDefault(); window.location.href = 'logout.php'; });

  // Διαχείριση ΔΕ
  const panel = document.querySelector('.announcements');
  const manageBtn = document.getElementById('managethesesBtn');
  if (!panel || !manageBtn) return;

  const partialUrl = new URL('manage_thesis_content.php', window.location.href).toString();

  async function loadManage() {
    const resp = await fetch(partialUrl, { credentials: 'same-origin' });
    panel.innerHTML = await resp.text();
    attachManageHandlers();
  }

  function attachManageHandlers() {
    const form = document.getElementById('inviteForm');
    if (!form) return;
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      try {
        const fd = new FormData(form);
        const resp = await fetch('student_manage_thesis_content.php', {
          method: 'POST', credentials: 'same-origin', body: fd
        });
        const data = await resp.json();
        // ανεξαρτήτως μηνύματος, ξαναφορτώνουμε το partial για να φανεί η νέα γραμμή
        await loadManage();
        if (!data.success && data.message) alert(data.message);
      } catch (err) {
        alert('Σφάλμα αποθήκευσης.');
        console.error(err);
      }
    });
  }

  manageBtn.addEventListener('click', (e) => { e.preventDefault(); loadManage(); });

  // Προαιρετικά: φόρτωσε αυτόματα αν υπάρχει ?tab=manage
  const params = new URLSearchParams(window.location.search);
  if (params.get('tab') === 'manage') loadManage();
});
