"""
Ponto Digital — Face Service
Microserviço de reconhecimento facial via FastAPI + DeepFace.

Endpoints:
  POST /enroll   — cadastra embedding para um employee_id
  POST /verify   — compara face enviada com o embedding cadastrado (retorna score 0-1)
  DELETE /enroll/{employee_id} — remove embedding
  GET  /health   — liveness

Autenticação: cabeçalho `X-Face-Service-Key` (definido via variável de ambiente FACE_SERVICE_KEY).
Os embeddings são persistidos em JSON simples em /data/embeddings.json.
"""

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

# ---------------------------------------------------------------------------
# Config
# ---------------------------------------------------------------------------
FACE_SERVICE_KEY: str = os.getenv("FACE_SERVICE_KEY", "changeme-secret-key")
DATA_DIR = Path(os.getenv("DATA_DIR", "/data"))
EMBEDDINGS_FILE = DATA_DIR / "embeddings.json"
# Distância coseno: <0.40 = mesma pessoa (modelo Facenet512)
SIMILARITY_THRESHOLD: float = float(os.getenv("FACE_THRESHOLD", "0.40"))
MODEL_NAME = "Facenet512"
# Ordem de tentativa: opencv costuma falhar em selfies; ssd/mtcnn/retinaface são mais robustos
_DEFAULT_DETECTORS = "ssd,mtcnn,retinaface,opencv"
DETECTOR_CHAIN: list[str] = [
    b.strip()
    for b in os.getenv("FACE_DETECTOR_CHAIN", _DEFAULT_DETECTORS).split(",")
    if b.strip()
]


app = FastAPI(title="Ponto Digital — Face Service", version="2.0.0")


# ---------------------------------------------------------------------------
# Storage helpers
# ---------------------------------------------------------------------------
def _load_embeddings() -> dict:
    if not EMBEDDINGS_FILE.exists():
        return {}
    try:
        with EMBEDDINGS_FILE.open("r") as f:
            return json.load(f)
    except Exception:
        return {}


def _save_embeddings(data: dict) -> None:
    EMBEDDINGS_FILE.parent.mkdir(parents=True, exist_ok=True)
    with EMBEDDINGS_FILE.open("w") as f:
        json.dump(data, f)


# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------
def _auth(api_key: Optional[str]) -> None:
    if api_key != FACE_SERVICE_KEY:
        raise HTTPException(status_code=401, detail="Chave de API inválida.")


def _save_upload_to_tmp(file: UploadFile) -> str:
    """Normaliza a imagem (RGB, redimensiona) e grava JPEG para o DeepFace."""
    raw = file.file.read()
    pil_img = Image.open(BytesIO(raw)).convert("RGB")
    # Reduz lado máximo para acelerar e ajudar detectores (fotos 12MP+)
    max_side = int(os.getenv("FACE_MAX_IMAGE_SIDE", "1600"))
    w, h = pil_img.size
    if max(w, h) > max_side:
        scale = max_side / float(max(w, h))
        pil_img = pil_img.resize(
            (int(w * scale), int(h * scale)),
            Image.Resampling.LANCZOS,
        )
    suffix = ".jpg"
    with tempfile.NamedTemporaryFile(delete=False, suffix=suffix) as tmp:
        pil_img.save(tmp.name, "JPEG", quality=92, optimize=True)
        return tmp.name


def _get_embedding(img_path: str) -> list:
    """Extrai embedding com DeepFace, tentando vários detectores (mobile/selfie)."""
    last_detail: str | None = None
    for backend in DETECTOR_CHAIN:
        try:
            result = DeepFace.represent(
                img_path=img_path,
                model_name=MODEL_NAME,
                enforce_detection=True,
                detector_backend=backend,
                align=True,
            )
            if not result:
                last_detail = f"detector={backend}: sem resultado"
                continue
            if len(result) > 1:
                raise HTTPException(
                    status_code=422,
                    detail="Mais de um rosto detectado. Envie apenas um.",
                )
            return result[0]["embedding"]
        except HTTPException:
            raise
        except Exception as e:
            msg = str(e)
            last_detail = f"{backend}: {msg[:200]}"
            if "Face could not be detected" in msg or "Detected face could not be aligned" in msg:
                continue
            if "no face" in msg.lower():
                continue
            # Erro inesperado neste backend — tenta o seguinte
            continue

    # Último recurso: sem forçar detecção (menos seguro, mas evita falha total em fotos difíceis)
    try:
        result = DeepFace.represent(
            img_path=img_path,
            model_name=MODEL_NAME,
            enforce_detection=False,
            detector_backend="opencv",
            align=True,
        )
        if result and len(result) == 1:
            return result[0]["embedding"]
    except Exception:
        pass

    raise HTTPException(
        status_code=422,
        detail="Nenhum rosto detectado na imagem. Use boa luz, olhe para a câmera e aproxime o rosto.",
    )


def _cosine_distance(a: list, b: list) -> float:
    va, vb = np.array(a), np.array(b)
    denom = np.linalg.norm(va) * np.linalg.norm(vb)
    if denom == 0:
        return 1.0
    return float(1.0 - np.dot(va, vb) / denom)


def _distance_to_score(distance: float) -> float:
    """Converte distância coseno para score 0-1 (1 = idêntico)."""
    return max(0.0, min(1.0, 1.0 - distance / 0.6))


# ---------------------------------------------------------------------------
# Routes
# ---------------------------------------------------------------------------
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
        raise HTTPException(
            status_code=404,
            detail="Nenhum rosto cadastrado para este colaborador.",
        )

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
