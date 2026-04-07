# EAPS – Easy Access Pair Storage

EAPS is a lightweight PHP key-value store designed for simple, HTTP-based storage and retrieval of named values. Data is organised under **clients** (identified by a `client_key`) and grouped into named **tags**. Within each tag, any number of **key/value pairs** can be stored; every write creates a new historical record so the full value history is always available, and the most recent value is also quickly accessible in a single call.

## Requirements

- PHP 7.4+
- MySQL **or** SQLite
- A web server (Apache, Nginx, …)

## Setup

1. Import the appropriate database schema from the `db/` directory:
   - `db/schema.sql` – SQLite
   - `db/schema-mysql.sql` – MySQL
2. Copy `config.php.example` to `config.php` and fill in your database credentials and preferred driver (`sqlite` or `mysql`).
3. Open `admin.php` in your browser, navigate to the **Manage Clients** section, and create your first client. The API key is generated automatically and shown only once – copy it before leaving the page.

---

## REST API

All requests are made to `index.php`. Every request requires the following query-string parameters:

| Parameter    | Description                                       |
|--------------|---------------------------------------------------|
| `client_key` | The API key that identifies and authenticates the client |
| `tag`        | The tag (namespace) to operate on                 |
| `action`     | One of `tags`, `keys`, `value`, `values`          |

### Response envelope

Successful responses return JSON:

```json
{
  "keys": ["key_name", ...],
  "data": [
    { "key": "key_name", "value_id": 1, "value": "...", "created": 1712345678 },
    ...
  ]
}
```

Error responses return a non-2xx HTTP status and JSON:

```json
{ "error_message": "description of the problem" }
```

---

### `GET ?action=tags`

Returns all tags that belong to the authenticated client.

**Required parameters:** `client_key`, `tag`

**Example:**

```bash
curl "https://example.com/EAPS/?action=tags&client_key=YOUR_KEY&tag=sensors"
```

**Example response:**

```json
{
  "keys": null,
  "data": [
    { "tag_id": 1, "tag_name": "sensors" }
  ]
}
```

---

### `GET ?action=keys`

Returns all keys that exist under the given tag.

**Required parameters:** `client_key`, `tag`

**Example:**

```bash
curl "https://example.com/EAPS/?action=keys&client_key=YOUR_KEY&tag=sensors"
```

**Example response:**

```json
{
  "keys": [
    { "key_id": 1, "key_name": "temperature" }
  ],
  "data": null
}
```

---

### `GET ?action=value&key=<key>`

Returns the **latest** stored value for a single key within the given tag.

**Required parameters:** `client_key`, `tag`, `key`

**Example:**

```bash
curl "https://example.com/EAPS/?action=value&client_key=YOUR_KEY&tag=sensors&key=temperature"
```

**Example response:**

```json
{
  "keys": ["temperature"],
  "data": [
    { "key": "temperature", "value_id": 42, "value": "21.5", "created": 1712345678 }
  ]
}
```

If no value has been stored yet, both `keys` and `data` entries will be `null`.

---

### `POST ?action=value&tag=<tag>`

Stores a new value for a key. Tags and keys are created automatically if they do not already exist.

**Required parameters (query string):** `client_key`, `tag`  
**Required parameters (POST body):** `key`, `value`

**Returns:** HTTP 201 with an empty body on success.

**Example:**

```bash
curl -X POST "https://example.com/EAPS/?action=value&client_key=YOUR_KEY&tag=sensors" \
     --data "key=temperature&value=21.5"
```

---

### `GET ?action=values`

Returns **all historical values** (every recorded entry, not just the latest) for a tag, optionally filtered by key and/or a start time. Results are ordered newest first.

> **Tip:** Use `action=value` when you only need the current value of a single key. Use `action=values` to retrieve the full history of one or all keys under a tag.

**Required parameters:** `client_key`, `tag`  
**Optional parameters:**

| Parameter | Description |
|-----------|-------------|
| `key`     | Restrict results to a single key name |
| `since`   | Unix timestamp **or** any date string accepted by PHP `strtotime()` (e.g. `"yesterday"`, `"2024-01-01"`) |

**Examples:**

```bash
# All history for a tag
curl "https://example.com/EAPS/?action=values&client_key=YOUR_KEY&tag=sensors"

# History for one key
curl "https://example.com/EAPS/?action=values&client_key=YOUR_KEY&tag=sensors&key=temperature"

# History for one key since a specific date
curl "https://example.com/EAPS/?action=values&client_key=YOUR_KEY&tag=sensors&key=temperature&since=2024-01-01"

# History since a Unix timestamp
curl "https://example.com/EAPS/?action=values&client_key=YOUR_KEY&tag=sensors&since=1704067200"
```

**Example response:**

```json
{
  "keys": ["temperature", "humidity"],
  "data": [
    { "key": "temperature", "value_id": 43, "value": "22.0", "created": 1712346000 },
    { "key": "humidity",    "value_id": 31, "value": "55",   "created": 1712345900 },
    { "key": "temperature", "value_id": 42, "value": "21.5", "created": 1712345678 }
  ]
}
```

---

## Admin Interface

`admin.php` provides a browser-based interface for managing clients and querying stored values.

### Managing Clients

The **Manage Clients** section at the top of the admin page lets you:

- **View** all registered clients (API key is shown truncated for security).
- **Add** a new client by entering a name. A cryptographically secure random API key (64-character hex string, 256 bits of entropy) is generated automatically and displayed **only once** – copy it before leaving the page.
- **Delete** a client and all of its associated data.

> The `client_key` acts as both an identifier and a secret API key. It is never stored in plain-readable form in the UI after creation.

### Searching Values

- Filter records by client, tag, key, and date/time range.
- Edit or delete individual value records inline.
- Export search results as a CSV file.

### Security

Access can be restricted to LAN (RFC 1918) addresses and/or protected with HTTP Basic authentication via the `ADMIN_REQUIRE_AUTH`, `ADMIN_USERNAME`, `ADMIN_PASSWORD_HASH`, and `ADMIN_LAN_ONLY` constants in `config.php`.

---

## PHP Client Library

A PHP client library is included in `client_libs/php/`.

### Instantiation

```php
require_once("client_libs/php/libEAPS.php");

$client = new EAPS_client("https://example.com/EAPS/", "your-client-key");
```

### Methods

#### `get_value(string $tag, string $key): EAPSValueResponse`

Fetches the latest value for a single key.

```php
$response = $client->get_value("sensors", "temperature");
```

#### `get_values(string $tag [, string $key [, string|int $since]]): EAPSValueResponse`

Fetches **all historical values** for a tag, with optional key and time filters.

```php
// All history for a tag
$response = $client->get_values("sensors");

// History for one key
$response = $client->get_values("sensors", "temperature");

// History since a date/time string or Unix timestamp
$response = $client->get_values("sensors", false, "yesterday");
$response = $client->get_values("sensors", "temperature", 1704067200);
```

#### `get_keys(string $tag): EAPSKeysResponse`

Fetches all key names for a tag.

```php
$response = $client->get_keys("sensors");
```

#### `add_value(string $tag, string $key, string $value): void`

Stores a new value for a key.

```php
$client->add_value("sensors", "temperature", "21.5");
```

---

## Data Model

```
tClient  (client_id, client_key, client_name)
  └─ tTag  (tag_id, tag_name, client_id)
       └─ tKey  (key_id, key_name, tag_id, newest_value_id)
            └─ tValue  (value_id, key_id, value_data, created)
```

- Each client is isolated; clients cannot access each other's data.
- Tags and keys are created automatically on first write.
- Every write appends a new `tValue` row; `tKey.newest_value_id` always points to the most recent one for fast single-value lookups.
- Use `action=values` to retrieve the full history across all writes.

