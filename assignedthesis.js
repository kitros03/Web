document.addEventListener('DOMContentLoaded', () => {
    const backBtn = document.getElementById('backBtn');
    const unassignForm = document.getElementById('unassignForm');
    const committeeTableBody = document.querySelector('#committeeTable tbody');
    const invitationList = document.getElementById('invitationList');
    const thesisID = document.getElementById('thesisID').value;

    backBtn?.addEventListener('click', () => {
        window.location.href = 'teacherdashboard.php';
    });

    async function loadData() {
        try {
            const res = await fetch(`assignedthesis.php?thesisID=${encodeURIComponent(thesisID)}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await res.json();
            if (!data.success) {
                alert(data.message || 'Failed to load data');
                return;
            }

            // Committee members display
            if (data.committee && Object.keys(data.committee).length > 0) {
                const m1 = data.committee.member1Name || 'No first member in committee.';
                const m2 = data.committee.member2Name || 'No second member in committee.';
                committeeTableBody.innerHTML = `<tr><td>${escapeHtml(m1)}</td><td>${escapeHtml(m2)}</td></tr>`;
            } else {
                committeeTableBody.innerHTML = `<tr><td colspan="2">No committee members assigned</td></tr>`;
            }

            // Invitations display
            if (data.invitations && data.invitations.length > 0) {
                invitationList.innerHTML = '';
                data.invitations.forEach(inv => {
                    let responseHtml = inv.response ? 
                        `${escapeHtml(inv.response)} ${escapeHtml(inv.responseDate)}` : 
                        '<p>Pending response</p>';
                    const item = document.createElement('li');
                    item.innerHTML = `${escapeHtml(inv.receiverName)} - ${responseHtml}`;
                    invitationList.appendChild(item);
                });
            } else {
                invitationList.innerHTML = '<p>No invitations sent.</p>';
            }

            // Show unassign form only if supervisor is current teacher
            if (data.teacherId && data.committee && data.committee.supervisor === data.teacherId) {
                unassignForm.style.display = 'block';
            } else {
                unassignForm.style.display = 'none';
            }
        } catch (err) {
            alert('Error loading data');
            console.error(err);
        }
    }

    unassignForm?.addEventListener('submit', async e => {
        e.preventDefault();
        if (!confirm('Are you sure you want to remove this assignment?')) return;
        const formData = new FormData(unassignForm);
        formData.append('remove', '1');

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
            alert('Unable to remove assignment');
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
