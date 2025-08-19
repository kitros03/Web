document.addEventListener('DOMContentLoaded', () => {
  const backBtn = document.getElementById('backBtn');
  if (backBtn) {
    backBtn.addEventListener('click', () => {
      window.location.href = 'teacherdashboard.php';
    });
  }

  const form = document.getElementById('unassignForm');
  if (!form) return;

  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    const thesisID = form.getAttribute('data-thesis-id') || form.querySelector('[name="thesisID"]')?.value;
    if (!thesisID) {
      alert('Missing thesisID for this form.');
      return;
    }

    if (!confirm('Are you sure you want to remove this assignment?')) return;

    const formData = new FormData(form);
    formData.set('remove', '1');
    formData.set('thesisID', thesisID);

    try {
      const response = await fetch('studentassignment.php', {
        method: 'POST',
        credentials: 'same-origin',
        body: formData
      });
      if (!response.ok) throw new Error(`HTTP ${response.status}`);
      const result = await response.text();

      alert('Assignment removed successfully.');
    window.location.href = `viewtheses.php`;
    } catch (error) {
      alert('Error: Could not remove assignment.');
      console.error('Removal error:', error);
    }
  });
});
