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

    resultDiv.innerHTML = `<p style="color: ${data.success ? 'green' : 'red'}">${data.message}</p>`;
  } catch (error) {
    resultDiv.innerHTML = `<p style="color: red">Error: Could not submit the form.</p>`;
    console.error("Error:", error);
  }
});

document.getElementById('edit-form').addEventListener('click', function() {
  const thesisID = this.getAttribute('data-thesis-id');
  window.location.assign(`thesisedit.php?id=${thesisID}`);
});


document.querySelectorAll('.delete-btn').forEach(btn => {
  btn.addEventListener('click', async function() {
    const thesisID = this.getAttribute('data-thesis-id');
    if (!confirm('Are you sure you want to delete this thesis?')) return;
    const formData = new FormData();
    formData.append("id", thesisID);
    const res = await fetch('thesisdelete.php', {
      method: "POST",
      body: formData,
      credentials: 'same-origin'
    });
    const data = await res.json();
    alert(data.message);
    if (data.success) {
      window.location.reload();
    }
  });
});

