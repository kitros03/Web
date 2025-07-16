document.getElementById('invitationForm').addEventListener('submit', async function(e) {
  e.preventDefault();

  const form = document.getElementById('invitationForm');
  const formData = new FormData(form);
  const resultDiv = document.querySelector('.result');

  try {
    const response = await fetch('committeeinvitations.php', {
      method: 'POST',
      credentials: 'same-origin',
      body: formData
    });

    const data = await response.json();

    // Show message
    resultDiv.innerHTML = `<p style="color: ${data.success ? 'green' : 'red'}">${data.message}</p>`;
  } catch (error) {
    resultDiv.innerHTML = `<p style="color: red">Error: Could not submit the form.</p>`;
    console.error("Error:", error);
  }
});