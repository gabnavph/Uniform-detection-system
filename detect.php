<!-- detect.php -->
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Uniform Detection System</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <style>
    body { background: #f8f9fa; }
    .video-card { text-align: center; }
    img.stream, canvas { width: 100%; max-width: 640px; border-radius: 10px; display:block; }

    .log-container {
      background: #111;
      color: #0f0;
      font-family: monospace;
      padding: 10px;
      border-radius: 10px;
      height: 250px;
      overflow-y: auto;
      white-space: pre-wrap;
    }

    /* Detection labels */
    .detection-label {
      display: inline-block;
      margin: 5px 10px 5px 0;
      padding: 5px 10px;
      border-radius: 5px;
      background: #ddd;
      cursor: pointer;
      user-select: none;
      transition: 0.2s;
    }
  </style>
</head>
<body>
<div class="container py-4">
  <div class="row g-4">

    <!-- Webcam & Bounding Boxes -->
    <div class="col-md-8">
      <div class="card video-card shadow-sm">
        <div class="card-header bg-primary text-white">
          <h5 class="m-0">Webcam – Live Detection</h5>
        </div>
        <div class="card-body">
          <img id="videoFeed" class="stream" src="http://localhost:5000/video_feed" alt="Live stream">
          <canvas id="overlay"></canvas>
        </div>
      </div>
    </div>

    <!-- Detection Labels + Reset Button + Live Log -->
    <div class="col-md-4">
      <div class="card shadow-sm mb-3">
        <div class="card-header bg-secondary text-white">
          <h5 class="m-0">Detected Objects</h5>
        </div>
        <div class="card-body">
          <div id="detections">
            <span id="cls_0" class="detection-label">ID</span>
            <span id="cls_1" class="detection-label">Female Dress</span>
            <span id="cls_2" class="detection-label">Female Skirt</span>
            <span id="cls_3" class="detection-label">Male Dress</span>
            <span id="cls_4" class="detection-label">Male Pants</span>
            <span id="cls_5" class="detection-label">Shoes</span>
          </div>
          <div class="mt-2">
            <button id="resetLabelsBtn" class="btn btn-warning btn-sm">Reset Labels</button>
          </div>
        </div>
      </div>

      <div class="card shadow-sm">
        <div class="card-header bg-dark text-white">
          <h5 class="m-0">📜 Live Log Feed</h5>
        </div>
        <div class="card-body">
          <div id="logBox" class="log-container">Waiting for logs...</div>
        </div>
      </div>
    </div>

  </div>
</div>

<script>
const CLASS_MAP = {
  0: "ID",
  1: "female_dress",
  2: "female_skirt",
  3: "male_dress",
  4: "male_pants",
  5: "shoes"
};

const permanentDetected = {}; // keeps track of labels that should stay green

// Canvas overlay
const videoImg = document.getElementById('videoFeed');
const canvas = document.getElementById('overlay');
const ctx = canvas.getContext ? canvas.getContext('2d') : null;
videoImg.onload = () => { canvas.width = videoImg.width; canvas.height = videoImg.height; };

// Fetch detections from server
async function fetchDetections() {
  try {
    const res = await fetch('http://localhost:5000/detections', { cache: "no-store" });
    const data = await res.json();

    // Keep labels green permanently when detected
    if (data.detected_ids && data.detected_ids.length) {
      data.detected_ids.forEach(id => {
        id = Number(id);
        permanentDetected[id] = true;
      });
    }

    // Update the label colors
    Object.keys(CLASS_MAP).forEach(idStr => {
      const id = Number(idStr);
      const el = document.getElementById('cls_' + id);
      if (!el) return;

      if (permanentDetected[id]) {
        el.style.background = "#28a745"; // green
        el.style.color = "#fff";
        el.style.fontWeight = "bold";
      } else {
        el.style.background = "#ddd"; // default gray
        el.style.color = "#000";
        el.style.fontWeight = "normal";
      }
    });

    // Draw bounding boxes on canvas
    // if (ctx && data.detected) {
    //   ctx.clearRect(0, 0, canvas.width, canvas.height);
    //   data.detected.forEach(obj => {
    //     const x1 = obj.x1, y1 = obj.y1, x2 = obj.x2, y2 = obj.y2;
    //     ctx.strokeStyle = "#00ff00";
    //     ctx.lineWidth = 2;
    //     ctx.strokeRect(x1, y1, x2 - x1, y2 - y1);
    //     ctx.font = "14px Arial";
    //     ctx.fillStyle = "#00ff00";
    //     ctx.fillText(`${obj.class_name} (${(obj.conf*100).toFixed(1)}%)`, x1, Math.max(12, y1-6));
    //   });
    // }

    updateLogs();
  } catch (err) {
    console.error("Error fetching detections:", err);
  }
}

// Reset labels
document.getElementById('resetLabelsBtn').addEventListener('click', () => {
  Object.keys(CLASS_MAP).forEach(idStr => {
    const id = Number(idStr);
    const el = document.getElementById('cls_' + id);
    if (!el) return;

    el.style.background = "#ddd";
    el.style.color = "#000";
    el.style.fontWeight = "normal";
    delete permanentDetected[id];
  });
  addToLog("All labels reset.");
});

// Update logs
async function updateLogs() {
  try {
    const res = await fetch('read_log.php', { cache: "no-store" });
    const txt = await res.text();
    const logBox = document.getElementById('logBox');
    logBox.innerText = txt;
    logBox.scrollTop = logBox.scrollHeight;
  } catch (err) {
    console.error("Failed to fetch logs:", err);
  }
}

// Append frontend log
function addToLog(msg) {
  const logBox = document.getElementById('logBox');
  logBox.innerText += `[${new Date().toLocaleTimeString()}] ${msg}\n`;
  logBox.scrollTop = logBox.scrollHeight;
}

// Start polling
setInterval(fetchDetections, 800);
setInterval(updateLogs, 2000);
fetchDetections();
updateLogs();
</script>
</body>
</html>
