<?php
session_start();

$role = "guest";
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    $role = $_SESSION['role']; // admin
    
}


// ✅ Zip logic
if (isset($_POST['download_zip'])) {
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        $showNotification = "❌ Unauthorized Access! Login required.";
    } else {
        $zip = new ZipArchive();
        $zipName = "backup_" . date("Ymd_His") . ".zip";

        if ($zip->open($zipName, ZipArchive::CREATE) === TRUE) {
            $zip->addFile("3month.json");
            $zip->addFile("6month.json");
            $zip->addFile("12month.json");
            $zip->addFile("all_members.json");
            $zip->addFile("sahyog.json");
            $zip->close();

            header('Content-Type: application/zip');
            header("Content-Disposition: attachment; filename=$zipName");
            header('Content-Length: ' . filesize($zipName));
            flush();
            readfile($zipName);
            unlink($zipName);
            exit;
        } else {
            $showNotification = "❌ Zip बनाने में error!";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="hi">
<head>

  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="portal.css">
  <title>सदस्य सूची (Edit, Search, Filter)</title>

</head>

<style>
 /*  Dark Mode Styles */
body.dark-mode {
  background: #121212;
  color: #e0e0e0;
}

body.dark-mode .link-bar {
  background: #1e1e1e;
}

body.dark-mode .link-bar button {
  background: #333;
  color: #fff;
  border: 1px solid #555;
}

body.dark-mode .link-bar button:hover {
  background: #444;
  color: #fbc02d;
}

body.dark-mode #searchInput,
body.dark-mode #filterMode {
  background: #222;
  color: #eee;
  border: 1px solid #444;
}

body.dark-mode table {
  background: #1e1e1e;
  color: #e0e0e0;
}

body.dark-mode th {
  background: #333;
  color: #fdd835;
}

body.dark-mode td {
  border-bottom: 1px solid #444;
}

body.dark-mode tr:hover {
  background: #2c2c2c;
}

body.dark-mode #totalAmount {
  background: #333;
  color: #fdd835;
  box-shadow: 0 0 10px rgba(255, 255, 255, 0.1);
}

body.dark-mode .modal-content {
  background: #1f1f1f;
  color: #eee;
}

body.dark-mode .modal-content input,
body.dark-mode .modal-content select {
  background: #2b2b2b;
  color: #fff;
  border: 1px solid #555;
}

body.dark-mode .modal-content button {
  background: #444;
  color: #fdd835;
}

body.dark-mode #notification {
  background: #333;
  color: #fdd835;
}

body.dark-mode .ticker-bar {
  background: #222;
  color: #fdd835;
}

body.dark-mode #loginBtn {
  background: linear-gradient(145deg, #ff9800, #f57c00);
  color: black;
  font-weight: bold;
}
</style> 
<body>
  <div id="notification">
  <audio id="notifSound" src="notification.mp3" preload="auto" style="display:none;"></audio>
  
<input type="hidden" id="showNotification" value="<?php echo htmlspecialchars($showNotification ?? '', ENT_QUOTES, 'UTF-8'); ?>">
  </div>
  <div class="link-bar">
    <button onclick="loadMembers('all_members.json')">सभी सदस्य</button>
    <button onclick="loadMembers('3month.json')">3 महीने</button>
    <button onclick="loadMembers('6month.json')">6 महीने</button>
    <button onclick="loadMembers('12month.json')">12 महीने</button>
    <button onclick="loadMembers('sahyog.json')">सहयोग राशि</button>
    <button onclick="openAddMemberModal()">Add Member</button>
    <button onclick="window.location.href='receipt.php'">रसीद बनाए</button>
    <button onclick="window.location.href='exp'">खर्च जोड़े</button>
    <button onclick="window.print()">Print</button>
 

  <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true && $_SESSION['role'] === 'admin'): ?>
    <!--  Only for Admin: Backup Download -->
    <form method="post" style="display:inline;">
      <button type="submit" name="download_zip">Download Backup File</button>
    </form>
  <?php endif; ?>

  <?php if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true): ?>
    <!--  Only show Login if not logged in -->
    <button id="loginBtn" onclick="window.location.href='login.php'">🔐 Login</button>
  <?php endif; ?>
  	
  <button onclick="toggleDarkMode()" style="margin: 10px; padding: 6px 14px;">Dark mode</button>
</div>



  <input type="text" id="searchInput" placeholder="खोजें (नाम, रसीद, UTR)">
  <select id="filterMode">
    <option value="">सभी मोड</option>
    <option value="Cash">Cash</option>
    <option value="Online">Online</option>
  </select>

  

  <div class="letterhead">
    <h3>श्री कृष्ण सेवा समिति जोधपुर (रजि.)</h3>
    <p>300/- प्रतिमाह मासिक सदस्य माह .............. का हिसाब विवरण .......... तक</p>
  </div>
  <div id="totalAmount"></div>
  <div id="tickerBar" class="ticker-bar"></div>
  <div id="contentArea"><p>कृपया ऊपर से कोई विकल्प चुनें।</p></div>

<!-- Edit Member Modal -->
<div class="modal" id="editModal">
  <div class="modal-content">
    <h4>सदस्य संपादित करें</h4>
    <input type="hidden" id="editOriginalId">
    <input type="number" id="editId" placeholder="क्रमांक (ID)">
    <input type="text" id="editName" placeholder="नाम">
    <input type="text" id="editReceipt" placeholder="रसीद नं.">
    <input type="date" id="editDate">
    <select id="editMode">
      <option value="">मोड चुनें</option>
      <option value="Cash">Cash</option>
      <option value="Online">Online</option>
    </select>
    <input type="text" id="editUtr" placeholder="UTR नं.">
    <input type="number" id="editAmount" placeholder="राशि">
    <select id="editStatus">
      <option value="">स्थिति चुनें</option>
      <option value="Paid">Paid</option>
      <option value="Not paid">Not paid</option>
    </select>
    <button class="edit-btn" onclick="saveMember()">Save</button>
    <button class="delete-btn" onclick="deleteMember()">Delete</button>
    <button onclick="closeModal()">Cancel</button>
  </div>
</div>

  <!-- Add Member Modal -->
<div class="modal" id="addModal">
  <div class="modal-content">
    <h4>नया सदस्य जोड़ें</h4>
    <div>
      <p>फाइल चुनें:</p>
      <label><input type="checkbox" name="fileSelect" value="all_members.json"> सभी सदस्य</label><br>
      <label><input type="checkbox" name="fileSelect" value="3month.json"> 3 महीने</label><br>
      <label><input type="checkbox" name="fileSelect" value="6month.json"> 6 महीने</label><br>
      <label><input type="checkbox" name="fileSelect" value="12month.json"> 12 महीने</label><br>
      <label><input type="checkbox" name="fileSelect" value="sahyog.json"> सहयोग राशि</label>
    </div>
    <hr>
    <input type="number" id="addId" placeholder="क्रमांक (ID)">
    <input type="text" id="addName" placeholder="नाम">
    <input type="text" id="addReceipt" placeholder="रसीद नं.">
    <input type="date" id="addDate">
    <select id="addMode">
      <option value="">मोड चुनें</option>
      <option value="Cash">Cash</option>
      <option value="Online">Online</option>
    </select>
    <input type="text" id="addUtr" placeholder="UTR नं.">
    <input type="number" id="addAmount" placeholder="राशि">
    <select id="addStatus">
      <option value="">स्थिति चुनें</option>
      <option value="Paid">Paid</option>
      <option value="Not paid">Not paid</option>
    </select>
    <button onclick="submitNewMember()">Save</button>
    <button onclick="clearAddForm()">Clear</button>
    <button onclick="closeAddModal()">Cancel</button>
  </div>
</div>

<script>
  let currentMembers = [];
  let currentFile = '';

  function loadMembers(file) {
  currentFile = file;

  updateHeaderByFile(file); // ✅ यही लाइन जोड़नी है

  fetch(file + '?v=' + new Date().getTime())
    .then(res => res.json())
    .then(data => {
      currentMembers = data;
      renderTable(data);
    })
    .catch(() => {
      document.getElementById('contentArea').innerHTML = '<p>डेटा लोड करने में समस्या हुई।</p>';
    });
}

  function renderTable(data) {
    const search = document.getElementById('searchInput').value.toLowerCase();
    const filter = document.getElementById('filterMode').value;



    let filtered = data.filter(m => {
      const searchMatch = (m.name || '').toLowerCase().includes(search) 
                        || (m.receipt_no || '').includes(search) 
                        || (m.utr_no || '').includes(search);
      const modeMatch = filter ? m.payment_mode === filter : true;
      return searchMatch && modeMatch;
    });

    let total = filtered.reduce((sum, m) => sum + Number(m.amount || 0), 0);
    let totalCash = filtered.filter(m => m.payment_mode === "Cash").reduce((sum, m) => sum + Number(m.amount || 0), 0);
    let totalOnline = filtered.filter(m => m.payment_mode === "Online").reduce((sum, m) => sum + Number(m.amount || 0), 0);

    document.getElementById('totalAmount').innerHTML = `
      <p>कुल राशि: ₹${total} (Online: ₹${totalOnline} | Cash: ₹${totalCash})</p>
    `;

    const half = Math.ceil(filtered.length / 2);
    const firstHalf = filtered.slice(0, half);
    const secondHalf = filtered.slice(half);

    function createTable(list) {
      let html = `<table>
        <thead>
          <tr>
            <th>क्रमांक</th>
            <th>नाम</th>
            <th>रसीद नं.</th>
            <th>तारीख</th>
            <th>मोड</th>
            <th>UTR</th>
            <th>राशि</th>
            <th>स्थिति</th>
          </tr>
        </thead>
        <tbody>`;
      list.forEach(m => {
        html += `<tr onclick="editMember(${m.id})">
          <td>${m.id}</td>
          <td>${m.name}</td>
          <td>${m.receipt_no || ''}</td>
          <td>${m.payment_date || ''}</td>
          <td>${m.payment_mode || ''}</td>
          <td>${m.utr_no || ''}</td>
          <td>${m.amount || '0'}</td>
          <td>${m.status === "Paid" ? "🟢" : "🔴"}</td>
        </tr>`;
      });
      html += '</tbody></table>';
      return html;
    }

    const twoColumnHtml = `
      <div class="two-column-table">
        ${createTable(firstHalf)}
        ${createTable(secondHalf)}
      </div>
    `;

    document.getElementById('contentArea').innerHTML = twoColumnHtml;
  }

  function editMember(id) {
    const m = currentMembers.find(m => m.id == id);
    document.getElementById('editOriginalId').value = m.id;
    document.getElementById('editId').value = m.id;
    document.getElementById('editName').value = m.name;
    document.getElementById('editReceipt').value = m.receipt_no;
    document.getElementById('editDate').value = m.payment_date || '';
    document.getElementById('editMode').value = m.payment_mode || '';
    document.getElementById('editUtr').value = m.utr_no || '';
    document.getElementById('editAmount').value = m.amount || '';
    document.getElementById('editStatus').value = m.status || '';
    document.getElementById('editModal').style.display = 'flex';
  }

  function closeModal() {
    document.getElementById('editModal').style.display = 'none';
  }

  function saveMember() {
    const originalId = document.getElementById('editOriginalId').value;
    const newId = document.getElementById('editId').value;

    const index = currentMembers.findIndex(m => m.id == originalId);
    if (index === -1) {
      showNotification("Member नहीं मिला।");
      return;
    }

    if (currentMembers.some((m, i) => m.id == newId && i !== index)) {
      showNotification("यह ID पहले से मौजूद है। कृपया कोई और ID डालें।");
      return;
    }

    currentMembers[index].id = newId;
    currentMembers[index].name = document.getElementById('editName').value;
    currentMembers[index].receipt_no = document.getElementById('editReceipt').value;
    currentMembers[index].payment_date = document.getElementById('editDate').value;
    currentMembers[index].payment_mode = document.getElementById('editMode').value;
    currentMembers[index].utr_no = document.getElementById('editUtr').value;
    currentMembers[index].amount = document.getElementById('editAmount').value;
    currentMembers[index].status = document.getElementById('editStatus').value;

    fetch('update_member.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ file: currentFile, members: currentMembers })
    })
    .then(res => res.text())
    .then(response => {
      showNotification(response);
      renderTable(currentMembers);
      closeModal();
    })
    .catch(() => showNotification("Save failed!"));
  }

  function openAddMemberModal() {
    document.getElementById('addModal').style.display = 'flex';
  }

  function closeAddModal() {
    document.getElementById('addModal').style.display = 'none';
  }

  function generateId() {
    return Math.floor(Math.random() * 1000000);
  }

  function submitNewMember() {
    const selectedFiles = Array.from(document.querySelectorAll('input[name="fileSelect"]:checked'))
      .map(cb => cb.value);

    const newId = document.getElementById('addId').value || generateId();

    if (currentMembers.some(m => m.id == newId)) {
      showNotification("यह ID पहले से मौजूद है। कृपया कोई और ID डालें।");
      return;
    }

    if (selectedFiles.length === 0) {
      showNotification("कृपया कम से कम एक JSON फ़ाइल चुनें।");
      return;
    }

    const newMember = {
      id: newId,
      name: document.getElementById('addName').value,
      receipt_no: document.getElementById('addReceipt').value,
      payment_date: document.getElementById('addDate').value,
      payment_mode: document.getElementById('addMode').value,
      utr_no: document.getElementById('addUtr').value,
      amount: document.getElementById('addAmount').value,
      status: document.getElementById('addStatus').value
    };

    fetch('add_member.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ files: selectedFiles, member: newMember })
    })
    .then(res => res.text())
    .then(response => {
      showNotification(response);
      closeAddModal();
      if (selectedFiles.includes(currentFile)) {
        loadMembers(currentFile);
      }
    })
    .catch(() => showNotification("Save failed"));
  }

  document.getElementById('searchInput').addEventListener('input', () => renderTable(currentMembers));
  document.getElementById('filterMode').addEventListener('change', () => renderTable(currentMembers));

  function clearAddForm() {
    document.getElementById('addId').value = '';
    document.getElementById('addName').value = '';
    document.getElementById('addReceipt').value = '';
    document.getElementById('addDate').value = '';
    document.getElementById('addMode').value = '';
    document.getElementById('addUtr').value = '';
    document.getElementById('addAmount').value = '';
    document.getElementById('addStatus').value = '';
    document.querySelectorAll('input[name="fileSelect"]').forEach(cb => cb.checked = false);
  }

  function deleteMember() {
    const id = document.getElementById('editId').value;
    if (!confirm(`क्या आप वाकई इस सदस्य (ID: ${id}) को डिलीट करना चाहते हैं?`)) return;

    currentMembers = currentMembers.filter(m => m.id != id);

    fetch('update_member.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ file: currentFile, members: currentMembers })
    })
    .then(res => res.text())
    .then(response => {
      showNotification("सदस्य सफलतापूर्वक डिलीट हो गया!");
      closeModal();
      renderTable(currentMembers);
    })
    .catch(() => {
      showNotification("डिलीट फेल हुआ!");
    });
  }
  
  function showNotification(message, duration = 3000) {
  const notif = document.getElementById('notification');
  notif.textContent = message;
  notif.classList.add('show');

  // Audio element create or reuse
  let sound = document.getElementById('notifSound');
  if (!sound) {
    sound = document.createElement('audio');
    sound.id = 'notifSound';
    sound.src = 'notification.mp3'; // अपनी mp3 फाइल का नाम और path यही डालो
    sound.preload = 'auto';
    document.body.appendChild(sound);
  }

  // Play sound
  sound.currentTime = 0;
  sound.play().catch(e => {
    // Mobile में auto play block हो सकता है
    console.log("Sound play failed:", e);
  });

  // Hide after duration
  setTimeout(() => {
    notif.classList.remove('show');
  }, duration);
}

function updateHeaderByFile(fileName) {
  const now = new Date();
  const monthNames = [
    "जनवरी", "फरवरी", "मार्च", "अप्रैल", "मई", "जून",
    "जुलाई", "अगस्त", "सितम्बर", "अक्टूबर", "नवम्बर", "दिसम्बर"
  ];
  const currentMonth = monthNames[now.getMonth()];
  const currentYear = now.getFullYear();

  const currentQuarter = (() => {
    const q = Math.floor(now.getMonth() / 3) + 1;
    return `Q${q} (${monthNames[q * 3 - 3]} - ${monthNames[q * 3 - 1]})`;
  })();

  let headerText = "";
  let amount = "";

  if (fileName === "all_members.json") {
    amount = "₹300";
    headerText = `${amount}/- प्रतिमाह मासिक सदस्य माह ${currentMonth} का हिसाब विवरण ${currentYear} तक`;
  } else if (fileName === "3month.json") {
    amount = "₹900";
    headerText = `${amount}/- प्रति त्रिमासिक सदस्य माह ${currentQuarter} का हिसाब विवरण ${currentYear} तक`;
  } else if (fileName === "6month.json") {
    amount = "₹1800";
    headerText = `${amount}/- प्रति अर्धवार्षिक सदस्य माह जनवरी - जून / जुलाई - दिसम्बर का हिसाब विवरण ${currentYear} तक`;
  } else if (fileName === "12month.json") {
    amount = "₹3600";
    headerText = `${amount}/- वार्षिक सदस्य माह जनवरी - दिसम्बर का हिसाब विवरण ${currentYear} तक`;
  } else if (fileName === "sahyog.json") {
    headerText = `सहयोग राशि का विवरण माह ${currentMonth} / ${currentYear} तक`;
  } else {
    headerText = `माह का हिसाब विवरण ${currentYear} तक`;
  }

  // यह लाइन हेडर टेक्स्ट को बदलती है:
  document.querySelector('.letterhead p').innerText = headerText;
}
</script>

<!-- ✅ Update Log Popup (No Textarea) -->
<div id="updateLogModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background-color:rgba(0,0,0,0.4);z-index:9999;justify-content:center;align-items:center;">
  <div style="background:#fff;padding:20px 25px;border-radius:12px;width:90%;max-width:450px;box-shadow:0 10px 20px rgba(0,0,0,0.25);font-family:'Noto Sans Devanagari', sans-serif;">
    <h3 style="margin:0 0 10px;color:#0d47a1;">🔔 हालिया अपडेट</h3>
    <ul style="padding-left:18px;">
      <li>🔥 हेडर में खुद से राशि जुड़ रही है</li>
      <li>🖨️ प्रिंट में ऊपर के बटन नहीं दिखते</li>
      <li>📋 प्रिंट के लिए Responsive डिज़ाइन</li>
      <li>🔔 नोटिफिकेशन में आवाज़ और एनिमेशन</li>
    </ul>
    <div style="text-align:right;margin-top:12px;">
      <button onclick="closeUpdateLog()" style="padding:8px 16px;border:none;border-radius:6px;background:linear-gradient(135deg,#fdd835,#fbc02d);color:#0d47a1;font-weight:bold;cursor:pointer;">ठीक है</button>
    </div>
  </div>
</div>
<script>
  const userRole = "<?php echo $_SESSION['role']; ?>";
</script>

<script>
  window.addEventListener('load', () => {
    if (!localStorage.getItem('updateLogSeen')) {
      document.getElementById('updateLogModal').style.display = 'flex';
    }
  });

  function closeUpdateLog() {
    document.getElementById('updateLogModal').style.display = 'none';
    localStorage.setItem('updateLogSeen', 'true');
  }
  
  const jsonFilesToLoad = [
  'all_members.json',
  '3month.json',
  '6month.json',
  '12month.json',
  'sahyog.json'
];

const jsonFileLabels = {
  'all_members.json': 'मासिक',
  '3month.json': 'त्रैमासिक',
  '6month.json': 'अर्धवार्षिक',
  '12month.json': 'वार्षिक',
  'sahyog.json': 'सहयोग राशि'
};

function loadAllJsonAndTicker() {
  const rates = {
    'all_members.json': 300,
    '3month.json': 900,
    '6month.json': 1800,
    '12month.json': 3600,
    'sahyog.json': null
  };

  const promises = jsonFilesToLoad.map(file =>
    fetch(file)
      .then(res => res.json())
      .then(data => {
        const count = data.length;
        let total = 0;

        if (rates[file]) {
          total = count * rates[file];
        } else {
          total = data.reduce((sum, m) => sum + Number(m.amount || 0), 0);
        }

        return { file, count, total };
      })
      .catch(() => ({ file, count: 0, total: 0 }))
  );

  Promise.all(promises).then(results => {
    const messages = results.map(r => {
      const label = jsonFileLabels[r.file] || r.file;
      return `${label} (${r.count} सदस्य — ₹${r.total})`;
    });

    const tickerText = messages.join(" • ");
    document.getElementById('tickerBar').innerHTML = `<span>${tickerText} • ${tickerText}</span>`;
  });
}

window.onload = function () {
  loadAllJsonAndTicker();
};
  
  if (userRole !== 'admin') {
  document.querySelectorAll('.edit-btn, .delete-btn, .add-ledger-btn').forEach(btn => {
    btn.style.display = 'none';
  });
}

window.addEventListener("load", () => {
  const msg = document.getElementById("errorMessage").value;
  if (msg) {
    showNotification(msg);
  }
});

  // Load from storage
  if (localStorage.getItem('darkMode') === 'enabled') {
    document.body.classList.add('dark-mode');
  }

  function toggleDarkMode() {
    const body = document.body;
    if (body.classList.contains('dark-mode')) {
      body.classList.remove('dark-mode');
      localStorage.setItem('darkMode', 'disabled');
    } else {
      body.classList.add('dark-mode');
      localStorage.setItem('darkMode', 'enabled');
    }
  }
</script>
</body>
</html>