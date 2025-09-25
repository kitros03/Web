document.addEventListener('DOMContentLoaded', () => {
    const thesisID = new URLSearchParams(window.location.search).get('thesisID');
    if (!thesisID) {
        alert('Missing thesisID in URL!');
        return;
    }

    const backBtn = document.getElementById('backBtn');
    const addNotesBtn = document.getElementById('addNotesBtn');
    const viewNotesBtn = document.getElementById('viewNotesBtn');
    const popupWindow1 = document.getElementById('popupWindow1');
    const popupWindow2 = document.getElementById('popupWindow2');
    const closePopupBtn1 = document.getElementById('closePopupBtn1');
    const closePopupBtn2 = document.getElementById('closePopupBtn2');
    const notesListContent = document.getElementById('notesListContent');
    const addNoteForm = document.getElementById('addNoteForm');
    const unassignSection = document.getElementById('unassignSection');
    const unassignForm = document.getElementById('unassignForm');
    const unassignMessage = document.getElementById('unassignMessage');
    const startExamSection = document.getElementById('startExamSection');
    const startExamForm = document.getElementById('startExamForm');
    const startExamMessage = document.getElementById('startExamMessage');
    const unauthorizedMessage = document.getElementById('unauthorizedMessage');

    backBtn?.addEventListener('click', () => window.location.href = 'teacherdashboard.php');

    addNotesBtn?.addEventListener('click', () => popupWindow1.style.display = 'flex');
    closePopupBtn1?.addEventListener('click', () => popupWindow1.style.display = 'none');
    popupWindow1?.addEventListener('click', e => { if (e.target === popupWindow1) popupWindow1.style.display = 'none'; });

    viewNotesBtn?.addEventListener('click', () => popupWindow2.style.display = 'flex');
    closePopupBtn2?.addEventListener('click', () => popupWindow2.style.display = 'none');
    popupWindow2?.addEventListener('click', e => { if (e.target === popupWindow2) popupWindow2.style.display = 'none'; });


    //load data
    async function loadData() {
        try{
            const res = await fetch(`activethesis.php?thesisID=${encodeURIComponent(thesisID)}`, {
                headers: {'X-Requested-With':'XMLHttpRequest'}
            });
            const data = await res.json();

            if(!data.success){
                unauthorizedMessage.style.display = "block";
                return;
            } else {
                unauthorizedMessage.style.display = "none";
            }

            if(data.canUnassign){
                unassignMessage.textContent = 'The thesis has been active for more than 2 years, you can unassign it.';
                if(unassignForm) {
                    unassignForm.style.display = "block";
                    document.getElementById('unassignThesisID').value = thesisID;
                }
                unassignSection.style.display = "block";
            } else {
                unassignMessage.textContent = 'The thesis has NOT been active for 2 years';
                if(unassignForm) unassignForm.style.display = "none";
                unassignSection.style.display = "block";
            }

            if(data.hasGsNumb){
                if(startExamForm) {
                    startExamForm.style.display = "block";
                    document.getElementById('startExamThesisID').value = thesisID;
                }
                startExamSection.style.display = "block";
                startExamMessage.textContent = "";
            } else {
                if(startExamForm) startExamForm.style.display = "none";
                startExamSection.style.display = "block";
                startExamMessage.textContent = "You can only start the examination if GS number is assigned";
            }
            
            //show notes
            if(notesListContent){
                if(data.teachernotes.length > 0){
                    let html = "<h3>Notes</h3><ul class='notes-list'>";
                    for(let note of data.teachernotes){
                        html += `<li><strong>${escapeHtml(note.description)}</strong></li>`;
                    }
                    html += "</ul>";
                    notesListContent.innerHTML = html;
                } else {
                    notesListContent.innerHTML = "<p>No notes found</p>";
                }
            }

        } catch(e){
            unauthorizedMessage.style.display = "block";
        }
    }

    function escapeHtml(text){
        if(!text) return "";
        return text.replace(/&/g,"&amp;")
                   .replace(/</g,"&lt;")
                   .replace(/>/g,"&gt;")
                   .replace(/"/g,"&quot;")
                   .replace(/'/g,"&#039;");
    }

    //add note 
    addNoteForm?.addEventListener('submit', async e => {
        e.preventDefault();
        const desc = document.getElementById('description').value.trim();

        if(!desc){
            alert('Description required');
            return;
        }

        try{
            let formData = new FormData();
            formData.append('description', desc);
            formData.append('thesisID', thesisID);

            let res = await fetch('activethesis.php',{
                method: 'POST',
                credentials: 'same-origin',
                body: formData,
                headers: {'X-Requested-With':'XMLHttpRequest'}
            });

            let result = await res.json();
            alert(result.message);
            if(result.success){
                document.getElementById('description').value = "";
                popupWindow1.style.display = "none";
                await loadData();
            }
        }catch(e){
            alert("Error submitting note");
        }
    });

    //unassign 
    unassignForm?.addEventListener('submit', async e => {
        e.preventDefault();
        if(!confirm("Are you sure you want to unassign?")) return;
        try{
            let formData = new FormData();
            formData.append('remove', '1');
            formData.append('thesisID', thesisID);

            let res = await fetch('activethesis.php',{
                method:'POST',
                credentials: 'same-origin',
                body: formData,
                headers: {'X-Requested-With':'XMLHttpRequest'}
            });

            let result = await res.json();
            alert(result.message);
            if(result.success){
                window.location.href = 'viewtheses.php';
            }
        }catch(e){
            alert("Failed unassigning");
        }
    });

    //start exam
    startExamForm?.addEventListener('submit', async e => {
        e.preventDefault();
        if(!confirm("Start examination?")) return;
        try{
            let formData = new FormData();
            formData.append('start_examination','1');
            formData.append('thesisID', thesisID);

            let res = await fetch('activethesis.php',{
                method: 'POST',
                credentials: 'same-origin',
                body: formData,
                headers: {'X-Requested-With':'XMLHttpRequest'}
            });

            let result = await res.json();
            alert(result.message);
            if(result.success){
                window.location.href = `examthesis.php?thesisID=${encodeURIComponent(thesisID)}`;
            }
        }catch(e){
            alert("Failed starting examination");
        }
    });

    loadData();

});
