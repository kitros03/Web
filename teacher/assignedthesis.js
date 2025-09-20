document.addEventListener('DOMContentLoaded', () => {
    const backBtn = document.getElementById('backBtn');
    const unassignForm = document.getElementById('unassignForm');
    const committeeTableBody = document.querySelector('#committeeTable tbody');
    const invitationList = document.getElementById('invitationList');
    const thesisIDElem = document.getElementById('thesisID');
    const thesisID = thesisIDElem ? thesisIDElem.value : null;

    backBtn?.addEventListener('click', () => {
        window.location.href = 'teacherdashboard.php';
    });

    async function loadData() {
        if (!thesisID) {
            alert('Το ID του θέματος δεν είναι διαθέσιμο.');
            return;
        }
        try {
            const res = await fetch(`assignedthesis.php?thesisID=${encodeURIComponent(thesisID)}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await res.json();
            if (!data.success) {
                alert(data.message || 'Αποτυχία φόρτωσης δεδομένων');
                return;
            }

            if (data.committee && Object.keys(data.committee).length > 0) {
                const m1 = data.committee.member1Name || 'Ν/Α.';
                const m2 = data.committee.member2Name || 'Ν/Α.';
                committeeTableBody.innerHTML = `<tr><td>${escapeHtml(m1)}</td><td>${escapeHtml(m2)}</td></tr>`;
            } else {
                committeeTableBody.innerHTML = `<tr><td colspan="2">Δεν υπάρχουν μέλη.</td></tr>`;
            }

            if (data.invitations && data.invitations.length > 0) {
                invitationList.innerHTML = '';
                data.invitations.forEach(inv => {
                    let responseHtml = inv.response ?
                        `${escapeHtml(inv.response)} ${escapeHtml(inv.responseDate)}` :
                        '<p>Εκκρεμεί απάντηση</p>';
                    const item = document.createElement('li');
                    item.innerHTML = `${escapeHtml(inv.receiverName)} - ${responseHtml}`;
                    invitationList.appendChild(item);
                });
            } else {
                invitationList.innerHTML = '<p>Δεν υπάρχουν προσκλήσεις.</p>';
            }

            if (data.teacherId && data.committee && data.committee.supervisor == data.teacherId) {
                unassignForm.style.display = 'block';
            } else {
                unassignForm.style.display = 'none';
            }
        } catch (err) {
            alert('Σφάλμα φόρτωσης δεδομένων');
            console.error(err);
        }
    }

    unassignForm?.addEventListener('submit', async e => {
        e.preventDefault();
        if (!confirm('Σίγουρα θέλετε να αναιρέσετε την ανάθεση θέματος;')) return;

        const formData = new FormData(unassignForm);
        formData.append('remove', '1');

        if (!formData.get('thesisID')) {
            alert('Απαιτείται το ID του θέματος για την ανάθεση.');
            return;
        }

        try {
            const res = await fetch('assignedthesis.php', {
                method: 'POST',
                credentials: 'same-origin',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const result = await res.json();
            alert(result.message);
            if (result.success) {
                window.location.href = 'viewtheses.php';
            }
        } catch (error) {
            alert('Αποτυχία αναίρεσης.');
            console.error(error);
        }
    });

    function escapeHtml(text) {
        if (!text) return '';
        return text.replace(/&/g, "&amp;")
                   .replace(/</g, "&lt;")
                   .replace(/>/g, "&gt;")
                   .replace(/"/g, "&quot;")
                   .replace(/'/g, "&#039;");
    }

    loadData();
});
