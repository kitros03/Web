document.addEventListener("DOMContentLoaded", () => {
    const draftLink = document.getElementById("draftLink");
    const btnPresentation = document.getElementById("btnPresentation");
    const popupPresentation = document.getElementById("popupPresentation");
    const closePresentation = document.getElementById("closePresentation");
    const btnGrading = document.getElementById("btnGrading");
    const popupGrading = document.getElementById("popupGrading");
    const closeGrading = document.getElementById("closeGrading");
    const presentationInfo = document.getElementById("presentationInfo");
    const gradingContent = document.getElementById("gradingContent");
    const backBtn = document.getElementById('backBtn');

    backBtn?.addEventListener('click', () => {
        window.location.href = 'teacherdashboard.php';
    });

    
    // Retrieve thesisID from URL or sessionStorage
    function getThesisId() {
        const params = new URLSearchParams(window.location.search);
        let id = params.get("thesisID");
        if (id) {
            sessionStorage.setItem("thesisID", id);
            return id;
        }
        return sessionStorage.getItem("thesisID");
    }

    const thesisId = getThesisId();

    if (!thesisId) {
        alert("Missing thesisID.");
        return;
    }

    function escapeHtml(text) {
        if (text === null || text === undefined) return "";
        return String(text)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    //load data
    async function fetchData() {
        try {
            const res = await fetch(`examthesis.php?thesisID=${encodeURIComponent(thesisId)}`, {
                headers: { "X-Requested-With": "XMLHttpRequest" },
            });
            if (!res.ok) throw new Error("Network error");
            const data = await res.json();
            console.log("Fetched data:", data);
            return data;
        } catch (e) {
            alert("Failed to load data");
            console.error(e);
            return null;
        }
    }

    //update draft link
    async function updateDraftLink() {
        const data = await fetchData();
        draftLink.style.display = "inline-block";
        if (data?.thesis?.pdf_description) {
            draftLink.href = data.thesis.pdf_description;
            draftLink.style.pointerEvents = "auto";
            draftLink.style.color = "";
            draftLink.textContent = "View Draft";
        } else {
            draftLink.removeAttribute("href");
            draftLink.style.pointerEvents = "none";
            draftLink.style.color = "gray";
            draftLink.textContent = "Draft file not available";
        }
    }

    //presentation popup
    btnPresentation.addEventListener("click", async () => {
        popupPresentation.style.display = "flex";
        const data = await fetchData();
        if (!data) return;
        const meta = data.meta || {};
        const student = data.student || {};
        const thesis = data.thesis || {};
        let html = "";
        if (meta.announce) {
            html += `<p>Φοιτητής: ${escapeHtml(student.s_fname)} ${escapeHtml(student.s_lname)}</p>`;
            html += `<p>Θέμα: ${escapeHtml(thesis.title)}</p>`;
            html += `<p>Ημ/νία και Ώρα: ${escapeHtml(meta.exam_datetime)}</p>`;
            if (meta.exam_meeting_url) {
                html += `<p>Link: <a href="${escapeHtml(meta.exam_meeting_url)}" target="_blank">${escapeHtml(meta.exam_meeting_url)}</a></p>`;
            } else if (meta.exam_room) {
                html += `<p>Αίθουσα: ${escapeHtml(meta.exam_room)}</p>`;
            }
        } else {
            html = `<p>Δεν υπάρχουν πληροφορίες</p>`;
        }
        presentationInfo.innerHTML = html;
    });

    closePresentation.addEventListener("click", () => {
        popupPresentation.style.display = "none";
    });

    popupPresentation.addEventListener("click", (e) => {
        if (e.target === popupPresentation) {
            popupPresentation.style.display = "none";
        }
    });

    //grading popup
    btnGrading.addEventListener("click", async () => {
        popupGrading.style.display = "flex";
        const data = await fetchData();
        if (!data) {
            gradingContent.textContent = "Αποτυχία φόρτωσης δεδομένων.";
            return;
        }
        const grades = data.grades || [];
        const thesis = data.thesis || {};
        const teacher = data.teacher || {};

        let html = "";

        const gradingActive = thesis.grading ? true : false;
        const currentTeacherId = teacher.id ? Number(teacher.id) : 0;
        const supervisorId = thesis.supervisor ? Number(thesis.supervisor) : 0;

        if (!gradingActive) {
            if (currentTeacherId !== 0 && currentTeacherId === supervisorId) {
                html = `<form id="activateGrading" style="margin-top:1em;">
                    <input type="hidden" name="thesisID" value="${escapeHtml(thesis.thesisID || thesis.id || '')}" />
                    <button type="submit">Activate Grading</button>
                </form>`;
            } else {
                html = "<p>Supervisor has not activated grading.</p>";
            }
        } else {
            const hasSubmitted = grades.some(g => Number(g.teacherID) === currentTeacherId);
            if (!hasSubmitted) {
                html = `<form id="gradeForm" style="margin-top:1em;">
                    <label>Ποιότητα Δ.Ε.</label>
                    <input name="quality_grade" type="number" min="0" max="10" step="0.1" required />
                    <label>Χρόνος εκπόνησης</label>
                    <input name="time" type="number" min="0" max="10" step="0.1" required />
                    <label>Πληρότητα Κειμένου</label>
                    <input name="rest" type="number" min="0" max="10" step="0.1" required />
                    <label>Συνολική εικόνα</label>
                    <input name="presentation" type="number" min="0" max="10" step="0.1" required />
                    <button type="submit">Submit</button>
                </form>`;
            } else {
                html = "<p>Έχετε ήδη βαθμολογήσει.</p>";
            }

            if (grades.length) {
                html += "<table border='1' style='border-collapse: collapse; margin-top: 1em; width: 100%;'><thead><tr><th>Καθηγητής</th><th>Ποιότητα Δ.Ε.</th><th>Χρόνος εκπόνησης</th><th>Πληρότητα Κειμένου</th><th>Συνολική εικόνα</th><th>Βαθμός</th></tr></thead><tbody>";
                for (const grade of grades) {
                    html += `<tr>
                        <td>${escapeHtml(grade.t_fname)} ${escapeHtml(grade.t_lname)}</td>
                        <td>${escapeHtml(grade.quality_grade)}</td>
                        <td>${escapeHtml(grade.time_grade)}</td>
                        <td>${escapeHtml(grade.rest_quality_grade)}</td>
                        <td>${escapeHtml(grade.presentation_grade)}</td>
                        <td>${escapeHtml(Number(grade.calc_grade).toFixed(2))}</td>
                    </tr>`;
                }
                html += "</tbody></table>";
            }
        }

        gradingContent.innerHTML = html;

        // form handlers
        const activateForm = document.getElementById("activateGrading");
        if (activateForm) {
            activateForm.addEventListener("submit", async (e) => {
                e.preventDefault();
                const formData = new FormData(activateForm);
                formData.append("action", "activateGrading");
                try {
                    const res = await fetch("examthesis.php", {
                        method: "POST",
                        headers: { "X-Requested-With": "XMLHttpRequest" },
                        body: formData
                    });
                    const json = await res.json();
                    alert(json.message);
                    if (json.success) location.reload();
                } catch {
                    alert("Failed to activate grading");
                }
            });
        }

        // submit grades form
        const gradeForm = document.getElementById("gradeForm");
        if (gradeForm) {
            gradeForm.addEventListener("submit", async (e) => {
                e.preventDefault();
                const formData = new FormData(gradeForm);
                formData.append("action", "submitGrades");
                formData.append("thesisID", thesis.thesisID || thesis.id);
                try {
                    const res = await fetch("examthesis.php", {
                        method: "POST",
                        headers: { "X-Requested-With": "XMLHttpRequest" },
                        body: formData
                    });
                    if (!res.ok) throw new Error(`Network response was not ok (${res.status})`);
                    const json = await res.json();
                    console.log("Submit grades response:", json);
                    alert(json.message);
                    if (json.success) location.reload();
                } catch (error) {
                    alert("Failed to submit grades");
                    console.error("Submit grades fetch error:", error);
                }
            });
        }
    });

    closeGrading.addEventListener("click", () => {
        popupGrading.style.display = "none";
    });

    popupGrading.addEventListener("click", (e) => {
        if (e.target === popupGrading) {
            popupGrading.style.display = "none";
        }
    });

    window.onload = updateDraftLink;
});
