document.addEventListener('DOMContentLoaded', function () {
    // BACK BUTTON
    const backBtn = document.getElementById('backBtn');
    if (backBtn) {
        backBtn.addEventListener('click', () => {
            window.location.href = 'teacherdashboard.php';
        });
    }

    // POPUPS
    const togglePopup = (btnId, popupId, closeBtnId) => {
        const btn = document.getElementById(btnId);
        const popup = document.getElementById(popupId);
        const closeBtn = document.getElementById(closeBtnId);
        if (btn && popup && closeBtn) {
            btn.addEventListener('click', () => popup.style.display = 'flex');
            closeBtn.addEventListener('click', () => popup.style.display = 'none');
            popup.addEventListener('click', (e) => {
                if (e.target === popup) popup.style.display = 'none';
            });
        }
    };
    togglePopup('textbtn','popup1','closePopupBtn1');
    togglePopup('presentationbtn','popup2','closePopupBtn2');
    togglePopup('gradebtn','popup3','closePopupBtn3');

    // ACTIVATE GRADING
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

    // ΥΠΟΒΟΛΗ ΒΑΘΜΟΛΟΓΙΑΣ
    const gradeForm = document.getElementById('gradeForm');
    if (gradeForm) {
        gradeForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(gradeForm);
            formData.append('action', 'submitGrades');
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
                    alert('Σφάλμα υποβολής βαθμολογίας.');
                }
            } catch (error) {
                alert("Σφάλμα.");
                console.error(error);
            }
        });
    }
});
