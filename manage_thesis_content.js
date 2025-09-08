// studentdashboard.js – handler για Manage
document.addEventListener('DOMContentLoaded', () => {
  const panel = document.querySelector('.announcements');
  const manageBtn = document.getElementById('managethesesBtn');
  if (!panel || !manageBtn) return;

  const partialUrl = new URL('manage_thesis_content.php', window.location.href).toString();

  manageBtn.addEventListener('click', async (e) => {
    e.preventDefault();
    try {
      const resp = await fetch(partialUrl, { credentials: 'same-origin' });
      panel.innerHTML = resp.ok ? await resp.text() : `<section class="card"><p class="muted">Σφάλμα φόρτωσης (${resp.status}).</p></section>`;
      if (resp.ok) {
        const hook = document.createElement('script');
        hook.src = 'student_manage_thesis.js';
        hook.defer = true;
        document.body.appendChild(hook);
      }
    } catch (e2) {
      panel.innerHTML = '<section class="card"><p class="muted">Σφάλμα φόρτωσης.</p></section>';
      console.error('manage_thesis_content fetch error:', e2);
    }
  });
});
