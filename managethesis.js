document.getElementById('backBtn').onclick = () => {
  window.location.href = 'teacherdashboard.php';
};

document.addEventListener('DOMContentLoaded', function() {
    function goBack() { window.location.href = 'thesiscreation.php'; }
    const backBtn2 = document.getElementById('backBtn2'); // the added back button
    if(backBtn2) backBtn2.addEventListener('click', goBack);

    const form = document.getElementById('thesisForm');
    const resultDiv = document.getElementById('result');
    if(form) {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            resultDiv.innerHTML = '<span style="color:#22316c;">Saving changes…</span>';
            const formData = new FormData(form);
            const params = new URLSearchParams(window.location.search);
            const url = "managethesis.php?id=" + encodeURIComponent(params.get('id'));
            try {
                const response = await fetch(url, {
                    method: "POST",
                    body: formData,
                    credentials: 'same-origin'
                });
                const data = await response.json();
                if(data.success) {
                    resultDiv.innerHTML = `<p>${data.message}</p>`;
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    resultDiv.innerHTML = `<p>${data.message}</p>`;
                }
            } catch (err) {
                resultDiv.innerHTML = '<p>Error: Could not save changes.</p>';
            }
        });
    }
});
