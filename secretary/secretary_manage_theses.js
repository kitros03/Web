// secretary_manage_theses.js — GS / Ακύρωση / Done (με υποστήριξη ready_finalize)
(function () {
  'use strict';

  // Προτιμάμε το endpoint της διαχείρισης (έχει repo/grade/ready_finalize). Fallback στην απλή λίστα.
  var LIST_ENDPOINTS = [
    'secretary_manage_theses_list.php',
    'secretary_theses_list.php'
  ];

  var ACTIONS_ENDPOINT   = 'secretary_manage_thesis_actions.php';
  var MARK_DONE_ENDPOINT = 'secretary_mark_done.php';

  function $(id){ return document.getElementById(id); }

  // Άγκυρα κάτω από το searchBox για σωστό layout
  var searchBox = $('searchBox');
  var anchor = searchBox ? (searchBox.parentNode || searchBox) : document.body;

  var listDiv = $('thesesList');
  if (!listDiv) {
    listDiv = document.createElement('div');
    listDiv.id = 'thesesList';
    anchor.parentNode.insertBefore(listDiv, anchor.nextSibling);
  }

  var cachedList = [];

  function statusGR(s){
    var m={'NOT_ASSIGNED':'Μη Ανατεθειμένη','ASSIGNED':'Ανατεθειμένη','ACTIVE':'Ενεργή','EXAM':'Υπό Εξέταση','DONE':'Περατωμένη','CANCELLED':'Ακυρωμένη'};
    return m[s] || s || '';
  }
  function daysLabel(n){
    if(n==null) return '—';
    var d=Number(n); if(isNaN(d)) return '—';
    if(d===0) return '0 ημέρες'; if(d===1) return '1 ημέρα';
    return d+' ημέρες';
  }

  function canShowDone(it){
    if (!it || it.th_status === 'DONE' || it.th_status === 'CANCELLED') return false;
    // Επιτρέπουμε Done κυρίως για EXAM (και προαιρετικά ACTIVE)
    if (['EXAM','ACTIVE'].indexOf(it.th_status) === -1) return false;

    // 1) προτιμάμε explicit flag από API
    if (typeof it.ready_finalize !== 'undefined') {
      return !!it.ready_finalize;
    }
    // 2) διαφορετικά, ελέγχουμε repo + grade
    var hasRepo  = !!it.repository_url;
    var hasGrade = (it.final_grade != null) && !isNaN(Number(it.final_grade));
    return hasRepo && hasGrade;
  }

  function renderList(items){
    if (!items || !items.length){ listDiv.innerHTML='<p>Δεν βρέθηκαν διπλωματικές.</p>'; return; }

    var rows = [];
    rows.push('<table class="table"><thead><tr>');
    rows.push('<th>Κωδ.</th><th>Τίτλος</th><th>Κατάσταση</th><th>Επιβλέπων</th><th>Φοιτητής</th><th>Από ανάθεση</th><th>Ενέργειες</th>');
    rows.push('</tr></thead><tbody>');

    items.forEach(function(it){
      rows.push('<tr>');
      rows.push('<td>'+ (it.thesisID!=null?it.thesisID:'') +'</td>');
      rows.push('<td>'+ (it.title||'') +'</td>');
      rows.push('<td>'+ statusGR(it.th_status) +'</td>');
      rows.push('<td>'+ (it.supervisor_name||'—') +'</td>');
      rows.push('<td>'+ (it.student_name||'—') +'</td>');
      rows.push('<td>'+ daysLabel(it.days_since_assignment) +'</td>');

      var actions = [];
      if (it.th_status === 'ACTIVE') {
        actions.push('<button class="sidebarButton btn-start-exam" data-id="'+it.thesisID+'">Καταχώρηση GS</button>');
        actions.push('<button class="sidebarButton btn-cancel-thesis" data-id="'+it.thesisID+'">Ακύρωση</button>');
      }
      if (canShowDone(it)) {
        actions.push('<button class="sidebarButton btn-finalize" data-id="'+it.thesisID+'">Οριστική Περάτωση</button>');
      }
      rows.push('<td>' + (actions.length ? actions.join(' ') : '—') + '</td>');
      rows.push('</tr>');
    });

    rows.push('</tbody></table>');
    listDiv.innerHTML = rows.join('');
  }

  function fetchJSON(url, options){
    options=options||{}; if(!options.credentials) options.credentials='same-origin';
    return fetch(url, options).then(function(res){
      return res.text().then(function(txt){
        var data;
        try { data = JSON.parse(txt); }
        catch(_){ var e=new Error('Non-JSON ('+res.status+') από '+url+': '+String(txt).slice(0,200)); e._nonJson=true; throw e; }
        if (!res.ok){
          var msg=(data && (data.message||data.error)) || (res.status+' '+(res.statusText||'')); 
          throw new Error(msg);
        }
        return data;
      });
    });
  }

  function postJSON(url, payload){
    return fetchJSON(url, {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      credentials:'same-origin',
      body:JSON.stringify(payload||{})
    });
  }

  function tryLoad(i){
    if (i >= LIST_ENDPOINTS.length){
      listDiv.innerHTML = '<p class="error">Δεν βρέθηκε endpoint λίστας.</p>';
      return;
    }
    var url = LIST_ENDPOINTS[i];
    listDiv.innerHTML = '<p>Φόρτωση…</p>';
    fetchJSON(url)
      .then(function(data){
        cachedList = Array.isArray(data)?data:[];
        renderList(cachedList);
      })
      .catch(function(err){
        if (err._nonJson || /404/.test(err.message)) { tryLoad(i+1); }
        else { listDiv.innerHTML = '<p class="error">'+err.message+'</p>'; }
      });
  }
  function loadList(){ tryLoad(0); }

  // Actions: GS / Ακύρωση / Done
  document.addEventListener('click', function(e){
    var t=e.target||e.srcElement;

    if (t && t.classList && t.classList.contains('btn-start-exam')){
      var id1 = Number(t.getAttribute('data-id'));
      var gs = window.prompt('Δώσε GS Number (ακέραιος):','');
      if (gs===null) return;
      gs=String(gs).trim();
      if (!/^\d+$/.test(gs)){ alert('Το GS πρέπει να είναι ακέραιος αριθμός.'); return; }
      var old = t.textContent; t.disabled=true; t.textContent='Καταχώρηση…';
      postJSON(ACTIONS_ENDPOINT,{action:'startExam',thesisID:id1,gs_numb:gs})
        .then(function(out){ if(!out||!out.success) throw new Error((out&&out.message)||'Αποτυχία'); alert(out.message||'Έγινε.'); })
        .catch(function(err){ alert('Σφάλμα: '+err.message); })
        .then(function(){ t.disabled=false; t.textContent=old; });
      return;
    }

    if (t && t.classList && t.classList.contains('btn-cancel-thesis')){
      var id2 = Number(t.getAttribute('data-id'));
      var gaNumber=window.prompt('GA Number:','')||'';
      var gaDate  =window.prompt('GA Date (YYYY-MM-DD):','')||'';
      var reason  =window.prompt('Αιτιολογία:','από Διδάσκοντα')||'από Διδάσκοντα';
      gaNumber=gaNumber.trim(); gaDate=gaDate.trim(); reason=(reason||'').trim()||'από Διδάσκοντα';
      if(!gaNumber||!gaDate){ alert('Συμπλήρωσε GA Number και GA Date.'); return; }
      if(!/^\d{4}-\d{2}-\d{2}$/.test(gaDate)){ alert('Μη έγκυρη ημερομηνία (YYYY-MM-DD).'); return; }
      if(!confirm('Σίγουρα ακύρωση;')) return;
      var old2=t.textContent; t.disabled=true; t.textContent='Ακύρωση…';
      postJSON(ACTIONS_ENDPOINT,{action:'cancelThesis',thesisID:id2,gaNumber:gaNumber,gaDate:gaDate,reason:reason})
        .then(function(out){ if(!out||!out.success) throw new Error((out&&out.message)||'Αποτυχία'); alert(out.message||'Η διπλωματική ακυρώθηκε.'); loadList(); })
        .catch(function(err){ alert('Σφάλμα: '+err.message); })
        .then(function(){ t.disabled=false; t.textContent=old2; });
      return;
    }

    if (t && t.classList && t.classList.contains('btn-finalize')){
      var id3 = Number(t.getAttribute('data-id'));
      if (!confirm('Οριστική περάτωση της ΔΕ #' + id3 + ';')) return;
      var old3 = t.textContent; t.disabled = true; t.textContent = 'Περάτωση…';
      fetchJSON(MARK_DONE_ENDPOINT, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ thesisID: id3 })
      })
      .then(function(out){
        if (!out || !out.success) throw new Error((out && out.message) || 'Αποτυχία');
        alert('Η διπλωματική χαρακτηρίστηκε ως Περατωμένη.');
        loadList();
      })
      .catch(function(err){
        alert('Σφάλμα: ' + err.message);
        t.disabled = false; t.textContent = old3;
      });
      return;
    }
  });

  if (searchBox){
    searchBox.addEventListener('input', function(){
      var q=(searchBox.value||'').toLowerCase().trim();
      var filtered=(cachedList||[]).filter(function(x){
        return (x.title||'').toLowerCase().indexOf(q)!==-1 ||
               (x.supervisor_name||'').toLowerCase().indexOf(q)!==-1 ||
               (x.student_name||'').toLowerCase().indexOf(q)!==-1 ||
               String(x.thesisID||'').toLowerCase().indexOf(q)!==-1;
      });
      renderList(filtered);
    });
  }

  loadList();
})();
