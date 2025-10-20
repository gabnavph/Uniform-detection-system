# detection_server.py
import os
import time
from datetime import datetime
from threading import Lock

import cv2
from flask import Flask, Response, jsonify
from ultralytics import YOLO
from flask_cors import CORS  # ✅ ADDED

# ------------
# CONFIG
# ------------
MODEL_PATH = "best2.pt"     # <-- put your model here
LOG_DIR = "logs"
os.makedirs(LOG_DIR, exist_ok=True)

# class names (match your data.yaml / training)
CLASS_NAMES = {
    0: "ID",
    1: "female_dress",
    2: "female_skirt",
    3: "male_dress",
    4: "male_pants",
    5: "shoes"
}

# ------------
# GLOBALS
# ------------
app = Flask(__name__)
CORS(app)  # ✅ ENABLE CORS HERE

model = YOLO(MODEL_PATH)
latest_detections = []   # list of class ids detected in the last processed frame
latest_boxes = []        # also store boxes for optional use
lock = Lock()

# Detection confidence threshold (can be updated via API)
DETECTION_CONFIDENCE = 0.58

# ------------
# LOGGING
# ------------
def log_detection(msg: str):
    filename = os.path.join(LOG_DIR, datetime.now().strftime("%Y-%m-%d") + ".log")
    ts = datetime.now().strftime("%H:%M:%S")
    with open(filename, "a", encoding="utf-8") as f:
        f.write(f"[{ts}] {msg}\n")

# ------------
# CAMERA INIT
# ------------
def open_camera():
    for backend in (cv2.CAP_DSHOW, cv2.CAP_MSMF):
        cap = cv2.VideoCapture(0, backend)
        time.sleep(0.2)
        if cap.isOpened():
            print(f"Opened camera with backend {backend}")
            return cap
        cap.release()
    raise RuntimeError("Could not open camera with CAP_DSHOW or CAP_MSMF.")

# ------------
# FRAME GENERATOR
# ------------
def generate_frames():
    global latest_detections, latest_boxes

    cap = open_camera()
    try:
        while True:
            ret, frame = cap.read()
            if not ret or frame is None:
                print("Failed to grab frame, retrying...")
                time.sleep(0.1)
                continue

            results = model(frame, conf=DETECTION_CONFIDENCE, verbose=False)  # dynamic confidence

            detected_ids = []
            boxes_for_frame = []

            for res in results:
                for box in res.boxes:
                    # extract coordinates
                    try:
                        xyxy = box.xyxy[0].cpu().numpy()
                    except Exception:
                        xyxy = box.xyxy[0].numpy()
                    x1, y1, x2, y2 = map(int, xyxy.tolist())

                    # confidence and class_id (fix DeprecationWarning)
                    try:
                        conf = float(box.conf.cpu().numpy().item())
                    except Exception:
                        conf = float(box.conf)

                    try:
                        cls_id = int(box.cls.cpu().numpy().item())
                    except Exception:
                        cls_id = int(box.cls)

                    detected_ids.append(cls_id)
                    boxes_for_frame.append({
                        "class_id": cls_id,
                        "class_name": CLASS_NAMES.get(cls_id, str(cls_id)),
                        "conf": conf,
                        "xyxy": [x1, y1, x2, y2]
                    })

                    # draw box and label
                    label = f"{CLASS_NAMES.get(cls_id, str(cls_id))} {conf:.2f}"
                    cv2.rectangle(frame, (x1, y1), (x2, y2), (0, 255, 0), 2)
                    cv2.putText(frame, label, (x1, max(20, y1-8)),
                                cv2.FONT_HERSHEY_SIMPLEX, 0.6, (0, 255, 0), 2)

            # update globals
            with lock:
                latest_detections = sorted(list(set(detected_ids)))
                latest_boxes = boxes_for_frame

            # log detected classes
            for cls in set(detected_ids):
                log_detection(f"Detected: {CLASS_NAMES.get(cls, str(cls))}")

            # encode frame as JPEG
            ret2, buffer = cv2.imencode(".jpg", frame)
            if not ret2:
                continue
            jpg = buffer.tobytes()

            yield (b'--frame\r\n'
                   b'Content-Type: image/jpeg\r\n\r\n' + jpg + b'\r\n')

            time.sleep(0.03)
    finally:
        cap.release()

# ------------
# FLASK ROUTES
# ------------
@app.route("/video_feed")
def video_feed():
    return Response(generate_frames(),
                    mimetype='multipart/x-mixed-replace; boundary=frame')

@app.route("/detections")
def get_detections():
    with lock:
        ids = list(latest_detections)
        boxes = list(latest_boxes)
    payload = {
        "detected_ids": ids,
        "detected": [
            {
                "class_id": b["class_id"],
                "class_name": b["class_name"],
                "conf": b["conf"],
                "x1": b["xyxy"][0],
                "y1": b["xyxy"][1],
                "x2": b["xyxy"][2],
                "y2": b["xyxy"][3]
            } for b in boxes
        ]
    }
    return jsonify(payload)

@app.route("/set_confidence/<float:conf_value>")
def set_confidence(conf_value):
    global DETECTION_CONFIDENCE
    if 0.1 <= conf_value <= 1.0:
        DETECTION_CONFIDENCE = conf_value
        return jsonify({"status": "success", "confidence": conf_value})
    else:
        return jsonify({"status": "error", "message": "Confidence must be between 0.1 and 1.0"}), 400

@app.route("/manual_toggle/<int:class_id>/<action>")
def manual_toggle(class_id, action):
    cname = CLASS_NAMES.get(class_id, str(class_id))
    log_detection(f"Manual {action}: {cname}")
    return jsonify({"status": "ok", "class_id": class_id, "action": action})

# ------------
# RUN SERVER
# ------------
if __name__ == "__main__":
    print("Starting detection server...")
    app.run(host="0.0.0.0", port=5000, threaded=True)
