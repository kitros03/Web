document.getElementById('backBtn').onclick = () => {
    window.location.href = 'teacherdashboard.php';
};

// event listener για την υποβολη της φορμας
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

        if (data.success) {
            await loadTheses();
            form.reset();
        }
    } catch (error) {
        resultDiv.innerHTML = `<p style="color: red">Error: Could not submit the form.</p>`;
        console.error("Error:", error);
    }
});

// φορτωση δεδομενων
async function loadTheses() {
    try {
        const response = await fetch('thesiscreation.php?ajax=1', {
            credentials: 'same-origin'
        });
        const theses = await response.json();

        const tableBody = document.querySelector('#ajaxThesesTable tbody');
        const noThesisMsg = document.getElementById('noThesisMsg');
        tableBody.innerHTML = '';
        noThesisMsg.textContent = '';

        if (!theses.length) {
            noThesisMsg.textContent = 'No theses found.';
            return;
        }

        theses.forEach(thesis => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${thesis.thesisID}</td>
                <td>${thesis.title}</td>
                <td>${thesis.th_description}</td>
                <td>${thesis.pdf_description ? `<a href="${thesis.pdf_description}" target="_blank">View PDF</a>` : 'Ν/Α'}</td>
                <td><button class="edit-btn" type="button" data-thesis-id="${thesis.thesisID}">Επεξεργασία</button></td>
            `;
            tableBody.appendChild(row);
        });

        document.querySelectorAll('.edit-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const thesisID = this.getAttribute('data-thesis-id');
                window.location.assign(`thesisedit.php?id=${thesisID}`);
            });
        });
    } catch (error) {
        console.error('AJAX loading error:', error);
    }
}

document.addEventListener('DOMContentLoaded', loadTheses);
