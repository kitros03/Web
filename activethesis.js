document.addEventListener('DOMContentLoaded', function () {
    const backBtn = document.getElementById('backBtn');
    if (backBtn) {
        backBtn.addEventListener('click', () => {
        window.location.href = 'teacherdashboard.php';
        });
    }

    const notesBtn = document.getElementById('addNotesBtn');
    const popupWindow1 = document.getElementById('popupWindow1');
    const closePopupBtn1 = document.getElementById('closePopupBtn1');

    const viewBtn = document.getElementById('viewNotesBtn');
    const popupWindow2 = document.getElementById('popupWindow2');
    const closePopupBtn2 = document.getElementById('closePopupBtn2');


    if (notesBtn && popupWindow1 && closePopupBtn1) {
        popupBtn.addEventListener('click', () => {
        popupWindow.style.display = 'flex';
        });
        closePopupBtn.addEventListener('click', () => {
        popupWindow.style.display = 'none';
        });
        popupWindow.addEventListener('click', (e) => {
        if (e.target === popupWindow) {
            popupWindow.style.display = 'none';
        }
        });
    }

    if (viewBtn && popupWindow2 && closePopupBtn2) {
        popupBtn.addEventListener('click', () => {
        popupWindow.style.display = 'flex';
        });
        closePopupBtn.addEventListener('click', () => {
        popupWindow.style.display = 'none';
        });
        popupWindow.addEventListener('click', (e) => {
        if (e.target === popupWindow) {
            popupWindow.style.display = 'none';
        }
        });
    }

  //need to handle the form submission for unassigning thesis
    const form = document.getElementById('unassignForm');
    form.addEventListener('submit', async (e) => {
      e.preventDefault();

      const thesisID = form.getAttribute('data-thesis-id') || form.querySelector('[name="thesisID"]')?.value;
      if (!thesisID) {
        alert('Missing thesisID for this form.');
        return;
      }

      if (!confirm('Are you sure you want to remove this assignment?')) return;

      const formData = new FormData(form);
      formData.set('remove', '1');
      formData.set('thesisID', thesisID);

      try {
        const response = await fetch('activethesis.php', {
          method: 'POST',
          credentials: 'same-origin',
          body: formData
        });
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        const result = await response.text();

        alert('Assignment removed successfully.');
        window.location.href = `viewtheses.php`;
      } catch (error) {
        alert('Error: Could not remove assignment.');
        console.error('Removal error:', error);
      }
    });

    // also need to handle the form for starting exam
    const startExamForm = document.getElementById('startExamForm');
    startExamForm.addEventListener('submit', async (e) => {
      e.preventDefault();

      const thesisID = startExamForm.getAttribute('data-thesis-id') || startExamForm.querySelector('[name="thesisID"]')?.value;
      if (!thesisID) {
        alert('Missing thesisID for this form.');
        return;
      }

      if (!confirm('Are you sure you want to start the exam?')) return;

      const formData = new FormData(startExamForm);
      formData.set('startExam', '1');
      formData.set('thesisID', thesisID);

      try {
        const response = await fetch('activethesis.php', {
          method: 'POST',
          credentials: 'same-origin',
          body: formData
        });
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        const result = await response.text();

        alert('Exam started successfully.');
        window.location.href = `examthesis.php?thesisID=${encodeURIComponent(thesisID)}`;
      } catch (error) {
        alert('Error: Could not start exam.');
        console.error('Start exam error:', error);
      }
    });
});
