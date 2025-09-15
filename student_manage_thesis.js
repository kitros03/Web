async function postForm(url, form) {
  const r = await fetch(url, { method:'POST', body: form, credentials:'same-origin' });
  return r.json();
}

document.getElementById('examDraftForm')?.addEventListener('submit', async (e)=>{
  e.preventDefault();
  const fd = new FormData(e.target);
  const res = await postForm('thesis_exam_actions.php', fd);
  alert(res.message || (res.success ? 'Αποθηκεύτηκε' : 'Σφάλμα'));
});

document.getElementById('examScheduleForm')?.addEventListener('submit', async (e)=>{
  e.preventDefault();
  const fd = new FormData(e.target);
  const res = await postForm('thesis_exam_actions.php', fd);
  alert(res.message || (res.success ? 'Αποθηκεύτηκε' : 'Σφάλμα'));
});

document.getElementById('examAfterForm')?.addEventListener('submit', async (e)=>{
  e.preventDefault();
  const fd = new FormData(e.target);
  const res = await postForm('thesis_exam_actions.php', fd);
  alert(res.message || (res.success ? 'Αποθηκεύτηκε' : 'Σφάλμα'));
});

// Handler για προσκλήσεις (αν υπάρχει)
document.getElementById('inviteForm')?.addEventListener('submit', async (e) => {
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
    // Reload το panel για να δεις τις νέες προσκλήσεις
    const manageBtn = document.getElementById('managethesesBtn');
    if (manageBtn) manageBtn.click();
  } catch (err) {
    alert('Σφάλμα αποθήκευσης.');
    console.error(err);
  }
});
