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
    const params = new URLSearchParams(window.location.search);
    const thesisId = params.get("thesisID");

    function escapeHtml(text) {
        if (!text) return "";
        return text.replace(/&/g, "&amp;")
                   .replace(/</g, "&lt;")
                   .replace(/>/g, "&gt;")
                   .replace(/"/g, "&quot;")
                   .replace(/'/g, "&#039;");
    }

    async function fetchData() {
        try {
            const res = await fetch(`examthesis.php?thesisID=${encodeURIComponent(thesisId)}`, {
                headers: { "X-Requested-With": "XMLHttpRequest" },
            });
            if (!res.ok) throw new Error("Network error");
            return await res.json();
        } catch (e) {
            alert("Failed to load data");
            console.error(e);
            return null;
        }
    }

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

    btnPresentation.addEventListener("click", async () => {
        popupPresentation.style.display = "flex";
        const data = await fetchData();
        if (!data) return;
        const meta = data.meta || {};
        const student = data.student || {};
        const thesis = data.thesis || {};
        let html = "";
        if (meta.announce) {
            html += `<p>Student: ${escapeHtml(student.s_fname)} ${escapeHtml(student.s_lname)}</p>`;
            html += `<p>Thesis: ${escapeHtml(thesis.title)}</p>`;
            html += `<p>Date & Time: ${escapeHtml(meta.exam_datetime)}</p>`;
            if (meta.exam_meeting_url) {
                html += `<p>Link: <a href="${escapeHtml(meta.exam_meeting_url)}" target="_blank">${escapeHtml(meta.exam_meeting_url)}</a></p>`;
            } else if (meta.exam_room) {
                html += `<p>Room: ${escapeHtml(meta.exam_room)}</p>`;
            }
        } else {
            html = `<p>Presentation details not set</p>`;
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

    btnGrading.addEventListener("click", async () => {
        popupGrading.style.display = "flex";
        const data = await fetchData();
        console.log("Grading data:", data);
        if (!data) {
            gradingContent.textContent = "Failed to load grading data.";
            return;
        }
        const grades = data.grades || [];
        const meta = data.meta || {};
        const thesis = data.thesis || {};
        const teacher = data.teacher || {};
        console.log("Teacher ID:", teacher.id);
        console.log("Thesis supervisor:", thesis.supervisor);
        let html = "";

        if (!thesis.grading) {
            if (teacher.id != null && teacher.id === thesis.supervisor) {
                html = `<form id="activateGrading">
                    <input type="hidden" name="thesisID" value="${escapeHtml(thesis.thesisID || thesis.id || '')}" />
                    <button type="submit">Activate Grading</button>
                </form>`;
            } else {
                html = "<p>Supervisor has not activated grading.</p>";
            }
        } else {
            const hasSubmitted = grades.some(g => String(g.teacherID) === String(teacher.id));
            if (!hasSubmitted) {
                html = `<form id="gradeForm">
                    <label>Quality</label>
                    <input name="quality_grade" type="number" min="0" max="10" step="0.1" required />
                    <label>Time</label>
                    <input name="time" type="number" min="0" max="10" step="0.1" required />
                    <label>Completeness</label>
                    <input name="rest" type="number" min="0" max="10" step="0.1" required />
                    <label>Presentation</label>
                    <input name="presentation" type="number" min="0" max="10" step="0.1" required />
                    <button type="submit">Submit</button>
                </form>`;
            } else {
                html = "<p>You have already submitted grades.</p>";
            }

            if (grades.length) {
                html += "<table><thead><tr><th>Teacher</th><th>Quality</th><th>Time</th><th>Completeness</th><th>Presentation</th><th>Avg</th></tr></thead><tbody>";
                for (const grade of grades) {
                    html += `<tr>
                        <td>${escapeHtml(grade.t_fname)} ${escapeHtml(grade.t_lname)}</td>
                        <td>${escapeHtml(grade.quality_grade)}</td>
                        <td>${escapeHtml(grade.time)}</td>
                        <td>${escapeHtml(grade.rest)}</td>
                        <td>${escapeHtml(grade.presentation)}</td>
                        <td>${escapeHtml(grade.calc)}</td>
                    </tr>`;
                }
                html += "</tbody></table>";
            }
        }

        gradingContent.innerHTML = "";
        const container = document.createElement("div");
        container.innerHTML = html || "<p>No grading information available.</p>";
        gradingContent.appendChild(container);

        const activateForm = document.getElementById("activateGrading");
        if (activateForm) {
            activateForm.addEventListener("submit", async (e) => {
                e.preventDefault();
                const formData = new FormData(activateForm);
                formData.append("action", "activateGrading");
                try {
                    const res = await fetch("examthesis.php", { method: "POST", body: formData });
                    const json = await res.json();
                    alert(json.message);
                    if (json.success) location.reload();
                } catch {
                    alert("Failed to activate grading");
                }
            });
        }

        const gradeForm = document.getElementById("gradeForm");
        if (gradeForm) {
            gradeForm.addEventListener("submit", async (e) => {
                e.preventDefault();
                const formData = new FormData(gradeForm);
                formData.append("action", "submitGrades");
                formData.append("thesisID", thesis.thesisID || thesis.id);
                try {
                    const res = await fetch("examthesis.php", { method: "POST", body: formData });
                    const json = await res.json();
                    alert(json.message);
                    if (json.success) location.reload();
                } catch {
                    alert("Failed to submit grades");
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
