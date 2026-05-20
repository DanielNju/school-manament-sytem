// === SLIDER CONTROL ===

// Track current slide index for each section
const slideIndex = {
  administration: 0,
  infrastructure: 0,
  cor_curriculum: 0
};

// Get total number of slides in a section
function getTotalSlides(sectionId) {
  const track = document.querySelector(`#${sectionId} .slide-track`);
  return track ? track.children.length : 0;
}

// Move to next or previous slide
function moveSlide(sectionId, direction) {
  const track = document.querySelector(`#${sectionId} .slide-track`);
  if (!track) return;

  const total = getTotalSlides(sectionId);
  slideIndex[sectionId] = (slideIndex[sectionId] + direction + total) % total;
  track.style.transform = `translateX(-${slideIndex[sectionId] * 100}%)`;
}

// Start auto-sliding for a section
function startAutoSlide(sectionId, interval = 3000) {
  setInterval(() => {
    moveSlide(sectionId, 1);
  }, interval);
}


// === MAP LOGIC ===

let map, userMarker, routeLine;

// 📍 High School Coordinates
const SCHOOL_LAT = -1.078244;
const SCHOOL_LNG = 36.747846;
const SCHOOL_NAME = "Githiga Boys High School";

// Initialize map centered on school
function initMap() {
  if (map) return;

  map = L.map('map-display').setView([SCHOOL_LAT, SCHOOL_LNG], 15);

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors',
    maxZoom: 19
  }).addTo(map);

  L.marker([SCHOOL_LAT, SCHOOL_LNG])
    .addTo(map)
    .bindPopup(`<b>${SCHOOL_NAME}</b>`)
    .openPopup();
}

// Get user's location
function getLocation() {
  const userLocationDiv = document.getElementById("user-location");
  if (!userLocationDiv) return;

  userLocationDiv.innerHTML = "Finding your location...";

  if (!navigator.geolocation) {
    userLocationDiv.innerHTML = "Geolocation not supported.";
    return;
  }

  navigator.geolocation.getCurrentPosition(
    (position) => {
      const { latitude, longitude, accuracy } = position.coords;

      // ✅ Validate coordinates
      if (
        typeof latitude !== "number" ||
        typeof longitude !== "number" ||
        isNaN(latitude) ||
        isNaN(longitude)
      ) {
        userLocationDiv.innerHTML = "Invalid location data.";
        return;
      }

      userLocationDiv.innerHTML = `
        <strong>Your Location:</strong> ${latitude.toFixed(6)}, ${longitude.toFixed(6)}<br>
        <small>Accuracy: ±${Math.round(accuracy)} meters</small>
      `;

      map.setView([latitude, longitude], 14);

      if (userMarker) {
        userMarker.setLatLng([latitude, longitude]);
      } else {
        userMarker = L.marker([latitude, longitude])
          .addTo(map)
          .bindPopup(`You are here<br>±${Math.round(accuracy)}m`)
          .openPopup();
      }

      fetchRoute(latitude, longitude);
    },
    (error) => {
      let msg = "Unable to get your location.";
      if (error.code === error.PERMISSION_DENIED) msg = "Please allow location access.";
      else if (error.code === error.POSITION_UNAVAILABLE) msg = "Location unavailable.";
      else if (error.code === error.TIMEOUT) msg = "Location request timed out.";

      userLocationDiv.innerHTML = msg;
      console.error("Geolocation error:", error);
    },
    {
      enableHighAccuracy: true,
      timeout: 15000,
      maximumAge: 0
    }
  );
}

// Fetch route via OSRM (with timeout + guards)
function fetchRoute(userLat, userLng) {
  // ✅ Guard against invalid coords
  if (
    typeof userLat !== "number" ||
    typeof userLng !== "number" ||
    typeof SCHOOL_LAT !== "number" ||
    typeof SCHOOL_LNG !== "number"
  ) {
    console.error("Invalid coordinates", { userLat, userLng });
    return;
  }

  if (!map) return;

  const osrmUrl = `https://router.project-osrm.org/route/v1/driving/${userLng},${userLat};${SCHOOL_LNG},${SCHOOL_LAT}?overview=full&geometries=geojson`;

  if (routeLine) {
    map.removeLayer(routeLine);
    routeLine = null;
  }

  // ✅ Fetch timeout protection
  const controller = new AbortController();
  const timeoutId = setTimeout(() => controller.abort(), 8000);

  fetch(osrmUrl, { signal: controller.signal })
    .then(response => {
      clearTimeout(timeoutId);
      if (!response.ok) throw new Error("OSRM network error");
      return response.json();
    })
    .then(data => {
      if (data.code !== "Ok" || !data.routes.length) {
        throw new Error("No route found");
      }

      const route = data.routes[0];
      const distance = (route.distance / 1000).toFixed(2);
      const duration = (route.duration / 60).toFixed(1);

      routeLine = L.polyline(
        route.geometry.coordinates.map(([lng, lat]) => [lat, lng]),
        { color: '#4285F4', weight: 6, opacity: 0.8 }
      ).addTo(map);

      map.fitBounds(routeLine.getBounds(), { padding: [40, 40] });

      routeLine.bindPopup(`
        🚗 <b>Route to ${SCHOOL_NAME}</b><br>
        Distance: ${distance} km<br>
        Time: ${duration} min
      `).openPopup();
    })
    .catch(err => {
      clearTimeout(timeoutId);
      if (err.name === "AbortError") {
        alert("Routing service timed out. Please try again.");
      } else {
        alert("Could not load route.");
      }
      console.error("Route error:", err);
    });
}


// === CHART & PDF PROCESSING ===

 let kcseChart = null;
let subjectChart = null;

// ✅ Safely get elements
const pdfFileInput = document.getElementById('pdfFile');
const yearSelect = document.getElementById('yearSelect');
const startBtn = document.getElementById('startBtn');
const statusBox = document.getElementById('status');
const preview = document.getElementById('preview');

// Initialize PDF.js
if (typeof pdfjsLib !== 'undefined') {
  pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.4.120/pdf.worker.min.js';
}

// ✅ Unified setStatus function: always pass the target element
function setStatus(el, msg, isError = false) {
  if (!el) return;
  el.style.color = isError ? '#d32f2f' : '#388e3c';
  el.textContent = msg;
}

// Only proceed if all required elements exist
if (startBtn && statusBox && pdfFileInput && yearSelect) {
  startBtn.addEventListener('click', async () => {
    try {
      const file = pdfFileInput.files[0];
      const year = yearSelect.value;

      if (!file) return setStatus(statusBox, '❌ Please select a PDF file.', true);
      if (!year) return setStatus(statusBox, '❌ Please select a year.', true);

      setStatus(statusBox, '📤 Sending PDF to PHP (which calls Python)...');

      const formData = new FormData();
      formData.append('pdf', file);
      formData.append('year', year);

      const resp = await fetch('slms.php?action=parse_pdf', {
        method: 'POST',
        body: formData
      });

      if (!resp.ok) throw new Error(`HTTP ${resp.status}`);

      const result = await resp.json();

      if (result.success) {
        const csvText = result.csv;
        preview.textContent = csvText.length > 20000
          ? csvText.substring(0, 20000) + '\n\n...(truncated)'
          : csvText;

        setStatus(statusBox, '🚀 Uploading to MySQL via PHP...');
        const importResp = await fetch('slms.php?action=import_csv', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ csv: csvText, year })
        });

        const importResult = await importResp.json();
        if (importResult.success) {
          setStatus(statusBox, `✅ Success: ${importResult.count} records imported.`);
        } else {
          setStatus(statusBox, `❌ Import failed: ${importResult.message}`, true);
        }
      } else {
        setStatus(statusBox, `❌ Parsing failed: ${result.error}`, true);
      }

      await loadYearChart();
      await loadSubjectChart();

    } catch (err) {
      setStatus(statusBox, `🚨 ${err.message}`, true);
    }
  });
}

async function loadYearChart() {
  try {
    const resp = await fetch('slms.php?action=fetch_years');
    const json = await resp.json();
    if (!json.ok || !json.data?.length) {
      const el = document.getElementById('status');
      if (el) setStatus(el, '⚠️ No yearly data available', false);
      return;
    }

    const labels = json.data.map(r => `Year ${r.year}`);
    const pts = json.data.map(r => Number(r.avg_points).toFixed(2));

    const ctx = document.getElementById('kcseChart')?.getContext('2d');
    if (!ctx) return;

    if (kcseChart) kcseChart.destroy();

    kcseChart = new Chart(ctx, {
      type: 'bar',
      data: {
        labels,
        datasets: [{
          label: 'Avg Mean Points (1–12)',
          data: pts,
          backgroundColor: 'rgba(0, 255, 255, 0.6)',
          borderColor: 'cyan',
          borderWidth: 1
        }]
      },
      options: {
        responsive: true,
        scales: { y: { beginAtZero: true, max: 12, ticks: { stepSize: 1 } } }
      }
    });
  } catch (e) {
    const el = document.getElementById('status');
    if (el) setStatus(el, '⚠️ Year chart failed', false);
  }
}

async function loadSubjectChart() {
  try {
    const resp = await fetch('slms.php?action=fetch_subjects');
    const json = await resp.json();
    if (!json.ok || !json.data?.length) {
      const el = document.getElementById('status');
      if (el) setStatus(el, '⚠️ No subject data available', false);
      return;
    }

    const labels = json.data.map(d => d.subject);
    const vals = json.data.map(d => d.avg);

    const ctx = document.getElementById('subjectChart')?.getContext('2d');
    if (!ctx) return;

    if (subjectChart) subjectChart.destroy();

    subjectChart = new Chart(ctx, {
      type: 'bar',
      data: {
        labels,
        datasets: [{
          label: `Subject Avg – Year ${json.year}`,
          data: vals,
          backgroundColor: 'orange',
          borderWidth: 1
        }]
      },
      options: {
        indexAxis: 'y',
        responsive: true,
        scales: {
          x: { beginAtZero: true, max: 100 },
          y: { beginAtZero: true }
        }
      }
    });
  } catch (e) {
    const el = document.getElementById('status');
    if (el) setStatus(el, '⚠️ Subject chart failed', false);
  }
} 
// === LOGIN & SESSION MANAGEMENT ===

const loginBtn = document.querySelector('nav li a[href="#login"]');
const loginModal = document.getElementById('loginModal');
const closeLogin = document.getElementById('closeLogin');
const loginForm = document.getElementById('loginForm');
const loginError = document.getElementById('loginError');

// Restore session on load
restoreSession();

// Modal handling
if (loginBtn) {
  loginBtn.addEventListener('click', e => {
    e.preventDefault();
    if (loginModal) loginModal.style.display = 'block';
  });
}

if (closeLogin) {
  closeLogin.addEventListener('click', () => {
    if (loginModal) loginModal.style.display = 'none';
  });
}

window.addEventListener('click', e => {
  if (loginModal && e.target === loginModal) {
    loginModal.style.display = 'none';
  }
});

// Login form submission
if (loginForm) {
  loginForm.addEventListener('submit', async e => {
    e.preventDefault();
    if (loginError) setFormStatus(loginError, '⏳ Logging in...', 'black');

    const username = document.getElementById('loginUsername')?.value.trim();
    const password = document.getElementById('loginPassword')?.value;

    if (!username || !password) {
      if (loginError) setFormStatus(loginError, '❌ Username and password are required.', 'red');
      return;
    }

    const data = new URLSearchParams({
      action: 'login',
      username,
      password
    });

    try {
      const res = await fetch('slms.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: data
      });

      const text = await res.text();

      if (!text.trim().startsWith('{')) {
        if (loginError) setFormStatus(loginError, '❌ Server returned invalid data.', 'red');
        return;
      }

      const result = JSON.parse(text);

      if (result.ok) {
        const userData = {
          role: result.role,
          id: result.id,
          name: result.name,
          class: result.class || '',
          stream: result.stream || '',
          timestamp: Date.now()
        };
        sessionStorage.setItem('user', JSON.stringify(userData));

        hideMainContent();
        showDashboard(userData);
        addLogoutButton();
        updateNavbarOnLogin(true, userData);

        document.getElementById(`${result.role}_dashboard`)?.scrollIntoView({ behavior: 'smooth' });
        if (loginModal) loginModal.style.display = 'none';
        if (loginForm) loginForm.reset();
      } else {
        if (loginError) setFormStatus(loginError, '❌ ' + (result.msg || 'Login failed'), 'red');
      }
    } catch (err) {
      if (loginError) setFormStatus(loginError, '🚨 Network error. Try again.', 'red');
    }
  });
}

// Restore session
function restoreSession() {
  const saved = sessionStorage.getItem('user');
  if (!saved) return;

  const userData = JSON.parse(saved);
  const maxAge = 24 * 60 * 60 * 1000; // 24 hours

  if (Date.now() - userData.timestamp > maxAge) {
    logout();
    return;
  }

  hideMainContent();
  showDashboard(userData);
  addLogoutButton();
  updateNavbarOnLogin(true, userData); // 
}

// UI helpers
function hideMainContent() {
  document.querySelectorAll('header, #mission, #vission, #location, #administration, #infrastructure, #cor_curriculum, #religious, #public-charts')
    .forEach(el => {
      if (el) el.style.display = 'none';
    });
}

function addLogoutButton() {
  if (document.getElementById('logout-btn')) return;
  const logoutBtn = document.createElement('li');
  logoutBtn.id = 'logout-btn';
  logoutBtn.innerHTML = `<a href="#" onclick="event.preventDefault(); logout();">Logout</a>`;
  const navList = document.querySelector('nav ul');
  if (navList) navList.appendChild(logoutBtn);
}

function setFormStatus(el, msg, color) {
  if (!el) return;
  el.style.color = color;
  el.textContent = msg;
}

window.logout = async function () {
  sessionStorage.removeItem('user');
  const logoutBtn = document.getElementById('logout-btn');
  if (logoutBtn) logoutBtn.remove();

  const studentDash = document.getElementById('student_dashboard');
  const adminDash = document.getElementById('admin_dashboard');
  if (studentDash) studentDash.style.display = 'none';
  if (adminDash) adminDash.style.display = 'none';

  document.querySelectorAll('header, #mission, #vission, #location, #administration, #infrastructure, #cor_curriculum, #religious, #public-charts')
    .forEach(el => {
      if (el) el.style.display = 'block';
    });

  try {
    await fetch('slms.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ action: 'logout' })
    });
  } catch (err) {
    // Silent fail - frontend logout is already handled
  }

  window.location.href = 'slms.html?page=home';
  restoreSession();
};


// === STUDENT REGISTRATION ===

document.getElementById('classSelect')?.addEventListener('change', function () {
  const classValue = this.value;
  const streamSelect = document.getElementById('streamSelect');
  if (!streamSelect) return;
  
  streamSelect.innerHTML = '<option value="">-- Select Stream --</option>';

  if (!classValue) return;
  const formNumber = classValue.replace('Form', '');
  const streams = ['Y', 'B', 'P', 'R', 'G', 'O'];

  streams.forEach(stream => {
    const opt = document.createElement('option');
    opt.value = `${formNumber}${stream}`;
    opt.textContent = opt.value;
    streamSelect.appendChild(opt);
  });
});

const registerForm = document.getElementById('registerStudentForm');
const registerStatus = document.getElementById('registerStatus');

if (registerForm) {
  registerForm.addEventListener('submit', async e => {
    e.preventDefault();

    const formData = new FormData(registerForm);
    formData.append('action', 'register_student');

    const name = formData.get('name');
    const username = formData.get('username');
    const admissionNo = formData.get('admissionNo');
    const password = formData.get('password');
    const classVal = formData.get('class');
    const stream = formData.get('stream');

    if (!name || !username || !admissionNo || !password || !classVal || !stream) {
      if (registerStatus) setStatus(registerStatus, '❌ Please fill in all fields', true);
      return;
    }

    if (registerStatus) setStatus(registerStatus, '⏳ Registering student...', false);

    try {
      const res = await fetch('slms.php', {
        method: 'POST',
        body: formData
      });

      const text = await res.text();

      let result;
      try {
        result = JSON.parse(text);
      } catch (err) {
        if (registerStatus) setStatus(registerStatus, '❌ Server returned invalid data.', true);
        return;
      }

      if (result.ok) {
        if (registerStatus) setStatus(registerStatus, result.msg, false);
        registerForm.reset();
        const streamSelect = document.getElementById('streamSelect');
        if (streamSelect) streamSelect.innerHTML = '<option value="">-- Select Stream --</option>';
      } else {
        if (registerStatus) setStatus(registerStatus, '❌ ' + (result.msg || 'Registration failed'), true);
      }
    } catch (err) {
      if (registerStatus) setStatus(registerStatus, '🚨 Network error.', true);
    }
  });
}

// Add this to handle stream selection based on class
document.getElementById('selectClass')?.addEventListener('change', function () {
  const classValue = this.value;
  const streamSelect = document.getElementById('selectStream');
  if (!streamSelect) return;
  
  streamSelect.innerHTML = '<option value="">-- Select Stream --</option>';

  if (!classValue) return;
  const formNumber = classValue.replace('Form', '');
  const streams = ['Y', 'B', 'P', 'R', 'G', 'O'];

  streams.forEach(stream => {
    const opt = document.createElement('option');
    opt.value = `${formNumber}${stream}`;
    opt.textContent = opt.value;
    streamSelect.appendChild(opt);
  });
});

// === MATERIAL UPLOAD FUNCTION ===
function setupUploadMaterialForm() {
    const form = document.getElementById('uploadMaterialForm');
    if (!form) return;

    form.addEventListener('submit', async function (e) {
        e.preventDefault();

        const formData = new FormData(form);
        formData.append('action', 'upload_material');

        // Get both class and stream values
        const classVal = document.getElementById('selectClass').value;
        const streamVal = document.getElementById('selectStream').value;
        
        // Combine class and stream for the database (e.g., "Form4-4Y")
        const combinedClassStream = `${classVal}-${streamVal}`;
        formData.append('class_stream', combinedClassStream);

        // Client-side validation
        const title = formData.get('title')?.toString().trim();
        const subject = formData.get('subject')?.toString().trim();
        const type = formData.get('type')?.toString();
        const file = formData.get('file');

        if (!title || !classVal || !streamVal || !subject || !type || !file || file.size === 0) {
            alert('❌ Please fill in all required fields and select a file');
            return;
        }

        if (!['assignment', 'revision', 'report'].includes(type)) {
            alert('❌ Please select a valid material type');
            return;
        }

        try {
            const response = await fetch('slms.php', {
                method: 'POST',
                body: formData
            });

            // Check if response is OK (status 200–299)
            if (!response.ok) {
                alert('❌ Upload failed. Please try again.');
                return;
            }

            let result;
            const contentType = response.headers.get('content-type');
            if (contentType && contentType.includes('application/json')) {
                result = await response.json();
            } else {
                alert('❌ Server returned invalid response. Please try again.');
                return;
            }

            // Handle success/failure
            if (result.ok) {
                alert('✅ ' + result.msg);
                form.reset();
                // Reset stream dropdown
                document.getElementById('selectStream').innerHTML = '<option value="">-- Select Stream --</option>';
            } else {
                alert('❌ Error: ' + result.msg);
            }

        } catch (error) {
            alert('❌ Network error. Please check your connection and try again.');
        }
    });
}


// Function to load and display materials based on student's class AND stream
function loadStudentMaterials() {
    const materialsList = document.getElementById('materialsList');
    
    if (!materialsList) return;

    materialsList.innerHTML = '<p class="loading-message">Loading materials...</p>';
    
    // Get user data from session storage
    const userData = JSON.parse(sessionStorage.getItem('user'));
    
    if (!userData || !userData.class || !userData.stream) {
        materialsList.innerHTML = '<p class="no-materials">Unable to determine your class and stream.</p>';
        return;
    }
    
    // Create the combined class-stream format (e.g., "Form4-4Y")
    const combinedClassStream = `${userData.class}-${userData.stream}`;
    
    // Prepare the request data - sending the combined class_stream
    const data = new URLSearchParams({
        action: 'student_materials',
        class_stream: combinedClassStream
    });
    
    // Fetch materials for the student's class and stream
    fetch('slms.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: data
    })
    .then(response => response.json())
    .then(result => {
        if (result.ok && result.data && result.data.length > 0) {
            displayMaterials(result.data);
            populateSubjectFilter(result.data);
            setupFilterHandlers();
        } else {
            materialsList.innerHTML = '<p class="no-materials">No materials available for your class and stream.</p>';
        }
    })
    .catch(error => {
        materialsList.innerHTML = '<p class="no-materials">Error loading materials. Please try again later.</p>';
    });
}

// Test function for manual testing
function testMaterialsLoad() {
    loadStudentMaterials();
}

// The rest of the functions remain the same...
function displayMaterials(materials) {
    const materialsList = document.getElementById('materialsList');
    if (!materialsList) return;
    
    materialsList.innerHTML = '';
    
    materials.forEach(material => {
        const materialCard = document.createElement('div');
        materialCard.className = 'material-card';
        materialCard.dataset.subject = material.subject.toLowerCase();
        materialCard.dataset.type = material.type;
        
        materialCard.innerHTML = `
            <h4 class="material-title">${material.title}</h4>
            <div class="material-meta">
                <span class="material-subject">Subject: ${material.subject}</span> | 
                <span class="material-type">Type: ${material.type}</span>
            </div>
            <a href="${material.file_path}" class="download-btn" download>Download</a>
        `;
        
        materialsList.appendChild(materialCard);
    });
}

function populateSubjectFilter(materials) {
    const subjectFilter = document.getElementById('subjectFilter');
    if (!subjectFilter) return;
    
    const subjects = [...new Set(materials.map(m => m.subject))].sort();
    
    while (subjectFilter.options.length > 1) {
        subjectFilter.remove(1);
    }
    
    subjects.forEach(subject => {
        const option = document.createElement('option');
        option.value = subject.toLowerCase();
        option.textContent = subject;
        subjectFilter.appendChild(option);
    });
}

function setupFilterHandlers() {
    const subjectFilter = document.getElementById('subjectFilter');
    const typeFilter = document.getElementById('typeFilter');
    
    if (!subjectFilter || !typeFilter) return;
    
    const filterMaterials = () => {
        const selectedSubject = subjectFilter.value;
        const selectedType = typeFilter.value;
        
        document.querySelectorAll('.material-card').forEach(card => {
            const subjectMatch = !selectedSubject || card.dataset.subject === selectedSubject;
            const typeMatch = !selectedType || card.dataset.type === selectedType;
            
            card.style.display = (subjectMatch && typeMatch) ? 'block' : 'none';
        });
    };
    
    subjectFilter.addEventListener('change', filterMaterials);
    typeFilter.addEventListener('change', filterMaterials);
}
// Stream selection for exam upload form
document.getElementById('2classSelect')?.addEventListener('change', function () {
    const classValue = this.value;
    const streamSelect = document.getElementById('2streamSelect');
    if (!streamSelect) return;
    
    streamSelect.innerHTML = '<option value="">-- Select Stream --</option>';
    
    if (!classValue) return;
    const formNumber = classValue.replace('Form', '');
    const streams = ['Y', 'B', 'P', 'R', 'G', 'O'];
    
    streams.forEach(stream => {
        const opt = document.createElement('option');
        opt.value = `${formNumber}${stream}`;
        opt.textContent = opt.value;
        streamSelect.appendChild(opt);
    });
});

//=====to upload student resluts ====
// === Exam upload form handling ===
function setupExamUploadForm() {
    const form = document.getElementById('examUploadForm');
    if (!form) {
        return;
    }
    
    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        
        const statusBox = document.getElementById('2status');
        const preview = document.getElementById('review');
        const formData = new FormData(form);

        formData.append('action', 'parse_exam_pdf');

        // Get form values
        const classVal = document.getElementById('2classSelect').value;
        const streamVal = document.getElementById('2streamSelect').value;
        const examName = document.getElementById('examNameSelect').value;
        const term = document.getElementById('termSelect').value;
        
        if (!classVal || !streamVal || !examName || !term) {
            setStatus(statusBox, '❌ Please fill in all fields', true);
            return;
        }
        
        // Add additional data to formData
        formData.append('class', classVal);
        formData.append('stream', streamVal);
        formData.append('exam_name', examName);
        formData.append('term', term);
        
        setStatus(statusBox, '📤 Processing exam results...', false);
        
        try {
            // Step 1: Upload PDF and parse it
            const response = await fetch('slms.php', {
                method: 'POST',
                body: formData
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const result = await response.json();
            
            if (result.success) {
                const csvText = result.csv;
                preview.textContent = csvText.length > 20000 
                    ? csvText.substring(0, 20000) + '\n\n...(truncated)' 
                    : csvText;
                
                setStatus(statusBox, '🚀 Uploading to database...', false);
                
                // Step 2: Import CSV into database
                const importResponse = await fetch('slms.php?action=import_exam_csv', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        csv: csvText, 
                        class: classVal,
                        stream: streamVal,
                        exam_name: examName,
                        term: term
                    })
                });
                
                const importResult = await importResponse.json();
                
                if (importResult.success) {
                    setStatus(statusBox, `✅ Success: ${importResult.count} records imported.`, false);
                    form.reset();
                    document.getElementById('2streamSelect').innerHTML = '<option value="">-- Select Stream --</option>';
                } else {
                    setStatus(statusBox, `❌ Import failed: ${importResult.message || 'Unknown error'}`, true);
                }
            } else {
                setStatus(statusBox, `❌ Parsing failed: ${result.error || 'No error message provided'}`, true);
            }
        } catch (error) {
            console.error("Error in exam upload process", error);
            setStatus(statusBox, `🚨 Error: ${error.message}`, true);
        }
    });
}
//==student charts ===
// Load student-specific charts
// === Student Charts ===
async function loadStudentCharts() {
    const userData = JSON.parse(sessionStorage.getItem('user'));
    if (!userData || userData.role !== 'student') return;

    try {
        const response = await fetch('slms.php?action=fetch_student_exams', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                student_id: userData.id
            })
        });

        const result = await response.json();

        if (result.ok && result.data && result.data.length > 0) {
            renderStudentExamChart(result.data, userData);
            renderStudentSubjectChart(result.data, userData, result.data[result.data.length - 1]); // latest exam
            loadClassPerformanceChart();
        } else {
            console.log('No exam data found for student:', result.message || 'No data');
            const chartsContainer = document.querySelector('.charts');
            if (chartsContainer) {
                chartsContainer.innerHTML = `
                    <div class="no-data-message">
                        <h3>📊 No Exam Results Available</h3>
                        <p>Your exam results will appear here once they've been processed by the school.</p>
                    </div>
                `;
            }
        }

    } catch (error) {
        console.error('Error loading student charts:', error);
        const chartsContainer = document.querySelector('.charts');
        if (chartsContainer) {
            chartsContainer.innerHTML = `
                <div class="error-message">
                    <h3>⚠️ Error Loading Results</h3>
                    <p>There was a problem loading your exam results. Please try again later.</p>
                </div>
            `;
        }
    }
}

// === Overall Performance Chart ===
function renderStudentExamChart(examData, userData) {
    const ctx = document.getElementById('studentKcseChart');
    if (!ctx) return;

    if (ctx.chart) ctx.chart.destroy();

    const labels = examData.map(exam => `${exam.exam_name} - ${exam.term}`);
    const meanPoints = examData.map(exam => parseFloat(exam.mean_points) || 0);
    const meanGrades = examData.map(exam => exam.mean_grade || "N/A");

    if (labels.length === 0 || meanPoints.every(score => score === 0)) {
        const chartContainer = ctx.closest('.chart-container');
        if (chartContainer) {
            chartContainer.innerHTML = `
                <div class="no-data-message">
                    <h4>No Performance Data</h4>
                    <p>No performance history available for display</p>
                </div>
            `;
        }
        return;
    }

    const formattedName = userData.name.split(' ').map(word =>
        word.charAt(0).toUpperCase() + word.slice(1).toLowerCase()
    ).join(' ');

    ctx.chart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                label: 'Mean Points',
                data: meanPoints,
                backgroundColor: 'rgba(54, 162, 235, 0.5)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: true,
                    text: `Overall Performance for: ${formattedName}`,
                    font: { size: 16, weight: 'bold' }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const grade = meanGrades[context.dataIndex];
                            return `Mean Points: ${context.formattedValue} (${grade})`;
                        }
                    }
                }
            },
            onClick: (evt, elements) => {
                if (elements.length > 0) {
                    const idx = elements[0].index;
                    renderStudentSubjectChart(examData, userData, examData[idx]); // update subject chart on click
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    max: 12,
                    title: { display: true, text: 'Mean Points' }
                },
                x: {
                    title: { display: true, text: 'Exams' },
                    ticks: { maxRotation: 45, minRotation: 45 }
                }
            }
        }
    });
}

// === Subject Performance Chart ===
function renderStudentSubjectChart(examData, userData, exam) {
    const ctx = document.getElementById('studentSubjectChart');
    if (!ctx || !exam) return;

    if (ctx.chart) ctx.chart.destroy();

    const allSubjects = [
        'english', 'math', 'kiswahili', 'cre', 'chemistry',
        'physics', 'biology', 'geography', 'history',
        'computer', 'agriculture', 'business'
    ];

    const subjectsWithScores = allSubjects
        .map(subject => ({
            name: subject,
            score: parseFloat(exam[subject]) || 0,
            displayName: subject.charAt(0).toUpperCase() + subject.slice(1).replace(/_/g, ' ')
        }))
        .filter(item => item.score > 0)
        .sort((a, b) => b.score - a.score);

    if (subjectsWithScores.length === 0) {
        const chartContainer = ctx.closest('.chart-container');
        if (chartContainer) {
            chartContainer.innerHTML = `
                <div class="no-data-message">
                    <h4>No Subject Data</h4>
                    <p>No subject performance data available for display</p>
                </div>
            `;
        }
        return;
    }

    const labels = subjectsWithScores.map(item => item.displayName);
    const data = subjectsWithScores.map(item => item.score);

    const formattedName = userData.name.split(' ').map(word =>
        word.charAt(0).toUpperCase() + word.slice(1).toLowerCase()
    ).join(' ');

    ctx.chart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                label: `Scores - ${exam.exam_name} (${exam.term})`,
                data,
                backgroundColor: 'rgba(255, 99, 132, 0.5)',
                borderColor: 'rgba(255, 99, 132, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y',
            plugins: {
                title: {
                    display: true,
                    text: `Subject Performance for: ${formattedName}`,
                    font: { size: 16, weight: 'bold' }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return `${context.dataset.label}: ${context.parsed.x.toFixed(1)}%`;
                        }
                    }
                }
            },
            scales: {
                x: {
                    beginAtZero: true,
                    max: 100,
                    title: { display: true, text: 'Score (%)' }
                }
            }
        }
    });
}

let feeData = null;

// Load fee statement when student dashboard is shown
function loadFeeStatement() {
    const user = JSON.parse(sessionStorage.getItem('user'));
    if (!user || user.role !== 'student') return;
    
    const admissionNo = user.id; // Admission number
    
    // Set generation date
    document.getElementById('generation-date').textContent = new Date().toLocaleDateString();
    
    fetch(`slms.php?action=student_payments&admission_no=${admissionNo}`)
        .then(response => response.json())
        .then(data => {
            if (data.ok) {
                feeData = data;
                
                // Update student details
                document.getElementById('student-name').textContent = data.student.name || 'N/A';
                document.getElementById('student-admission').textContent = data.student.admissionNo || 'N/A';
                document.getElementById('student-class').textContent = data.student.class || 'N/A';
                
                // Update term dropdown with dynamic terms from database
                const termSelect = document.getElementById('term-select');
                if (termSelect) {
                    termSelect.innerHTML = ''; // Clear existing options
                    
                    const terms = Object.keys(data.termly_fees || {});
                    
                    if (terms.length === 0) {
                        const option = document.createElement('option');
                        option.value = '';
                        option.textContent = 'No terms available';
                        termSelect.appendChild(option);
                        termSelect.disabled = true;
                    } else {
                        termSelect.disabled = false;
                        
                        terms.forEach(term => {
                            const option = document.createElement('option');
                            option.value = term;
                            option.textContent = term;
                            termSelect.appendChild(option);
                        });
                        
                        // Select first term by default
                        const defaultTerm = terms[0];
                        termSelect.value = defaultTerm;
                        displayFeeStatement(defaultTerm);
                        updateTermOverview(defaultTerm);
                    }
                }
            } else {
                document.getElementById('no-payments-message').style.display = 'block';
            }
        })
        .catch(() => {
            document.getElementById('no-payments-message').style.display = 'block';
        });
}

// Display fee statement for selected term
function displayFeeStatement(term) {
    const tableBody = document.querySelector('#fee-statement-table tbody');
    tableBody.innerHTML = '';
    
    if (!feeData || !feeData.payments) {
        document.getElementById('no-payments-message').style.display = 'block';
        return;
    }
    
    const termPayments = feeData.payments.filter(payment => 
        String(payment.term).trim().toLowerCase() === String(term).trim().toLowerCase()
    );

    if (termPayments.length === 0 && (!feeData.carry_forward || !feeData.carry_forward[term])) {
        document.getElementById('no-payments-message').style.display = 'block';
        document.getElementById('no-payments-message').textContent = 'No payment records found for this term.';
        return;
    } else {
        document.getElementById('no-payments-message').style.display = 'none';
    }
    
    // Term fee
    const termFee = Number(feeData.termly_fees?.[term] || 0);

    // Carry forward from previous term (positive = credit, negative = arrears)
    const carryForward = Number(feeData.carry_forward?.[term] || 0);

    // Calculate initial balance including carry forward
    let runningBalance = termFee + carryForward;

    // Opening balance row
    const openingRow = document.createElement('tr');
    openingRow.innerHTML = `
        <td colspan="3"><strong>Opening Balance (incl. Carry Forward)</strong></td>
        <td><strong>KES ${runningBalance.toFixed(2)}</strong></td>
    `;
    tableBody.appendChild(openingRow);

    // If there is carry forward, note it separately
    if (carryForward !== 0) {
        const cfRow = document.createElement('tr');
        cfRow.innerHTML = `
            <td colspan="4" style="color:${carryForward > 0 ? 'green' : 'red'};">
                <em>${carryForward > 0 ? 'Credit' : 'Arrears'} carried forward from previous term: KES ${Math.abs(carryForward).toFixed(2)}</em>
            </td>
        `;
        tableBody.appendChild(cfRow);
    }

    // Add term fee row
    const feeRow = document.createElement('tr');
    feeRow.innerHTML = `
        <td>-</td>
        <td>Term Fee</td>
        <td>KES ${termFee.toFixed(2)}</td>
        <td>KES ${runningBalance.toFixed(2)}</td>
    `;
    tableBody.appendChild(feeRow);

    // Process payments
    termPayments.forEach(payment => {
        const amount = parseFloat(payment.amount) || 0;
        runningBalance -= amount;

        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${payment.receipt_number || 'N/A'}</td>
            <td>${formatDate(payment.transaction_date)}</td>
            <td>KES ${amount.toFixed(2)}</td>
            <td>KES ${runningBalance.toFixed(2)}</td>
        `;
        tableBody.appendChild(row);
    });
    
    // Update overview + label
    updateTermOverview(term);
    const totalFeeLabel = document.getElementById('total-fee-label');
    if (totalFeeLabel) {
        totalFeeLabel.textContent = `Total Fee for ${term}`;
    }
}

// Update term overview information
function updateTermOverview(term) {
    if (!feeData) return;
    
    const totalFee = Number(feeData.termly_fees?.[term] || 0);
    const amountPaid = Number(feeData.term_totals?.[term] || 0);
    const carryForward = Number(feeData.carry_forward?.[term] || 0);
    
    // Balance after applying carry forward and payments
    const balance = totalFee + carryForward - amountPaid;
    
    document.getElementById('selected-term').textContent = term;
    document.getElementById('total-fee').textContent = `KES ${totalFee.toFixed(2)}`;
    document.getElementById('amount-paid').textContent = `KES ${amountPaid.toFixed(2)}`;
    document.getElementById('balance-remaining').textContent = `KES ${balance.toFixed(2)}`;
    
    const balanceElement = document.getElementById('balance-remaining');
    balanceElement.style.color = balance > 0 ? '#dc3545' : '#28a745';
    
    // Display carry forward information if applicable
    const carryForwardInfo = document.getElementById('carry-forward-info');
    if (carryForwardInfo) {
        if (carryForward !== 0) {
            carryForwardInfo.textContent = carryForward > 0 
                ? `Credit carried forward: KES ${carryForward.toFixed(2)}`
                : `Arrears carried forward: KES ${Math.abs(carryForward).toFixed(2)}`;
            carryForwardInfo.style.color = carryForward > 0 ? '#28a745' : '#dc3545';
            carryForwardInfo.style.display = 'block';
        } else {
            carryForwardInfo.style.display = 'none';
        }
    }
}

// Helper: format date
function formatDate(dateString) {
    const options = { year: 'numeric', month: 'short', day: 'numeric' };
    return new Date(dateString).toLocaleDateString(undefined, options);
}

// Setup fee statement event listeners
function setupFeeStatement() {
    const generationDateEl = document.getElementById('generation-date');
    if (generationDateEl) {
        generationDateEl.textContent = new Date().toLocaleDateString();
    }
    
    const termSelect = document.getElementById('term-select');
    if (termSelect) {
        termSelect.addEventListener('change', function() {
            const selectedTerm = this.value;
            if (feeData) {
                displayFeeStatement(selectedTerm);
                updateTermOverview(selectedTerm);
            }
        });
    }
    
    const printStatementBtn = document.getElementById('print-statement-btn');
    if (printStatementBtn) {
        printStatementBtn.addEventListener('click', () => window.print());
    }
}

// Update showDashboard function to include fee statement loading
function showDashboard(userData) {
  const studentDash = document.getElementById('student_dashboard');
  const adminDash = document.getElementById('admin_dashboard');

  if (studentDash) studentDash.style.display = 'none';
  if (adminDash) adminDash.style.display = 'none';

  if (userData.role === 'student' && studentDash) {
    studentDash.style.display = 'block';
    const nameEl = document.getElementById('studentName');
    const classEl = document.getElementById('studentClass');
    const streamEl = document.getElementById('studentStream');
    if (nameEl) nameEl.textContent = userData.name;
    if (classEl) classEl.textContent = userData.class;
    if (streamEl) streamEl.textContent = userData.stream;
    
    // Load student materials after displaying the dashboard
    loadStudentMaterials();
    
    // Load fee statement
    loadFeeStatement();

    // Load both student exam charts and class performance chart after a delay
    setTimeout(() => {
      loadStudentCharts();
      loadClassPerformanceChart();
    }, 200);
  } else if (userData.role === 'admin' && adminDash) {
    adminDash.style.display = 'block';
    const nameEl = document.getElementById('adminName');
    const roleEl = document.getElementById('adminRole');
    if (nameEl) nameEl.textContent = userData.name;
    if (roleEl) roleEl.textContent = 'Admin';
    loadAdminClassPerformanceCharts();
  }
}
// ✅ Adds Dashboard link to navbar after login
function addDashboardLink(userData) {
  const navList = document.querySelector('nav ul');
  if (!navList) return;

  // Remove if already exists
  const existing = document.getElementById('dashboard-nav-link');
  if (existing) existing.parentElement.remove();

  // Create new dashboard link
  const dashboardLi = document.createElement('li');
  dashboardLi.innerHTML = `
    <a id="dashboard-nav-link" href="#${userData.role}_dashboard">
      <i class="fas fa-tachometer-alt"></i> Dashboard
    </a>
  `;
  navList.appendChild(dashboardLi);

  // Add click handler to show dashboard
  const link = dashboardLi.querySelector('a');
  link.addEventListener('click', e => {
    e.preventDefault();
    hideMainContent(); // Hide public sections
    showDashboard(userData); // Show respective dashboard
    const dash = document.getElementById(`${userData.role}_dashboard`);
    if (dash) dash.scrollIntoView({ behavior: 'smooth', block: 'start' });
  });
}

// ✅ Updates navbar: hide login, show dashboard + logout (or reverse)
function updateNavbarOnLogin(isLoggedIn, userData = null) {
  const loginLink = document.querySelector('nav li a[href="#login"]')?.parentElement;

  if (isLoggedIn && userData) {
    // Hide login
    if (loginLink) loginLink.style.display = 'none';

    // Add dashboard link
    addDashboardLink(userData);

    // Add logout button (if not exists)
    if (isLoggedIn && userData) {
    // ... other code ...

    // Add logout button (if not exists)
    if (!document.getElementById('logout-btn')) {
      const logoutBtn = document.createElement('li');
      logoutBtn.id = 'logout-btn';
      logoutBtn.innerHTML = `
        <a href="#" onclick="event.preventDefault(); logout();">
          <i class="fas fa-sign-out-alt"></i> Logout
        </a>
      `;
      document.querySelector('nav ul')?.appendChild(logoutBtn);
    }
  }
  } else {
    // Show login
    if (loginLink) loginLink.style.display = 'block';

    // Remove dashboard link
    const dashLink = document.getElementById('dashboard-nav-link')?.parentElement;
    if (dashLink) dashLink.remove();

    // Remove logout button
    const logoutBtn = document.getElementById('logout-btn');
    if (logoutBtn) logoutBtn.remove();
  }
}

// ✅ Handles navigation for Home/Location when user is logged in
function handleNavToPublicSection(sectionId) {
  const userData = sessionStorage.getItem('user');
  if (userData) {
    // Hide dashboard, show main content
    const studentDash = document.getElementById('student_dashboard');
    const adminDash = document.getElementById('admin_dashboard');
    if (studentDash) studentDash.style.display = 'none';
    if (adminDash) adminDash.style.display = 'none';

    // Show main sections
    document.querySelectorAll('header, #mission, #vission, #location, #administration, #infrastructure, #cor_curriculum, #religious, #public-charts')
      .forEach(el => {
        if (el) el.style.display = 'block';
      });

    // Scroll to target
    const target = document.querySelector(sectionId);
    if (target) {
      window.scrollTo({
        top: target.offsetTop - 70,
        behavior: 'smooth'
      });
    }
  }
} 
// Function to load and display class performance chart
function loadClassPerformanceChart() {
    const userData = JSON.parse(sessionStorage.getItem('user'));
    
    // Only proceed if user is a student
    if (!userData || userData.role !== 'student') {
        return;
    }

    const studentId = userData.id;
    const canvas = document.getElementById('studentClassChart');
    
    // If canvas doesn't exist, exit
    if (!canvas) {
        console.error('Canvas element not found');
        return;
    }

    // Show loading state
    canvas.style.background = '#f9f9f9';
    canvas.style.display = 'block';
    
    // Create and show loading message
    const loadingMsg = document.createElement('div');
    loadingMsg.id = 'chartLoadingMsg';
    loadingMsg.textContent = 'Loading class performance data...';
    loadingMsg.style.textAlign = 'center';
    loadingMsg.style.padding = '20px';
    loadingMsg.style.color = '#666';
    
    canvas.parentNode.insertBefore(loadingMsg, canvas);
    canvas.style.display = 'none';

    // Prepare data for the request
    const data = new URLSearchParams({
        action: 'fetch_class_perf',
        student_id: studentId
    });

    // Fetch class performance data
    fetch('slms.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: data
    })
    .then(response => response.json())
    .then(result => {
        // Remove loading message
        const loadingMsg = document.getElementById('chartLoadingMsg');
        if (loadingMsg) loadingMsg.remove();
        
        canvas.style.display = 'block';
        
        if (result.ok) {
            renderClassChart(result, canvas);
        } else {
            showChartError(canvas, result.msg || 'Failed to load class performance data');
        }
    })
    .catch(error => {
        console.error('Error fetching class performance:', error);
        const loadingMsg = document.getElementById('chartLoadingMsg');
        if (loadingMsg) loadingMsg.remove();
        
        canvas.style.display = 'block';
        showChartError(canvas, 'Network error. Please try again.');
    });
}

// Function to render the class performance chart
function renderClassChart(data, canvas) {
    const ctx = canvas.getContext('2d');
    
    // Destroy previous chart instance if it exists
    if (canvas.chartInstance) {
        canvas.chartInstance.destroy();
    }
    
    // Prepare chart data
    // Prepare chart data
const backgroundColors = {
    '4Y': 'rgba(255, 205, 86, 0.7)',
    '4B': 'rgba(54, 162, 235, 0.7)',
    '4R': 'rgba(255, 99, 132, 0.7)',
    '4G': 'rgba(75, 192, 192, 0.7)',
    '4P': 'rgba(153, 102, 255, 0.7)',
    '4O': 'rgba(255, 159, 64, 0.7)',
    '3Y': 'rgba(255, 205, 86, 0.7)',
    '3B': 'rgba(54, 162, 235, 0.7)',
    '3R': 'rgba(255, 99, 132, 0.7)',
    '3G': 'rgba(75, 192, 192, 0.7)',
    '3P': 'rgba(153, 102, 255, 0.7)',
    '3O': 'rgba(255, 159, 64, 0.7)',
    '2Y': 'rgba(255, 205, 86, 0.7)',
    '2B': 'rgba(54, 162, 235, 0.7)',
    '2R': 'rgba(255, 99, 132, 0.7)',
    '2G': 'rgba(75, 192, 192, 0.7)',
    '2P': 'rgba(153, 102, 255, 0.7)',
    '2O': 'rgba(255, 159, 64, 0.7)',
    '1Y': 'rgba(255, 205, 86, 0.7)',
    '1B': 'rgba(54, 162, 235, 0.7)',
    '1R': 'rgba(255, 99, 132, 0.7)',
    '1G': 'rgba(75, 192, 192, 0.7)',
    '1P': 'rgba(153, 102, 255, 0.7)',
    '1O': 'rgba(255, 159, 64, 0.7)'
};

const borderColors = Object.fromEntries(
    Object.entries(backgroundColors).map(([k, v]) => [k, v.replace('0.7', '1')])
);

const bgColors = data.streams.map(s => backgroundColors[s] || 'rgba(75, 192, 192, 0.6)');
const bdColors = data.streams.map(s => borderColors[s] || 'rgba(75, 192, 192, 1)');

const chartData = {
    labels: data.streams,
    datasets: [{
        label: `Average Performance (${data.exam_name} ${data.term})`,
        data: data.averages,
        backgroundColor: bgColors,
        borderColor: bdColors,
        borderWidth: 1
    }]
};

    
    // Create chart
    canvas.chartInstance = new Chart(ctx, {
        type: 'bar',
        data: chartData,
              options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        title: {
            display: true,
            text: `Class ${data.class} Performance - ${data.exam_name} ${data.term}`,
            font: { size: 16, weight: 'bold' }
        },
        tooltip: {
            callbacks: {
                label: function(context) {
                    return `Mean Points: ${context.formattedValue}`;
                }
            }
        }
    },
    onClick: (evt, elements) => {
        if (elements.length > 0) {
            const idx = elements[0].index;
            console.log("Clicked stream:", data.streams[idx], "Mean Points:", data.averages[idx]);
        }
    },
    scales: {
    y: {
        beginAtZero: true,
        max: 12,
        title: { display: true, text: 'Mean Points' },
        ticks: {
            font: { size: 14, weight: 'bold' },  // bigger & bold
            color: '#000'                        // dark color for visibility
        }
    },
    x: {
        title: { display: true, text: 'Streams' },
        ticks: {
            font: { size: 14, weight: 'bold' },  // bigger & bold
            color: '#000',
            maxRotation: 45,   // slanted labels
            minRotation: 45
        }
    }
}

}


    });
}

// Function to show error message on chart canvas
function showChartError(canvas, message) {
    const ctx = canvas.getContext('2d');
    
    // Clear canvas
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    
    // Set background
    ctx.fillStyle = '#f9f9f9';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    
    // Display error message
    ctx.font = '16px Arial';
    ctx.fillStyle = '#ff0000';
    ctx.textAlign = 'center';
    ctx.fillText(message, canvas.width / 2, canvas.height / 2);
}
// admin chart display
function loadAdminClassPerformanceCharts() {
    const forms = ["Form1", "Form2", "Form3", "Form4"];

    forms.forEach(form => {
        const canvas = document.getElementById(form.toLowerCase() + "Chart");

        if (!canvas) return;

        // Show loading text
        showChartLoading(canvas, `Loading ${form} performance...`);

        const data = new URLSearchParams({
            action: 'fetch_class_perf_admin',
            class: form
        });

        fetch('slms.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: data
        })
        .then(res => res.json())
        .then(result => {
            hideChartLoading(canvas);

            if (result.ok) {
                renderClassChart(result, canvas);
            } else {
                showChartError(canvas, result.msg || `Failed to load ${form} data`);
            }
        })
        .catch(err => {
            console.error("Error fetching class performance:", err);
            hideChartLoading(canvas);
            showChartError(canvas, "Network error. Please try again.");
        });
    });
}

// Helper to show loading message
function showChartLoading(canvas, msg) {
    canvas.style.display = "none";
    let loading = document.createElement("div");
    loading.className = "chart-loading";
    loading.innerText = msg;
    loading.style.textAlign = "center";
    loading.style.color = "#666";
    loading.style.padding = "20px";
    loading.id = canvas.id + "_loading";
    canvas.parentNode.insertBefore(loading, canvas);
}

function hideChartLoading(canvas) {
    let loading = document.getElementById(canvas.id + "_loading");
    if (loading) loading.remove();
    canvas.style.display = "block";
} 
// Load payments for the selected class
let adminPaymentsData = null;

function loadAdminPayments(className = '') {
    const tableBody = document.querySelector('#adminPayments tbody');
    tableBody.innerHTML = '';
    const tableHead = document.querySelector('#adminPayments thead');
    tableHead.innerHTML = '';

    fetch(`slms.php?action=admin_payments_overview&class=${className}`)
        .then(res => res.json())
        .then(data => {
            if (!data.ok || !data.data || data.data.length === 0) {
                const msgEl = document.getElementById('no-payments-message');
                if (msgEl) {
                    msgEl.style.display = 'block';
                    msgEl.textContent = data.msg || 'No data';
                }
                return;
            }

            const msgEl = document.getElementById('no-payments-message');
            if (msgEl) msgEl.style.display = 'none';

            adminPaymentsData = data;
            displayAdminPayments(document.getElementById('PAtermsort').value);
        })
        .catch(() => {
            const msgEl = document.getElementById('no-payments-message');
            if (msgEl) {
                msgEl.style.display = 'block';
                msgEl.textContent = 'Error loading data';
            }
        });
}

function displayAdminPayments(selectedTerm = '') {
    if (!adminPaymentsData) return;

    const tableBody = document.querySelector('#adminPayments tbody');
    tableBody.innerHTML = '';
    const tableHead = document.querySelector('#adminPayments thead');
    tableHead.innerHTML = '';

    const termOrder = adminPaymentsData.term_order;
    const termsToDisplay = selectedTerm ? [selectedTerm] : termOrder;

    // Table headers: Student | Term Ledger
    const headerRow = document.createElement('tr');
    headerRow.innerHTML = `<th>Student</th><th>Term Ledger</th>`;
    tableHead.appendChild(headerRow);

    adminPaymentsData.data.forEach(student => {
        const mainRow = document.createElement('tr');
        const tdStudent = document.createElement('td');
        tdStudent.textContent = `${student.student} (${student.admission_no})`;
        mainRow.appendChild(tdStudent);

        const tdLedger = document.createElement('td');

        let previousBalance = 0;
        termsToDisplay.forEach(term => {
            const termFee = adminPaymentsData.term_fees[term] ?? 0;
            const paid = student.term_paid[term] ?? 0;
            const balance = student.term_balances[term] ?? 0;

            // Mini ledger HTML for this term without receipt
            const ledgerHTML = `
                <div class="term-ledger" style="margin-bottom:15px; border:1px solid #ccc; padding:10px; border-radius:5px;">
                    <strong>${term}</strong><br>
                    <strong>Total Fee:</strong> KES ${termFee.toFixed(2)}<br>
                    <strong>Amount Paid:</strong> KES ${paid.toFixed(2)}<br>
                    <strong>Balance Remaining:</strong> KES ${balance.toFixed(2)}<br>
                    ${previousBalance !== 0 ? `<em>Carry Forward from previous term: KES ${previousBalance.toFixed(2)}</em><br>` : ''}
                </div>
            `;

            tdLedger.innerHTML += ledgerHTML;
            previousBalance = balance; // carry forward
        });

        mainRow.appendChild(tdLedger);
        tableBody.appendChild(mainRow);
    });
}

// Event listeners
document.getElementById('PAfilter')?.addEventListener('change', function() {
    loadAdminPayments(this.value);
});

document.getElementById('PAtermsort')?.addEventListener('change', function() {
    displayAdminPayments(this.value);
});
// Load default (all classes)
loadAdminPayments();

const { jsPDF } = window.jspdf;

// PDF generation function (only for adminPayments)
function generatePDF(table, title = 'Payment Overview') {
    if (!table || table.id !== "adminPayments") return;

    const loader = document.getElementById("tableLoader");
    loader.style.display = "flex"; // ✅ show overlay on table

    html2canvas(table, { 
        scale: 2,
        ignoreElements: el => el.tagName === 'CANVAS'
    }).then(canvas => {
        const imgData = canvas.toDataURL('image/png');
        const pdf = new jsPDF('p', 'pt', 'a4');
        const pdfWidth = pdf.internal.pageSize.getWidth();
        const pdfHeight = (canvas.height * pdfWidth) / canvas.width;

        pdf.setFontSize(16);
        pdf.text(title, 40, 30);
        pdf.addImage(imgData, 'PNG', 20, 50, pdfWidth - 40, pdfHeight);
        pdf.save(`${title.replace(/\s+/g, '_')}.pdf`);
    }).catch(err => {
        console.error("PDF generation failed:", err);
    }).finally(() => {
        setTimeout(() => {
            loader.style.display = "none"; // ✅ hide overlay after capture
        }, 1000);
    });
}

// ✅ Download ALL students PDF (adminPayments only)
const downloadAllBtn = document.getElementById('downloadAllPDF');
if (downloadAllBtn) {
    downloadAllBtn.addEventListener('click', () => {
        const table = document.getElementById('adminPayments');
        if (table) generatePDF(table, 'All Students Payment Overview');
    });
}
function unlockAndLoadLogs() {
    const password = document.getElementById('logPassword').value.trim();
    if (!password) {
        alert("⚠️ Please enter a password");
        return;
    }

    fetch('slms.php?action=fetch_logs', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'password=' + encodeURIComponent(password)
    })
    .then(response => response.json())
    .then(data => {
        // ❌ Removed: console.log("Logs response:", data); ← Not critical

        const logList = document.getElementById('adminLogs');
        logList.innerHTML = ''; // clear old content

        // ✅ If PHP returned an error object → Keep visible to user + log for dev
        if (data.error) {
            logList.innerHTML = `<li style="color:red;">❌ ${data.error}</li>`;
            console.error("🔐 Password/Auth Error:", data.error); // ← ✅ CRITICAL: Log auth failures
            return;
        }

        // ✅ Ensure it's an array before looping
        if (!Array.isArray(data) || data.length === 0) {
            logList.innerHTML = '<li>No recent activity found.</li>';
            return;
        }

        // ✅ Safe to loop
        data.forEach(log => {
            const li = document.createElement('li');
            li.innerHTML = `
                <strong>[${log.changed_at}]</strong> 
                <span style="color:#2c3e50;">${log.action_type}</span> 
                on <b>${log.table_name}</b> (ID(s): ${log.record_id}) 
                <br>
                <small>New: ${log.new_data || '—'} | Count: ${log.count}</small>
            `;
            logList.appendChild(li);
        });

        // ✅ Hide unlock form once correct password is entered
        document.getElementById('logUnlock').style.display = 'none';
    })
    .catch(err => {
        console.error("❌ Error fetching logs:", err); // ← ✅ CRITICAL: Always keep network/error logs
        document.getElementById('adminLogs').innerHTML = '<li style="color:red;">⚠️ Failed to load logs.</li>';
    });
}

document.addEventListener("DOMContentLoaded", function () {
  // Initialize main page components
  startAutoSlide('administration', 3000);
  startAutoSlide('infrastructure', 4000);
  startAutoSlide('cor_curriculum', 5000);
  initMap();
  loadYearChart();
  loadSubjectChart();
  setupUploadMaterialForm();
  setupExamUploadForm();
  
  // Setup fee statement functionality
  setupFeeStatement();

  // === NAVIGATION ===
  document.querySelectorAll('nav a').forEach(item => {
    item.addEventListener('click', function(e) {
        e.preventDefault();
        document.querySelectorAll('nav a').forEach(link => {
            link.classList.remove('nav-active');
        });
        this.classList.add('nav-active');

        const targetId = this.getAttribute('href');

        // Handle Home & Location specially if user is logged in
        if (targetId === '#home' || targetId === '#location') {
            handleNavToPublicSection(targetId);
            return;
        }

        // Handle Dashboard link (if exists)
        if (targetId === '#student_dashboard' || targetId === '#admin_dashboard') {
            const userData = JSON.parse(sessionStorage.getItem('user'));
            if (userData) {
                hideMainContent();
                showDashboard(userData);
                const dash = document.getElementById(targetId);
                if (dash) dash.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
            return;
        }

        // Default: scroll to section
        const targetSection = document.querySelector(targetId);
        if (targetSection) {
            window.scrollTo({
                top: targetSection.offsetTop - 70,
                behavior: 'smooth'
            });
        }
    });
});

  // Check if there's an active session
  const saved = sessionStorage.getItem('user');
  if (saved) {
    const userData = JSON.parse(saved);
    const maxAge = 24 * 60 * 60 * 1000; // 24 hours
    
    // Verify session is still valid
    if (Date.now() - userData.timestamp <= maxAge) {
      // Show the appropriate dashboard
      showDashboard(userData);
      
      // If it's a student dashboard, load charts and fee statement
      if (userData.role === 'student') {
        setTimeout(loadStudentCharts, 300);
      }
    }
  }
});