document.addEventListener('DOMContentLoaded', () => {
    const assignmentForm = document.getElementById('assignmentForm');
    const resultDiv = document.getElementById('result');
    const thesisSelect = assignmentForm.querySelector('select[name="thesis"]');
    const studentSelect = assignmentForm.querySelector('select[name="student"]');
    const assignedContainer = document.getElementById('assignedTheses');
    const backBtn = document.getElementById('backBtn');

    backBtn?.addEventListener('click', () => {
        window.location.href = 'teacherdashboard.php';
    });

    function escapeHtml(text) {
        return text ? text.replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;").replace(/'/g,"&#039;") : "";
    }

    //load data
    async function loadData() {
        try {
            let response = await fetch('studentassignment.php', {
                headers: {"X-Requested-With": "XMLHttpRequest"}
            });
            let data = await response.json();
            if (!data.success) {
                resultDiv.textContent = data.message || "Αποτυχία φόρτωσης δεδομένων.";
                return;
            }

            //selects
            thesisSelect.innerHTML = '<option value="">-- Επιλέξτε --</option>';
            for(const thesis of data.theses) {
                let option = document.createElement('option');
                option.value = thesis.thesisID;
                option.textContent = thesis.title;
                thesisSelect.appendChild(option);
            }

            studentSelect.innerHTML = '<option value="">-- Επιλέξτε --</option>';
            for(const student of data.students) {
                let option = document.createElement('option');
                option.value = student.username;
                option.textContent = student.username;
                studentSelect.appendChild(option);
            }

            //theses table
            if (data.assignedTheses.length === 0) {
                assignedContainer.innerHTML = "<p>Δεν υπάρχουν ανατεθειμένα θέματα.</p>";
            } else {
                let html = `<table class="table"><thead><tr><th>ID</th><th>Τίτλος</th><th>Περιγραφή</th><th>Φοιτητής</th><th>Ενέργεια</th></tr></thead><tbody>`;
                for (const thesis of data.assignedTheses) {
                    html += `<tr><td>${thesis.thesisID}</td><td>${escapeHtml(thesis.title)}</td><td>${escapeHtml(thesis.thesis_description)}</td><td>${escapeHtml(thesis.studentUsername)}</td><td>`;
                    if (thesis.th_status==='ASSIGNED') {
                        html += `<button data-id="${thesis.thesisID}" class="submit-btn">Αναίρεση</button>`;
                    } else {
                        html += "N/A";
                    }
                    html += "</td></tr>";
                }
                html += "</tbody></table>";
                assignedContainer.innerHTML = html;

                //handle unassign
                assignedContainer.querySelectorAll(".submit-btn").forEach(button => {
                    button.addEventListener("click", async () => {
                        if (!confirm("Σίγουρα θέλετε να αναιρέσετε την ανάθεση θέματος;")) return;
                        const id = button.getAttribute("data-id");
                        let formData = new FormData();
                        formData.append("remove", "1");
                        formData.append("thesisID", id);

                        try {
                            let res = await fetch("studentassignment.php", {
                                method: "POST",
                                body: formData
                            });
                            let json = await res.json();
                            resultDiv.textContent = json.message;
                            if (json.success) loadData();
                        } catch (e) {
                            resultDiv.textContent = "Αποτυχία αναίρεσης ανάθεσης.";
                            console.error(e);
                        }
                    });
                });
            }
        } catch (e) {
            resultDiv.textContent = "Αποτυχία φόρτωσης δεδομένων.";
            console.error(e);
        }
    }

    //assign form submit
    assignmentForm.addEventListener("submit", async (e) => {
        e.preventDefault();
        resultDiv.textContent = "";
        if (!thesisSelect.value || !studentSelect.value) {
            resultDiv.textContent = "παρακαλώ επιλέξτε θέμα και φοιτητή.";
            return;
        }
        try {
            let formData = new FormData(assignmentForm);
            let res = await fetch("studentassignment.php", {
                method: "POST",
                body: formData
            });
            let json = await res.json();
            resultDiv.textContent = json.message;
            if (json.success) {
                assignmentForm.reset();
                loadData();
            }
        } catch (e) {
            resultDiv.textContent = "Αποτυχία ανάθεσης θέματος.";
            console.error(e);
        }
    });

    loadData();
});
