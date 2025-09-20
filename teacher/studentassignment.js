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

    async function loadData() {
        try {
            let response = await fetch('studentassignment.php', {
                headers: {"X-Requested-With": "XMLHttpRequest"}
            });
            let data = await response.json();
            if (!data.success) {
                resultDiv.textContent = data.message || "Failed to load data.";
                return;
            }

            thesisSelect.innerHTML = '<option value="">-- Select Thesis --</option>';
            for(const thesis of data.theses) {
                let option = document.createElement('option');
                option.value = thesis.thesisID;
                option.textContent = thesis.title;
                thesisSelect.appendChild(option);
            }

            studentSelect.innerHTML = '<option value="">-- Select Student --</option>';
            for(const student of data.students) {
                let option = document.createElement('option');
                option.value = student.username;
                option.textContent = student.username;
                studentSelect.appendChild(option);
            }

            if (data.assignedTheses.length === 0) {
                assignedContainer.innerHTML = "<p>No assigned theses yet.</p>";
            } else {
                let html = `<table class="table"><thead><tr><th>ID</th><th>Title</th><th>Description</th><th>Student</th><th>Action</th></tr></thead><tbody>`;
                for (const thesis of data.assignedTheses) {
                    html += `<tr><td>${thesis.thesisID}</td><td>${escapeHtml(thesis.title)}</td><td>${escapeHtml(thesis.thesis_description)}</td><td>${escapeHtml(thesis.studentUsername)}</td><td>`;
                    if (!thesis.finalized) {
                        html += `<button data-id="${thesis.thesisID}" class="submit-btn">Remove</button>`;
                    } else {
                        html += "Finalized";
                    }
                    html += "</td></tr>";
                }
                html += "</tbody></table>";
                assignedContainer.innerHTML = html;

                assignedContainer.querySelectorAll(".remove-btn").forEach(button => {
                    button.addEventListener("click", async () => {
                        if (!confirm("Are you sure to remove this assignment?")) return;
                        const id = button.getAttribute("data-id");
                        let formData = new FormData();
                        formData.append("remove", "1");
                        formData.append("thesisID", id);

                        try {
                            let res = await fetch("studentassignment.php", {
                                method: "POST",
                                body: formData
                            });
                            let text = await res.text();
                            alert(text);
                            loadData();
                        } catch (e) {
                            alert("Failed to remove assignment");
                            console.error(e);
                        }
                    });
                });
            }
        } catch (e) {
            resultDiv.textContent = "Failed to load initial data.";
            console.error(e);
        }
    }

    assignmentForm.addEventListener("submit", async (e) => {
        e.preventDefault();
        resultDiv.textContent = "";
        if (!thesisSelect.value || !studentSelect.value) {
            resultDiv.textContent = "Please select Thesis and Student.";
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
            resultDiv.textContent = "Failed to submit assignment.";
            console.error(e);
        }
    });

    loadData();
});
