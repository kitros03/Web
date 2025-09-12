document.addEventListener('DOMContentLoaded', function () {

  // BACK BUTTON
  const backBtn = document.getElementById('backBtn');
  if (backBtn) {
    backBtn.addEventListener('click', () => {
      window.location.href = 'teacherdashboard.php';
    });
  }

  // POPUP WINDOWS 
  const textbtn = document.getElementById('textbtn');
  const popup1 = document.getElementById('popup1');
  const closePopupBtn1 = document.getElementById('closePopupBtn1');

  if (textbtn && popup1 && closePopupBtn1) {
    textbtn.addEventListener('click', () => {
      popup1.style.display = 'flex';
    });

    closePopupBtn1.addEventListener('click', () => {
      popup1.style.display = 'none';
    });

    popup1.addEventListener('click', (e) => {
      if (e.target === popup1) {
        popup1.style.display = 'none';
      }
    });
  }

  const presentationbtn = document.getElementById('presentationbtn');
  const popup2 = document.getElementById('popup2');
  const closePopupBtn2 = document.getElementById('closePopupBtn2');

  if (presentationbtn && popup2 && closePopupBtn2) {
    presentationbtn.addEventListener('click', () => {
      popup2.style.display = 'flex';
    });

    closePopupBtn2.addEventListener('click', () => {
      popup2.style.display = 'none';
    });

    popup2.addEventListener('click', (e) => {
      if (e.target === popup2) {
        popup2.style.display = 'none';
      }
    });
  }

  const gradebtn = document.getElementById('gradebtn');
  const popup3 = document.getElementById('popup3');
  const closePopupBtn3 = document.getElementById('closePopupBtn2');

  if (gradebtn && popup3 && closePopupBtn3) {
    gradebtn.addEventListener('click', () => {
      popup3.style.display = 'flex';
    });

    closePopupBtn2.addEventListener('click', () => {
      popup3.style.display = 'none';
    });

    popup3.addEventListener('click', (e) => {
      if (e.target === popup3) {
        popup3.style.display = 'none';
      }
    });
  }

    // ACTIVATE GRADING FORM
   const activateGradingForm = document.getElementById('activateGradingForm');
  if (activateGradingForm) {
    activateGradingForm.addEventListener('submit', async function(e) {
      e.preventDefault();

      const formData = new FormData(activateGradingForm);
      formData.append('action', 'activateGrading');

      try {
        const response = await fetch('examthesis.php', {
          method: 'POST',
          body: formData,
        });

        const result = await response.json();

        if (result.success) {
          alert(result.message);
          window.location.reload();
        } else {
          alert('Αποτυχία ενεργοποίησης βαθμολόγησης.');
        }
      } catch (error) {
        alert('Σφάλμα κατά την ενεργοποίηση βαθμολόγησης.');
        console.error('Activate grading error:', error);
      }
    });
  }
});
