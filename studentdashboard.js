// studentdashboard.js (clean routing + no-store for partials)
document.addEventListener('DOMContentLoaded', () => {
  // Sidebar bindings (προτεραιότητα σε JS routing)
  const thesisBtn  = document.getElementById('thesisviewBtn');
  if (thesisBtn) {
    thesisBtn.addEventListener('click', (e) => {
      e.preventDefault();
      window.location.href = 'thesisview.php';
    });
    // καθάρισε τυχόν παλιό href
    if (thesisBtn.tagName === 'A') thesisBtn.setAttribute('href', 'thesisview.php');
  }

  const profileBtn = document.getElementById('profileBtn');
  if (profileBtn) {
    profileBtn.addEventListener('click', (e) => {
      e.preventDefault();
      window.location.href = 'profile.php'; // ΠΑΝΤΑ στο profile.php
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

  // Βεβαιώσου ότι το href του κουμπιού δείχνει στη σελίδα όπου υπάρχει το panel
  if (manageBtn.tagName === 'A') manageBtn.setAttribute('href', '#manage');

  const partialUrl = new URL('manage_thesis_content.php', window.location.href).toString();

  async function loadManage() {
    const resp = await fetch(partialUrl, { credentials: 'same-origin', cache: 'no-store' });
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
          method: 'POST',
          credentials: 'same-origin',
          body: fd,
          cache: 'no-store'
        });
        const data = await resp.json();
        await loadManage(); // ανανέωση partial
        if (!data.success && data.message) alert(data.message);
      } catch (err) {
        alert('Σφάλμα αποθήκευσης.');
        console.error(err);
      }
    });
  }

  manageBtn.addEventListener('click', (e) => {
    e.preventDefault();
    loadManage();
  });

  // Αυτόματη φόρτωση tab=manage
  const params = new URLSearchParams(window.location.search);
  if (params.get('tab') === 'manage') loadManage();
});
