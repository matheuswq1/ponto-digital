# Face Service — Ponto Digital

Microserviço Python de reconhecimento facial usando [face_recognition](https://github.com/ageitgey/face_recognition) (dlib).

## Rodar com Docker (recomendado)

```bash
# Build
docker build -t ponto-face-service .

# Run (persistindo embeddings em volume Docker)
docker run -d \
  --name ponto-face \
  -p 8001:8001 \
  -v ponto_face_data:/data \
  -e FACE_SERVICE_KEY=seu-segredo-aqui \
  -e FACE_THRESHOLD=0.55 \
  ponto-face-service
```

## Rodar localmente (desenvolvimento)

```bash
python -m venv venv
# Windows
venv\Scripts\activate
# Linux/Mac
source venv/bin/activate

pip install -r requirements.txt

DATA_DIR=./data FACE_SERVICE_KEY=dev-key uvicorn main:app --reload --port 8001
```

> No Windows instale CMake e Visual Studio Build Tools antes do `pip install`.

## Variáveis de ambiente

| Variável | Padrão | Descrição |
|---|---|---|
| `FACE_SERVICE_KEY` | `changeme-secret-key` | Chave de API (cabeçalho `X-Face-Service-Key`) |
| `FACE_THRESHOLD` | `0.55` | Distância euclidiana máxima para aprovação (menor = mais restritivo) |
| `DATA_DIR` | `/data` | Diretório onde `embeddings.json` é salvo |

## Endpoints

| Método | Rota | Descrição |
|---|---|---|
| `GET` | `/health` | Liveness check |
| `POST` | `/enroll` | Cadastra/atualiza rosto (`employee_id` + `photo`) |
| `POST` | `/verify` | Verifica rosto (`employee_id` + `photo`) → `{match, score, distance}` |
| `DELETE` | `/enroll/{employee_id}` | Remove embedding |

Documentação interativa: `http://localhost:8001/docs`

## Integração com Laravel

Configure no `.env` do Laravel:

```env
FACE_SERVICE_URL=http://localhost:8001
FACE_SERVICE_KEY=seu-segredo-aqui
```

O `FaceController` do Laravel faz proxy autenticado para este serviço.
