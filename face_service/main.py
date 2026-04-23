"""
Ponto Digital — Face Service
Microserviço de reconhecimento facial via FastAPI + face_recognition (dlib).

Endpoints:
  POST /enroll   — cadastra embedding para um employee_id
  POST /verify   — compara face enviada com o embedding cadastrado (retorna score 0-1)
  DELETE /enroll/{employee_id} — remove embedding
  GET  /health   — liveness

Autenticação: cabeçalho `X-Face-Service-Key` (definido via variável de ambiente FACE_SERVICE_KEY).
Os embeddings são persistidos em JSON simples em /data/embeddings.json.
Para produção use banco de dados ou Redis.
"""

import json
import os
from io import BytesIO
from pathlib import Path
from typing import Optional

import face_recognition
import numpy as np
from fastapi import FastAPI, File, Form, Header, HTTPException, UploadFile
from fastapi.responses import JSONResponse
from PIL import Image

# ---------------------------------------------------------------------------
# Config
# ---------------------------------------------------------------------------
FACE_SERVICE_KEY: str = os.getenv("FACE_SERVICE_KEY", "changeme-secret-key")
DATA_DIR = Path(os.getenv("DATA_DIR", "/data"))
EMBEDDINGS_FILE = DATA_DIR / "embeddings.json"
SIMILARITY_THRESHOLD: float = float(os.getenv("FACE_THRESHOLD", "0.55"))

app = FastAPI(title="Ponto Digital — Face Service", version="1.0.0")


# ---------------------------------------------------------------------------
# Storage helpers
# ---------------------------------------------------------------------------
def _load_embeddings() -> dict[str, list[float]]:
    if not EMBEDDINGS_FILE.exists():
        return {}
    try:
        with EMBEDDINGS_FILE.open("r") as f:
            return json.load(f)
    except Exception:
        return {}


def _save_embeddings(data: dict[str, list[float]]) -> None:
    EMBEDDINGS_FILE.parent.mkdir(parents=True, exist_ok=True)
    with EMBEDDINGS_FILE.open("w") as f:
        json.dump(data, f)


# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------
def _auth(api_key: Optional[str]) -> None:
    if api_key != FACE_SERVICE_KEY:
        raise HTTPException(status_code=401, detail="Chave de API inválida.")


def _decode_image(file: UploadFile) -> np.ndarray:
    """Lê o upload e devolve um array RGB compatível com face_recognition."""
    raw = file.file.read()
    pil_img = Image.open(BytesIO(raw)).convert("RGB")
    return np.array(pil_img)


def _get_embedding(img: np.ndarray) -> list[float]:
    """Detecta rosto e extrai embedding 128-d. Lança 422 se não encontrar exactamente 1 rosto."""
    locations = face_recognition.face_locations(img, model="hog")
    if len(locations) == 0:
        raise HTTPException(status_code=422, detail="Nenhum rosto detectado na imagem.")
    if len(locations) > 1:
        raise HTTPException(status_code=422, detail="Mais de um rosto detectado. Envie apenas um.")
    encodings = face_recognition.face_encodings(img, known_face_locations=locations)
    return encodings[0].tolist()


def _cosine_similarity(a: list[float], b: list[float]) -> float:
    va, vb = np.array(a), np.array(b)
    denom = (np.linalg.norm(va) * np.linalg.norm(vb))
    if denom == 0:
        return 0.0
    return float(np.dot(va, vb) / denom)


def _face_distance_to_score(distance: float) -> float:
    """Converte distância euclidiana (face_recognition) para score 0-1.
    score ~1 = mesma pessoa; score ~0 = pessoas diferentes.
    Fórmula: score = max(0, 1 - distance / 0.8)
    """
    return max(0.0, 1.0 - distance / 0.8)


# ---------------------------------------------------------------------------
# Routes
# ---------------------------------------------------------------------------
@app.get("/health")
def health():
    return {"status": "ok"}


@app.post("/enroll")
async def enroll(
    employee_id: str = Form(...),
    photo: UploadFile = File(...),
    x_face_service_key: Optional[str] = Header(None),
):
    """
    Cadastra (ou atualiza) o embedding facial de um colaborador.
    Deve ser chamado no **primeiro login** do colaborador no app.

    Body (multipart):
      - employee_id: string — ID do funcionário no banco
      - photo: file — imagem JPEG/PNG com o rosto frontal
    """
    _auth(x_face_service_key)

    img = _decode_image(photo)
    embedding = _get_embedding(img)

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
    """
    Verifica se o rosto da foto corresponde ao rosto cadastrado.

    Retorna:
      - match: bool — aprovado (score >= threshold)
      - score: float (0-1) — similaridade
      - threshold: float — limiar configurado
    """
    _auth(x_face_service_key)

    store = _load_embeddings()
    stored = store.get(str(employee_id))
    if stored is None:
        raise HTTPException(
            status_code=404,
            detail="Nenhum rosto cadastrado para este colaborador. Faça o cadastro primeiro.",
        )

    img = _decode_image(photo)
    live_embedding = _get_embedding(img)

    distance = float(face_recognition.face_distance([np.array(stored)], np.array(live_embedding))[0])
    score = _face_distance_to_score(distance)
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
    """Remove o embedding cadastrado de um colaborador."""
    _auth(x_face_service_key)

    store = _load_embeddings()
    if str(employee_id) not in store:
        raise HTTPException(status_code=404, detail="Embedding não encontrado.")
    del store[str(employee_id)]
    _save_embeddings(store)
    return {"message": "Embedding removido.", "employee_id": employee_id}
