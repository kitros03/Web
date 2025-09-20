document.addEventListener('DOMContentLoaded', () => {
    const assignmentForm = document.getElementById('assignmentForm');
    const resultDiv = document.getElementById('result');
    const thesisSelect = assignmentForm.querySelector('select[name="thesis"]');
    const studentSelect = assignmentForm.querySelector('select[name="student"]');
    const assignedContainer = document.getElementById('assignedTheses');

    async function loadData() {
        try {
            const response = await fetch('studentassignment.php', {headers: {'X-Requested-With': 'XMLHttpRequest'}});
            const data = await response.json();
            if (!data.success) {
                resultDiv.textContent = data.message || 'Failed to load data.';
                return;
            }

            // Populate theses
            thesisSelect.innerHTML = '<option value="">-- Select Thesis --</option>';
            data.theses.forEach(th => {
                const opt = document.createElement('option');
                opt.value = th.thesisID;
                opt.textContent = th.title;
                thesisSelect.appendChild(opt);
            });

            // Populate students
            studentSelect.innerHTML = '<option value="">-- Select Student --</option>';
            data.students.forEach(st => {
                const opt = document.createElement('option');
                opt.value = st.username;
                opt.textContent = st.username;
                studentSelect.appendChild(opt);
            });

            // Populate assigned theses
            if (data.assignedTheses.length === 0) {
                assignedContainer.innerHTML = '<p>No assigned theses yet.</p>';
            } else {
                let html = '<table class="table"><thead><tr><th>ID</th><th>Title</th><th>Description</th><th>Student</th><th>Action</th></tr></thead><tbody>';
                data.assignedTheses.forEach(t => {
                    html += `<tr>
                        <td>${t.thesisID}</td>
                        <td>${escapeHtml(t.title)}</td>
                        <td>${escapeHtml(t.thesis_description || t.description || '')}</td>
                        <td>${escapeHtml(t.studentUsername)}</td>`;
                    if (!t.finalized) {
                        html += `<td><button class="remove-btn" data-thesis-id="${t.thesisID}">Remove</button></td>`;
                    } else {
                        html += '<td>Finalized</td>';
                    }
                    html += '</tr>';
                });
                html += '</tbody></table>';
                assignedContainer.innerHTML = html;

                assignedContainer.querySelectorAll('.remove-btn').forEach(btn => {
                    btn.addEventListener('click', async () => {
                        if (!confirm('Are you sure to remove this assignment?')) return;
                        const thesisId = btn.getAttribute('data-thesis-id');
                        try {
                            const formData = new FormData();
                            formData.append('remove', '1');
                            formData.append('thesisID', thesisId);
                            const res = await fetch('studentassignment.php', {
                                method: 'POST',
                                body: formData,
                                headers: {'X-Requested-With': 'XMLHttpRequest'}
                            });
                            const json = await res.json();
                            alert(json.message);
                            if (json.success) {
                                loadData();
                            }
                        } catch (e) {
                            alert('Failed to remove assignment.');
                            console.error(e);
                        }
                    });
                });
            }
        } catch (e) {
            resultDiv.textContent = 'Failed to load data.';
            console.error(e);
        }
    }

    assignmentForm.addEventListener('submit', async e => {
        e.preventDefault();
        resultDiv.textContent = '';
        const thesisId = thesisSelect.value.trim();
        const studentName = studentSelect.value.trim();
        if (!thesisId || !studentName) {
            resultDiv.textContent = 'Please select both thesis and student.';
            return;
        }
        try {
            const formData = new FormData(assignmentForm);
            const res = await fetch('studentassignment.php', {
                method: 'POST',
                body: formData,
                headers: {'X-Requested-With': 'XMLHttpRequest'}
            });
            const json = await res.json();
            resultDiv.textContent = json.message;
            if (json.success) {
                assignmentForm.reset();
                loadData();
            }
        } catch (e) {
            resultDiv.textContent = 'Failed to submit assignment.';
            console.error(e);
        }
    });

    function escapeHtml(text) {
        if (!text) return '';
        return text.replace(/&/g, '&amp;')
                   .replace(/</g, '&lt;')
                   .replace(/>/g, '&gt;')
                   .replace(/"/g, '&quot;')
                   .replace(/'/g, '&#039;');
    }

    loadData();
});
