document.getElementById('backBtn').onclick = () => {
    window.location.href = 'teacherdashboard.php';
};

document.getElementById('backBtn2').onclick = () => {
    window.location.href = 'thesiscreation.php';
};

document.addEventListener('DOMContentLoaded', async () => {
    const params = new URLSearchParams(window.location.search);
    const thesisID = params.get('id');
    const resultDiv = document.getElementById('result');
    const titleInput = document.getElementById('title');
    const descTextarea = document.getElementById('description');
    const currentPdfStatusDiv = document.getElementById('currentPdfStatus');
    const removePdfCheckbox = document.getElementById('removePdfCheckbox');
    const pdfSection = document.getElementById('pdfSection');

    if (!thesisID) {
        resultDiv.innerHTML = '<p style="color:red;">No Thesis ID specified.</p>';
        return;
    }

    //load data
    try {
        const response = await fetch(`thesisedit.php?id=${encodeURIComponent(thesisID)}&ajax=1`, {
            credentials: 'same-origin',
        });
        const data = await response.json();
        if (data.success) {
            const thesis = data.thesis;
            titleInput.value = thesis.title;
            descTextarea.value = thesis.th_description;
            if (thesis.pdf_description && thesis.pdf_description.trim() !== '') {
                currentPdfStatusDiv.innerHTML = `<a href="${thesis.pdf_description}" target="_blank" style="color:#22316c;font-weight:500; margin-right:15px;">View Current PDF</a>`;
                removePdfCheckbox.checked = false;
                removePdfCheckbox.parentElement.style.display = 'inline-block';
            } else {
                currentPdfStatusDiv.textContent = 'No PDF currently uploaded.';
                removePdfCheckbox.parentElement.style.display = 'none';
            }
        } else {
            resultDiv.innerHTML = `<p style="color:red;">${data.message}</p>`;
        }
    } catch (error) {
        resultDiv.innerHTML = '<p style="color:red;">Failed to load thesis data.</p>';
        console.error(error);
    }

    //form submit
    const form = document.getElementById('thesisForm');
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        resultDiv.innerHTML = '<span style="color:#22316c;">Saving changes…</span>';

        const formData = new FormData(form);
        const url = `thesisedit.php?id=${encodeURIComponent(thesisID)}`;

        try {
            const response = await fetch(url, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
            });
            const data = await response.json();

            if (data.success) {
                resultDiv.innerHTML = `<p style="color: green;">${data.message}</p>`;
                setTimeout(() => window.location.reload(), 1000);
            } else {
                resultDiv.innerHTML = `<p style="color: red;">${data.message}</p>`;
            }
        } catch (error) {
            resultDiv.innerHTML = '<p style="color: red;">Error: Could not save changes.</p>';
        }
    });
});
