<?php
require_once '../dbconnect.php';
session_start();
if (!isset($_SESSION['username']) || ($_SESSION['role'] ?? '') !== 'student') {
  header('Location: index.html'); exit;
}

// Φόρτωση στοιχείων φοιτητή (schema: street, street_number, city, postcode, email, cellphone, homephone)
$st = $pdo->prepare("
  SELECT studentID, username,
         CONCAT(COALESCE(street,''), ' ', COALESCE(street_number,''), ', ', COALESCE(city,''), ' ', COALESCE(postcode,'')) AS full_address,
         email,
         cellphone AS mobile,
         homephone AS phone,
         street, street_number, city, postcode
  FROM student
  WHERE username=? LIMIT 1
");
$st->execute([$_SESSION['username']]);
$me = $st->fetch(PDO::FETCH_ASSOC) ?: [];

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="el">
  <head>
    <meta charset="UTF-8">
    <title>Student Dashboard</title>
    <link rel="stylesheet" href="../style.css">
    <style>
      

      
      .profile-rows { margin-top: 6px; }
      .profile-row {
        display: grid;
        grid-template-columns: 220px 1fr 260px;
        align-items: center;
        column-gap: 16px;
        padding: 10px 0;
        min-height: 44px;
        border-bottom: 1px solid rgba(0,0,0,0.06);
      }

      
      .profile-label { font-weight: 700; position: relative; padding-left: 14px; }
      .profile-label::before { content: '•'; position: absolute; left: 0; top: 0; }

      
      .profile-value [data-view] { display: inline; }
      .profile-value [data-edit] { display: none; }             
      .profile-value [data-edit].is-open { display: flex !important; } 

      
      .profile-input { min-width: 360px; max-width: 560px; padding: 6px 8px; box-sizing: border-box; }
      .profile-addr-grid { display: grid; grid-template-columns: repeat(4, minmax(130px,1fr)); gap: 6px; width: 100%; }
      .profile-addr-grid input { padding: 6px 8px; box-sizing: border-box; }

      
      .profile-actions { display: flex; justify-content: flex-end; align-items: center; gap: 10px; }
      .profile-btn { min-width: 120px; padding: 6px 12px; text-align: center; white-space: nowrap; }
      .profile-btn:disabled { opacity: .6; cursor: not-allowed; }
    </style>
  </head>
  <body>
    
    <header>
      <div class="logo-title-row">
        <img src="../logo2.jpg" alt="Logo" class="logo" />
        <h1 class="site-title">Student Dashboard</h1>
      </div>
    </header>

    <div class="dashboard-container">
      <!-- Sidebar -->
      <aside class="sidebar">
        <nav>
          <ul>
            <li><button class="sidebarButton" id="thesisviewBtn">Προβολή Θέματος</button></li>
            <li><button class="sidebarButton active" id="profileBtn">Επεξεργασία Προφίλ</button></li>
            <li><button class="sidebarButton" id="managethesesBtn">Διαχείριση Διπλωματικής Εργασίας</button></li>
            <li><button class="sidebarButton" id="logoutBtn">Logout</button></li>
          </ul>
        </nav>
      </aside>

      
      <main class="dashboard-with-sidebar">
        <section class="announcements">
          <h2>Επεξεργασία Προφίλ</h2>

          <div class="profile-rows">

            <!-- Διεύθυνση  -->
            <div class="profile-row" data-row="address">
              <div class="profile-label">Πλήρης διεύθυνση:</div>
              <div class="profile-value">
                <span data-view><?php echo h($me['full_address'] ?? '—'); ?></span>
                <div data-edit>
                  <div class="profile-addr-grid">
                    <input type="text" data-street   placeholder="Οδός"     value="<?php echo h($me['street'] ?? ''); ?>">
                    <input type="text" data-streetno placeholder="Αριθμός"  value="<?php echo h($me['street_number'] ?? ''); ?>">
                    <input type="text" data-city     placeholder="Πόλη"     value="<?php echo h($me['city'] ?? ''); ?>">
                    <input type="text" data-postcode placeholder="ΤΚ"       value="<?php echo h($me['postcode'] ?? ''); ?>">
                  </div>
                </div>
              </div>
              <div class="profile-actions">
                <button type="button" class="submit-btn profile-btn edit-btn">Επεξεργασία</button>
                <button type="button" class="submit-btn profile-btn save-btn" disabled>Αποθήκευση</button>
              </div>
            </div>

            <!-- Email -->
            <div class="profile-row" data-row="email">
              <div class="profile-label">Email επικοινωνίας:</div>
              <div class="profile-value">
                <span data-view><?php echo h($me['email'] ?? '—'); ?></span>
                <div data-edit>
                  <input type="text" class="profile-input" data-input value="<?php echo h($me['email'] ?? ''); ?>">
                </div>
              </div>
              <div class="profile-actions">
                <button type="button" class="submit-btn profile-btn edit-btn">Επεξεργασία</button>
                <button type="button" class="submit-btn profile-btn save-btn" disabled>Αποθήκευση</button>
              </div>
            </div>

            <!-- Κινητό -->
            <div class="profile-row" data-row="mobile">
              <div class="profile-label">Κινητό τηλέφωνο:</div>
              <div class="profile-value">
                <span data-view><?php echo h($me['mobile'] ?? '—'); ?></span>
                <div data-edit>
                  <input type="text" class="profile-input" data-input value="<?php echo h($me['mobile'] ?? ''); ?>">
                </div>
              </div>
              <div class="profile-actions">
                <button type="button" class="submit-btn profile-btn edit-btn">Επεξεργασία</button>
                <button type="button" class="submit-btn profile-btn save-btn" disabled>Αποθήκευση</button>
              </div>
            </div>

            <!-- Σταθερό -->
            <div class="profile-row" data-row="phone">
              <div class="profile-label">Σταθερό τηλέφωνο:</div>
              <div class="profile-value">
                <span data-view><?php echo h($me['phone'] ?? '—'); ?></span>
                <div data-edit>
                  <input type="text" class="profile-input" data-input value="<?php echo h($me['phone'] ?? ''); ?>">
                </div>
              </div>
              <div class="profile-actions">
                <button type="button" class="submit-btn profile-btn edit-btn">Επεξεργασία</button>
                <button type="button" class="submit-btn profile-btn save-btn" disabled>Αποθήκευση</button>
              </div>
            </div>

          </div>
        </section>
      </main>
    </div>

    <script>
    
    document.getElementById('thesisviewBtn')?.addEventListener('click', (e)=>{ e.preventDefault(); location.href='thesisview.php'; });
    document.getElementById('profileBtn')?.addEventListener('click', (e)=>{ e.preventDefault(); location.href='profile.php'; });
    document.getElementById('managethesesBtn')?.addEventListener('click', (e)=>{ e.preventDefault(); location.href='thesisview.php?tab=manage'; });
    document.getElementById('logoutBtn')?.addEventListener('click', (e)=>{ e.preventDefault(); location.href='../logout.php'; });

    
    function parts(rowEl){
      return {
        view:    rowEl.querySelector('[data-view]'),
        editBox: rowEl.querySelector('[data-edit]'),
        editBtn: rowEl.querySelector('.edit-btn'),
        saveBtn: rowEl.querySelector('.save-btn')
      };
    }
    function openEdit(rowEl){
      const p = parts(rowEl);
      if (!p.view || !p.editBox) return;
      p.view.style.display = 'none';
      p.editBox.classList.add('is-open');
      p.editBox.style.display = 'flex'; 
    }
    function closeEdit(rowEl){
      const p = parts(rowEl);
      if (!p.view || !p.editBox) return;
      p.editBox.classList.remove('is-open');
      p.editBox.style.display = 'none';
      p.view.style.display = '';
    }
    function snapshot(rowEl){
      const kind = rowEl.getAttribute('data-row');
      if (kind === 'address') {
        return {
          street:   rowEl.querySelector('[data-street]')?.value ?? '',
          streetno: rowEl.querySelector('[data-streetno]')?.value ?? '',
          city:     rowEl.querySelector('[data-city]')?.value ?? '',
          postcode: rowEl.querySelector('[data-postcode]')?.value ?? ''
        };
      }
      return { value: rowEl.querySelector('[data-edit] [data-input]')?.value ?? '' };
    }
    const same = (a,b) => JSON.stringify(a)===JSON.stringify(b);

    
    document.querySelectorAll('.profile-row').forEach(rowEl => {
      const kind = rowEl.getAttribute('data-row');
      const p = parts(rowEl);
      if (!p.editBtn || !p.saveBtn || !p.view || !p.editBox) { console.warn('Incomplete row DOM for', kind); return; }

      let original = snapshot(rowEl);

      p.editBtn.addEventListener('click', () => {
        openEdit(rowEl);
        original = snapshot(rowEl);
        const first = rowEl.querySelector('[data-edit] input');
        if (first) first.focus();
        p.saveBtn.disabled = true;
      });

      const onChange = () => { p.saveBtn.disabled = same(original, snapshot(rowEl)); };

      if (kind === 'address') {
        ['[data-street]','[data-streetno]','[data-city]','[data-postcode]'].forEach(sel=>{
          const inp = rowEl.querySelector(sel);
          if (inp) inp.addEventListener('input', onChange);
        });
      } else {
        const inp = rowEl.querySelector('[data-edit] [data-input]');
        if (inp) inp.addEventListener('input', onChange);
      }

      p.saveBtn.addEventListener('click', async () => {
        const fd = new FormData();
        fd.append('action', 'save_profile_field');
        fd.append('field', kind);

        if (kind === 'address') {
          const s = snapshot(rowEl);
          fd.append('street',   s.street);
          fd.append('streetno', s.streetno);
          fd.append('city',     s.city);
          fd.append('postcode', s.postcode);
        } else {
          fd.append('value', snapshot(rowEl).value);
        }

        try {
          const r = await fetch('profile_actions.php', { method:'POST', body: fd, credentials:'same-origin' });
          const j = await r.json();
          if (!j || !j.success) { alert(j && j.message ? j.message : 'Αποτυχία αποθήκευσης'); return; }

         
          if (kind === 'address') {
            const s = snapshot(rowEl);
            p.view.textContent = `${s.street} ${s.streetno}, ${s.city} ${s.postcode}`.trim();
          } else {
            p.view.textContent = snapshot(rowEl).value || '—';
          }
          closeEdit(rowEl);
          p.saveBtn.disabled = true;
        } catch(e) {
          alert('Σφάλμα δικτύου');
        }
      });
    });
    </script>
  </body>
</html>
