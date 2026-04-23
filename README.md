# Sistema de Ponto Eletrônico Web

Backend em **Laravel 12** para sistema de controle de ponto eletrônico com suporte a app Flutter.

---

## Stack

| Camada | Tecnologia |
|---|---|
| Backend | Laravel 12 + PHP 8.2 |
| Banco | MySQL 8 |
| Autenticação | Laravel Sanctum (tokens) |
| Armazenamento de fotos | Firebase Storage |
| Permissões | Spatie Laravel Permission |
| Filas | Laravel Queue (database driver) |

---

## Instalação

### 1. Pré-requisitos
- PHP 8.2+
- Composer
- MySQL 8+
- XAMPP (ou servidor equivalente)

### 2. Configurar o banco

Crie o banco de dados no MySQL:
```sql
CREATE DATABASE ponto_web CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 3. Configurar o `.env`

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ponto_web
DB_USERNAME=root
DB_PASSWORD=sua_senha

FIREBASE_CREDENTIALS=firebase-credentials.json
FIREBASE_STORAGE_DEFAULT_BUCKET=seu-projeto.appspot.com
```

### 4. Configurar Firebase

1. Acesse o [Firebase Console](https://console.firebase.google.com)
2. Crie um projeto
3. Vá em **Configurações do projeto → Contas de serviço**
4. Clique em **Gerar nova chave privada**
5. Salve como `firebase-credentials.json` na raiz do projeto

### 5. Executar migrations e seeders

```bash
php artisan migrate
php artisan db:seed
```

### 6. Iniciar o servidor de filas (opcional)

```bash
php artisan queue:work
```

---

## Usuários de teste (após seed)

| E-mail | Senha | Perfil |
|---|---|---|
| admin@ponto.com | password | Administrador |
| gestor@ponto.com | password | Gestor RH |
| funcionario@ponto.com | password | Funcionário |

---

## API Endpoints

**Base URL:** `http://localhost/projeto-ponto-web/public/api/v1`

### Autenticação

| Método | Endpoint | Descrição | Auth |
|---|---|---|---|
| POST | `/login` | Login e obtenção do token | Não |
| POST | `/logout` | Logout (revoga token) | Sim |
| GET | `/me` | Dados do usuário autenticado | Sim |
| POST | `/refresh-token` | Renova o token | Sim |

#### Exemplo de Login

```json
POST /api/v1/login
{
  "email": "funcionario@ponto.com",
  "password": "password",
  "device_name": "Flutter App"
}
```

Resposta:
```json
{
  "token": "1|abc123...",
  "token_type": "Bearer",
  "expires_at": "2026-05-22T17:00:00Z",
  "user": { ... }
}
```

---

### Registro de Ponto

| Método | Endpoint | Descrição | Auth |
|---|---|---|---|
| GET | `/time-records` | Listar registros | Sim |
| POST | `/time-records` | Registrar ponto | Sim |
| GET | `/time-records/today` | Registros do dia atual | Sim |
| GET | `/time-records/signed-upload-url` | URL assinada para upload de foto | Sim |
| POST | `/time-records/sync-offline` | Sincronizar registros offline | Sim |
| GET | `/time-records/{id}` | Detalhes de um registro | Sim |
| POST | `/time-records/{id}/edit-request` | Solicitar correção de ponto | Sim |

#### Bater Ponto

```json
POST /api/v1/time-records
Content-Type: multipart/form-data
Authorization: Bearer {token}

{
  "type": "entrada",          // entrada | saida_almoco | volta_almoco | saida
  "latitude": -23.5505,
  "longitude": -46.6333,
  "accuracy": 12.5,
  "photo": <arquivo>,         // ou photo_url se já enviou ao Firebase
  "device_id": "uuid-do-dispositivo",
  "is_mock_location": false,
  "offline": false
}
```

#### Sincronizar Offline

```json
POST /api/v1/time-records/sync-offline
{
  "records": [
    {
      "type": "entrada",
      "datetime": "2026-04-22T08:05:00Z",
      "latitude": -23.5505,
      "longitude": -46.6333,
      "device_id": "uuid",
      "photo_url": "https://storage.googleapis.com/..."
    }
  ]
}
```

---

### Sequência de Pontos (Obrigatória)

O sistema valida a sequência correta de batidas:

```
entrada → saida_almoco → volta_almoco → saida
          ↓ (pular almoço)
          saida
```

---

### Correção de Ponto

```json
POST /api/v1/time-records/{id}/edit-request
{
  "new_datetime": "2026-04-22T08:10:00Z",
  "new_type": "entrada",
  "justification": "Sistema apresentou falha ao registrar o ponto no horário correto."
}
```

### Aprovação de Correções (Gestor/Admin)

| Método | Endpoint | Descrição |
|---|---|---|
| GET | `/edit-requests` | Listar solicitações |
| POST | `/edit-requests/{id}/approve` | Aprovar correção |
| POST | `/edit-requests/{id}/reject` | Rejeitar correção |

---

### Banco de Horas

| Método | Endpoint | Descrição |
|---|---|---|
| GET | `/work-days` | Listar dias trabalhados |
| GET | `/work-days?year=2026&month=4` | Resumo mensal |
| GET | `/work-days/balance` | Saldo de horas por período |

---

### Funcionários (Admin/Gestor)

| Método | Endpoint | Descrição |
|---|---|---|
| GET | `/employees` | Listar funcionários |
| POST | `/employees` | Criar funcionário |
| GET | `/employees/{id}` | Detalhes |
| PUT | `/employees/{id}` | Atualizar |
| POST | `/employees/{id}/dismiss` | Desligar |

---

### Empresas (Admin)

| Método | Endpoint | Descrição |
|---|---|---|
| GET | `/companies` | Listar empresas |
| POST | `/companies` | Criar empresa |
| GET | `/companies/{id}` | Detalhes |
| PUT | `/companies/{id}` | Atualizar |

---

## Estrutura do Banco

```
users               → autenticação + role (admin/gestor/funcionario)
companies           → empresas com config de geofencing
employees           → funcionários vinculados a users + companies
time_records        → registros de ponto (coração do sistema)
work_days           → dias processados com total/extra de horas
time_record_edits   → auditoria de correções (nunca edita direto)
work_schedules      → jornada de trabalho do funcionário
```

---

## Regras de Negócio

### Anti-fraude
- Salva IP, device_info e device_id em cada batida
- Detecta GPS mockado via flag `is_mock_location`
- Foto obrigatória (configurável por empresa)
- Geofencing configurável por empresa

### Auditoria
- Pontos **nunca** são editados diretamente
- Toda correção cria um registro em `time_record_edits` com justificativa
- Correções precisam de aprovação de gestor ou admin

### Offline
- Flutter registra localmente quando sem internet
- Ao reconectar, envia via `POST /time-records/sync-offline`
- Sistema valida sequência retroativamente

### Timezone
- Todos os `datetime` são armazenados em **UTC** no banco
- O campo `datetime_local` na resposta converte para `America/Sao_Paulo`

---

## Integração Flutter

### Fluxo recomendado para bater ponto

1. App pede GPS + tira foto
2. Envia `GET /time-records/signed-upload-url` para obter URL assinada do Firebase
3. App faz upload da foto **diretamente** ao Firebase Storage usando a URL assinada
4. App envia `POST /time-records` com o `photo_url` já pronto (sem tráfego de imagem no Laravel)

### Fluxo offline

1. App salva registro localmente (SQLite)
2. Ao detectar conexão, envia lote via `POST /time-records/sync-offline`

---

## Comandos Úteis

```bash
# Migrations
php artisan migrate

# Seed com dados de teste
php artisan db:seed

# Processar filas
php artisan queue:work

# Recalcular banco de horas de um mês
php artisan ponto:recalculate-month 2026 4

# Sincronizar registros offline pendentes
php artisan ponto:sync-offline

# Listar rotas da API
php artisan route:list --path=api
```

---

## Próximas Funcionalidades (Roadmap)

- [ ] Relatórios PDF (banco de horas mensal, espelho de ponto)
- [ ] Exportação CSV para folha de pagamento
- [ ] Reconhecimento facial na selfie
- [ ] Dashboard em tempo real (WebSockets/Pusher)
- [ ] Integração com eSocial
- [ ] Multi-empresa com isolamento de dados (multitenancy)
- [ ] Alertas de ausência por e-mail/push notification
