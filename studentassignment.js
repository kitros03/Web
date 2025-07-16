document.getElementById('assignmentForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  const form = document.getElementById('assignmentForm');
  const formData = new FormData(form);
  const resultDiv = document.getElementById('result');

  try {
    const response = await fetch('studentassignment.php', {
      method: 'POST',
      credentials: 'same-origin',
      body: formData
    });
    const data = await response.json();
    resultDiv.innerHTML = `<p>${data.message}</p>`;

    if (data.success) {
      setTimeout(() => window.location.reload(), 1000);
    }
  } catch (error) {
    resultDiv.innerHTML = `<p>Error: Could not submit the form.</p>`;
    console.error("Error:", error);
  }
});

document.addEventListener('DOMContentLoaded', function () {
  const removeForms = document.querySelectorAll('.remove-form');

  removeForms.forEach(function (form) {
    form.addEventListener('submit', async function (e) {
      e.preventDefault();

      const thesisID = form.getAttribute('data-thesis-id');
      if (!confirm('Are you sure you want to remove this assignment?')) return;

      const formData = new FormData();
      formData.append('remove', '1');
      formData.append('thesisID', thesisID);

      try {
        const response = await fetch('studentassignment.php', {
          method: 'POST',
          credentials: 'same-origin',
          body: formData
        });
        const result = await response.text();
        alert('Assignment removed successfully.');
        window.location.reload();

        const row = form.closest('tr');
        if (row) row.remove();
      } catch (error) {
        alert('Error: Could not remove assignment.');
        console.error('Removal error:', error);
      }
    });
  });
});
