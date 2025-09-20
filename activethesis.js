document.addEventListener('DOMContentLoaded', function () {
    const backBtn = document.getElementById('backBtn');
    backBtn?.addEventListener('click', () => {
        window.location.href = 'teacherdashboard.php';
    });

    const notesBtn = document.getElementById('addNotesBtn');
    const popupWindow1 = document.getElementById('popupWindow1');
    const closePopupBtn1 = document.getElementById('closePopupBtn1');

    notesBtn?.addEventListener('click', () => {
        popupWindow1.style.display = 'flex';
    });
    closePopupBtn1?.addEventListener('click', () => {
        popupWindow1.style.display = 'none';
    });
    popupWindow1?.addEventListener('click', (e) => {
        if (e.target === popupWindow1) popupWindow1.style.display = 'none';
    });

    const viewBtn = document.getElementById('viewNotesBtn');
    const popupWindow2 = document.getElementById('popupWindow2');
    const closePopupBtn2 = document.getElementById('closePopupBtn2');

    viewBtn?.addEventListener('click', () => {
        popupWindow2.style.display = 'flex';
    });
    closePopupBtn2?.addEventListener('click', () => {
        popupWindow2.style.display = 'none';
    });
    popupWindow2?.addEventListener('click', (e) => {
        if (e.target === popupWindow2) popupWindow2.style.display = 'none';
    });

    const addNoteForm = document.getElementById('addNoteForm');
    addNoteForm?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const description = document.getElementById('description').value.trim();
        if (!description) {
            alert('Description is required.');
            return;
        }
        const thesisID = addNoteForm.querySelector('input[name="thesisID"]')?.value || addNoteForm.getAttribute('data-thesis-id');
        if (!thesisID) {
            alert('Thesis ID is required before adding a note.');
            return;
        }
        try {
            const formData = new FormData();
            formData.append('description', description);
            formData.append('thesisID', thesisID);
            const response = await fetch('activethesis.php', {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            });
            const result = await response.json();
            alert(result.message);
            if (result.success) {
                popupWindow1.style.display = 'none';
                window.location.reload();
            }
        } catch (error) {
            alert('Error adding note.');
            console.error(error);
        }
    });

    const unassignForm = document.getElementById('unassignForm');
    unassignForm?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const thesisID = unassignForm.querySelector('[name="thesisID"]')?.value || unassignForm.getAttribute('data-thesis-id');
        if (!thesisID) {
            alert('Missing thesis ID for unassign.');
            return;
        }
        if (!confirm('Are you sure you want to remove this assignment?')) return;
        const formData = new FormData(unassignForm);
        formData.set('remove', '1');
        formData.set('thesisID', thesisID);
        try {
            const response = await fetch('activethesis.php', {
                method: 'POST',
                credentials: 'same-origin',
                body: formData,
            });
            const result = await response.json();
            alert(result.message);
            if (result.success) {
                window.location.href = 'viewtheses.php';
            }
        } catch (error) {
            alert('Error removing assignment.');
            console.error(error);
        }
    });

    const startExamForm = document.getElementById('startExamForm');
    startExamForm?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const thesisID = startExamForm.querySelector('[name="thesisID"]')?.value || startExamForm.getAttribute('data-thesis-id');
        if (!thesisID) {
            alert('Missing thesis ID for starting exam.');
            return;
        }
        if (!confirm('Are you sure you want to start the exam?')) return;
        const formData = new FormData(startExamForm);
        formData.set('start_examination', '1');
        formData.set('thesisID', thesisID);
        try {
            const response = await fetch('activethesis.php', {
                method: 'POST',
                credentials: 'same-origin',
                body: formData,
            });
            const result = await response.json();
            alert(result.message);
            if (result.success) {
                alert('Exam started successfully. Redirecting to exam page.');
                window.location.href = `examthesis.php?thesisID=${encodeURIComponent(thesisID)}`;
            }
        } catch (error) {
            alert('Error starting exam.');
            console.error(error);
        }
    });

});
