# ДЗ 30. REST API с обработкой через очередь (RabbitMQ)

Домашнее задание «API»: универсальный REST-интерфейс для любых потребителей
(frontend, мобильные и сторонние приложения). Клиент отправляет запрос,
API складывает его в очередь RabbitMQ и **немедленно** возвращает номер заявки.
Фоновый worker обрабатывает заявку, а клиент периодически опрашивает статус
по номеру заявки.

## Архитектура

```text
клиент (curl / SPA / мобильное приложение)
        |
        | POST /api/v1/requests          -> 202 + {id, statusUrl}
        v
web (PHP, фронт-контроллер) --публикация--> RabbitMQ (durable очередь)
                                                 |
                                                 | prefetch=1, manual ack
                                                 v
                                            worker (PHP CLI)
                                                 |-> статус: processing -> completed
                                                 `-> результат сохраняется в хранилище
        клиент опрашивает GET /api/v1/requests/{id} до статуса completed
```

- `web` — REST-эндпоинты, валидация, публикация в очередь;
- `rabbitmq` — durable-очередь `statement_requests`, persistent-сообщения (`delivery_mode=2`);
- `worker` — консольный обработчик: `prefetch=1`, ручной `ack` только после успешной обработки;
- Docker volume — общее хранилище статусов и результатов.

## Эндпоинты

| Метод | Путь | Описание |
|-------|------|----------|
| `POST` | `/api/v1/requests` | Создать заявку. Ответ `202` + номер заявки и `statusUrl` |
| `GET` | `/api/v1/requests/{id}` | Статус заявки: `queued` → `processing` → `completed` (+ `report`) |
| `GET` | `/api/v1/requests` | Список всех заявок |
| `GET` | `/api/health` | Проверка доступности API |
| `GET` | `/docs` | Swagger UI (по спецификации `/openapi.yaml`) |

Коды ответов: `202` — заявка принята; `400` — ошибка валидации (с деталями по полям);
`404` — заявка не найдена; `405` — метод не поддерживается; `503` — очередь недоступна.

## Требования

- Docker Desktop с Docker Compose;
- свободные порты `8081` и `15673`.

PHP, RabbitMQ отдельно устанавливать не нужно.

## Запуск

```bash
cd api-homework
docker compose up --build -d
```

Открыть:

- Swagger UI: <http://localhost:8081/docs>;
- спецификация OpenAPI: <http://localhost:8081/openapi.yaml>;
- RabbitMQ Management: <http://localhost:15673> (`app` / `secret`).

## Пример сценария (curl)

1. Создать заявку:

```bash
curl -i -X POST http://localhost:8081/api/v1/requests \
  -H 'Content-Type: application/json' \
  -d '{"email":"user@example.com","dateFrom":"2025-01-01","dateTo":"2025-06-30"}'
```

Ответ:

```json
HTTP/1.1 202 Accepted
{
  "id": "a1b2c3d4e5f60718",
  "status": "queued",
  "createdAt": "2025-06-30T12:00:00+03:00",
  "statusUrl": "/api/v1/requests/a1b2c3d4e5f60718"
}
```

2. Опросить статус по номеру заявки (запросы можно повторять):

```bash
curl http://localhost:8081/api/v1/requests/a1b2c3d4e5f60718
```

Пока заявка обрабатывается, ответ содержит `"status": "queued"` или `"processing"`.
Когда `"status": "completed"` — в ответе появляется `report`:

```json
{
  "id": "a1b2c3d4e5f60718",
  "status": "completed",
  "report": {
    "period": "01.01.2025 — 30.06.2025",
    "openingBalance": 1234.56,
    "closingBalance": 12384.06,
    "operations": [
      {"date": "2025-01-01", "description": "Входящий перевод", "amount": 12500.00},
      {"date": "2025-06-30", "description": "Оплата услуг", "amount": -1350.50}
    ]
  },
  "completedAt": "2025-06-30T12:00:05+03:00"
}
```

3. Ошибка валидации возвращает `400` с деталями:

```bash
curl -i -X POST http://localhost:8081/api/v1/requests \
  -H 'Content-Type: application/json' \
  -d '{"email":"bad","dateFrom":"2025-01-01"}'
```

Логи worker:

```bash
docker compose logs -f worker
```

Остановка: `docker compose down`; вместе с данными: `docker compose down -v`.

## Надёжность очереди

- Очередь объявлена durable, сообщения публикуются с `delivery_mode=2` — заявки
  не теряются при перезапуске брокера или worker-а;
- worker использует `prefetch=1` и не подтверждает (`ack`) сообщение до успешной
  записи результата — при сбое сообщение будет доставлено повторно;
- статус сохраняется атомарно (временный файл + `rename`), поэтому клиент,
  опрашивающий статус, не прочитает частично записанный документ;
- идентификатор заявки проверяется строгим шаблоном, исключая выход за каталог хранилища.

## Обоснование использования очереди

Очередь отделяет приём HTTP-запроса от длительной обработки: API всегда отвечает
мгновенно, нагрузку можно масштабировать добавлением worker-ов, пик нагрузки
сглаживается очередью. Цена — дополнительная инфраструктура и eventual consistency:
результат появляется не сразу, поэтому клиенту нужен номер заявки и периодический
опрос статуса (для этого возвращается `statusUrl`).

Для production стоило бы добавить: retry с ограничением попыток и dead-letter queue,
идемпотентность по `message_id`, постоянную БД вместо файлового хранилища
и webhook/SSE-уведомления вместо поллинга.
