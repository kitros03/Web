document.getElementById('backBtn').onclick = () => {
  window.location.href = 'teacherdashboard.php';
};

document.getElementById('thesisForm').addEventListener('submit', async function(e) {
  e.preventDefault();

  const form = document.getElementById('thesisForm');
  const formData = new FormData(form);
  const resultDiv = document.getElementById('result');

  try {
    const response = await fetch('thesiscreation.php', {
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
