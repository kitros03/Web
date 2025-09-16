document.addEventListener('DOMContentLoaded', function () {

  // BACK BUTTON
  const backBtn = document.getElementById('backBtn');
  if (backBtn) {
    backBtn.addEventListener('click', () => {
      window.location.href = 'teacherdashboard.php';
    });
  }

  // POPUP WINDOWS (Add Notes)
  const notesBtn = document.getElementById('addNotesBtn');
  const popupWindow1 = document.getElementById('popupWindow1');
  const closePopupBtn1 = document.getElementById('closePopupBtn1');

  if (notesBtn && popupWindow1 && closePopupBtn1) {
    notesBtn.addEventListener('click', () => {
      popupWindow1.style.display = 'flex';
    });

    closePopupBtn1.addEventListener('click', () => {
      popupWindow1.style.display = 'none';
    });

    popupWindow1.addEventListener('click', (e) => {
      if (e.target === popupWindow1) {
        popupWindow1.style.display = 'none';
      }
    });
  }

  // POPUP WINDOWS (View Notes)
  const viewBtn = document.getElementById('viewNotesBtn');
  const popupWindow2 = document.getElementById('popupWindow2');
  const closePopupBtn2 = document.getElementById('closePopupBtn2');

  if (viewBtn && popupWindow2 && closePopupBtn2) {
    viewBtn.addEventListener('click', () => {
      popupWindow2.style.display = 'flex';
    });

    closePopupBtn2.addEventListener('click', () => {
      popupWindow2.style.display = 'none';
    });

    popupWindow2.addEventListener('click', (e) => {
      if (e.target === popupWindow2) {
        popupWindow2.style.display = 'none';
      }
    });
  }

  // ADD NOTE FORM SUBMISSION
  const addNoteForm = document.getElementById('addNoteForm');
  if(addNoteForm){
    addNoteForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const description = document.getElementById('description').value.trim();
      if(!description){
        alert('Description is required.');
        return;
      }

      const thesisID = addNoteForm.querySelector('input[name="thesisID"]')?.value || addNoteForm.getAttribute('data-thesis-id');
      if(!thesisID){
        alert('Thesis ID is required before adding a note.');
        return;
      }

      try{
        const formData = new FormData();
        formData.append('description', description);
        formData.append('thesisID', thesisID);

        const response = await fetch('activethesis.php',{
          method: 'POST',
          credentials: 'same-origin',
          body: formData
        });
        const result = await response.json();

        alert(result.message);

        if(result.success){
          // Close popup and reload notes or reload page as you like
          window.location.reload();
        }
      } catch(error){
        console.error('Add note error:', error);
        alert('Error adding note.');
      }
    });
  }

  // UNASSIGN FORM
  const form = document.getElementById('unassignForm');
  if (form) {
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const thesisID = form.querySelector('[name="thesisID"]')?.value || form.getAttribute('data-thesis-id');
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
          body: formData,
        });

        const result = await response.text();
        alert(result);

        if (result.includes('success')) {
          window.location.href = `viewtheses.php`;
        }
      } catch (error) {
        alert('Error: Could not remove assignment.');
        console.error('Removal error:', error);
      }
    });
  }

  // START EXAM FORM
  const startExamForm = document.getElementById('startExamForm');
  if (startExamForm) {
    startExamForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const thesisID = startExamForm.querySelector('[name="thesisID"]')?.value || startExamForm.getAttribute('data-thesis-id');
      if (!thesisID) {
        alert('Missing thesisID for this form.');
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

        const result = await response.text();
        alert(result);

        if (result.includes('success')) {
          alert('Exam started successfully. Redirecting to exam page.');
          window.location.href = `examthesis.php?thesisID=${encodeURIComponent(thesisID)}`;
        }
      } catch (error) {
        alert('Error: Could not start exam.');
        console.error('Start exam error:', error);
      }
    });
  }

});
