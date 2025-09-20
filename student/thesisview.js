
function renderThesis(j) {
  const box = document.getElementById('thesis-details');
  if (!box) return;
  const committee = (j.committee || []).filter(Boolean);
  const sup = j.supervisor_name || '—';
  box.innerHTML = `
    <h3>${j.title ?? '-'}</h3>
    <p><strong>Περιγραφή:</strong> ${j.desc ?? '-'}</p>
    <p><strong>Συνημμένο αρχείο:</strong> ${j.file ? `<a href="${j.file}" target="_blank">Προβολή ή Λήψη</a>` : '—'}</p>
    <p><strong>Κατάσταση:</strong> ${j.status ?? '-'}</p>
    <p><strong>Επιβλέπων:</strong></p>
    <ul><li>${sup}</li></ul>
    <p><strong>Επιτροπή:</strong></p>
    ${committee.length ? `<ul>${committee.map(n=>`<li>${n}</li>`).join('')}</ul>` : '<ul><li>—</li></ul>'}
    <p><strong>Χρόνος από ανάθεση:</strong> ${j.days_since_assignment ?? '-'}</p>
  `;
}

document.addEventListener('DOMContentLoaded', async () => {
  async function loadDetails() {
    const r = await fetch('thesis_details.php', { credentials: 'same-origin' });
    const j = await r.json();
    if (j && j.success) renderThesis(j);
  }
  await loadDetails();
  const tick = setInterval(loadDetails, 10000);
  setTimeout(()=>clearInterval(tick), 180000);
});
