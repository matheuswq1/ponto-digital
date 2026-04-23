#!/bin/bash
set -e

FACE_KEY="FaceKey@PontoDigital2026"
FACE_DIR="/opt/ponto-face-service"

echo "=== Criando diretório ==="
mkdir -p "$FACE_DIR"

echo "=== Copiando main.py ==="
cat > "$FACE_DIR/main.py" << 'PYEOF'
import json
import os
import tempfile
from io import BytesIO
from pathlib import Path
from typing import Optional

import numpy as np
from deepface import DeepFace
from fastapi import FastAPI, File, Form, Header, HTTPException, UploadFile
from PIL import Image

FACE_SERVICE_KEY = os.getenv("FACE_SERVICE_KEY", "changeme-secret-key")
DATA_DIR = Path(os.getenv("DATA_DIR", "/data"))
EMBEDDINGS_FILE = DATA_DIR / "embeddings.json"
SIMILARITY_THRESHOLD = float(os.getenv("FACE_THRESHOLD", "0.40"))
MODEL_NAME = "Facenet512"

app = FastAPI(title="Ponto Digital Face Service", version="2.0.0")

def _load_embeddings():
    if not EMBEDDINGS_FILE.exists():
        return {}
    try:
        with EMBEDDINGS_FILE.open("r") as f:
            return json.load(f)
    except Exception:
        return {}

def _save_embeddings(data):
    EMBEDDINGS_FILE.parent.mkdir(parents=True, exist_ok=True)
    with EMBEDDINGS_FILE.open("w") as f:
        json.dump(data, f)

def _auth(api_key):
    if api_key != FACE_SERVICE_KEY:
        raise HTTPException(status_code=401, detail="Chave de API inválida.")

def _save_upload_to_tmp(file):
    raw = file.file.read()
    pil_img = Image.open(BytesIO(raw)).convert("RGB")
    with tempfile.NamedTemporaryFile(delete=False, suffix=".jpg") as tmp:
        pil_img.save(tmp.name, "JPEG")
        return tmp.name

def _get_embedding(img_path):
    try:
        result = DeepFace.represent(
            img_path=img_path,
            model_name=MODEL_NAME,
            enforce_detection=True,
            detector_backend="opencv",
        )
        if not result:
            raise HTTPException(status_code=422, detail="Nenhum rosto detectado na imagem.")
        if len(result) > 1:
            raise HTTPException(status_code=422, detail="Mais de um rosto detectado.")
        return result[0]["embedding"]
    except HTTPException:
        raise
    except Exception as e:
        msg = str(e)
        if "Face could not be detected" in msg or "no face" in msg.lower():
            raise HTTPException(status_code=422, detail="Nenhum rosto detectado na imagem.")
        raise HTTPException(status_code=422, detail=f"Erro ao processar: {msg}")

def _cosine_distance(a, b):
    va, vb = np.array(a), np.array(b)
    denom = np.linalg.norm(va) * np.linalg.norm(vb)
    if denom == 0:
        return 1.0
    return float(1.0 - np.dot(va, vb) / denom)

def _distance_to_score(distance):
    return max(0.0, min(1.0, 1.0 - distance / 0.6))

@app.get("/health")
def health():
    return {"status": "ok", "model": MODEL_NAME}

@app.post("/enroll")
async def enroll(
    employee_id: str = Form(...),
    photo: UploadFile = File(...),
    x_face_service_key: Optional[str] = Header(None),
):
    _auth(x_face_service_key)
    tmp_path = _save_upload_to_tmp(photo)
    try:
        embedding = _get_embedding(tmp_path)
    finally:
        if os.path.exists(tmp_path):
            os.unlink(tmp_path)
    store = _load_embeddings()
    store[str(employee_id)] = embedding
    _save_embeddings(store)
    return {"message": "Rosto cadastrado com sucesso.", "employee_id": employee_id}

@app.post("/verify")
async def verify(
    employee_id: str = Form(...),
    photo: UploadFile = File(...),
    x_face_service_key: Optional[str] = Header(None),
):
    _auth(x_face_service_key)
    store = _load_embeddings()
    stored = store.get(str(employee_id))
    if stored is None:
        raise HTTPException(status_code=404, detail="Nenhum rosto cadastrado para este colaborador.")
    tmp_path = _save_upload_to_tmp(photo)
    try:
        live_embedding = _get_embedding(tmp_path)
    finally:
        if os.path.exists(tmp_path):
            os.unlink(tmp_path)
    distance = _cosine_distance(stored, live_embedding)
    score = _distance_to_score(distance)
    match = distance <= SIMILARITY_THRESHOLD
    return {
        "match": match,
        "score": round(score, 4),
        "distance": round(distance, 4),
        "threshold": SIMILARITY_THRESHOLD,
        "employee_id": employee_id,
    }

@app.delete("/enroll/{employee_id}")
def delete_enroll(
    employee_id: str,
    x_face_service_key: Optional[str] = Header(None),
):
    _auth(x_face_service_key)
    store = _load_embeddings()
    if str(employee_id) not in store:
        raise HTTPException(status_code=404, detail="Embedding não encontrado.")
    del store[str(employee_id)]
    _save_embeddings(store)
    return {"message": "Embedding removido.", "employee_id": employee_id}
PYEOF

echo "=== Copiando Dockerfile ==="
cat > "$FACE_DIR/Dockerfile" << 'DFEOF'
FROM python:3.11-slim

RUN apt-get update && apt-get install -y --no-install-recommends \
    libglib2.0-0 \
    libsm6 \
    libxext6 \
    libxrender1 \
    libgl1-mesa-glx \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

RUN pip install --no-cache-dir \
    fastapi==0.115.12 \
    "uvicorn[standard]==0.34.0" \
    python-multipart==0.0.20 \
    numpy \
    Pillow \
    deepface==0.0.93 \
    tf-keras

COPY main.py .

VOLUME /data

EXPOSE 8001

CMD ["uvicorn", "main:app", "--host", "0.0.0.0", "--port", "8001"]
DFEOF

echo "=== Build Docker (DeepFace - pode demorar ~5-8 min) ==="
cd "$FACE_DIR"
docker build -t ponto-face-service . 2>&1 | tail -5

echo "=== Parando container antigo ==="
docker stop ponto-face 2>/dev/null || true
docker rm ponto-face 2>/dev/null || true

echo "=== Iniciando container ==="
docker run -d \
  --name ponto-face \
  --restart unless-stopped \
  -p 127.0.0.1:8001:8001 \
  -v ponto_face_data:/data \
  -e FACE_SERVICE_KEY="$FACE_KEY" \
  ponto-face-service

sleep 8

echo "=== Teste de saúde ==="
curl -s http://127.0.0.1:8001/health || echo "AGUARDANDO..."

echo "=== Configurando .env Laravel ==="
cd /home/ponto/htdocs/ponto.approsamistica.com
sed -i '/FACE_SERVICE_URL/d' .env
sed -i '/FACE_SERVICE_KEY/d' .env
printf "\nFACE_SERVICE_URL=http://127.0.0.1:8001\nFACE_SERVICE_KEY=%s\n" "$FACE_KEY" >> .env
sudo -u ponto php artisan config:clear

echo "=== CONCLUIDO ==="
docker ps | grep ponto-face
