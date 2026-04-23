# Goal Description

Develop a lightweight, "upload-and-play" RAG-based AI customer service system tailored for vertical e-commerce websites. The system is designed to run in standard shared PHP hosting environments without requiring persistent daemon processes or root access. It features a bilingual PHP administrative dashboard, SQLite for local data persistence, Qdrant Cloud for vector storage, and utilizes Gemini/Groq APIs for AI models. The frontend is a lightweight, embeddable Preact widget.

## User Review Required

> [!IMPORTANT]
> **WordPress Admin Login Implementation**
> To avoid dependency conflicts and heavy performance overhead from including the entire WordPress core (`wp-load.php`) on every login attempt, the system will directly parse `wp-config.php` to extract the database credentials (`DB_NAME`, `DB_USER`, `DB_PASSWORD`, `DB_HOST`, `table_prefix`). It will then connect to the WordPress database directly and verify the password hash using a lightweight implementation of WordPress's `phpass` hashing algorithm. 

> [!IMPORTANT]
> **Pseudo-Cron Execution**
> The "fake cron" system will be triggered on incoming HTTP requests (e.g., when the chat widget loads or polls). If the current time surpasses the scheduled execution time (e.g., daily at 4:00 AM), the script will execute the product feed sync before returning the response. 
> *Note:* If the sync takes a long time (e.g., hundreds of products), it might delay that specific HTTP request for the user who accidentally triggered it. To mitigate this, we can optionally use `fastcgi_finish_request()` (if FPM is available) or keep the sync chunks small. 

## Proposed Architecture & Workflow

### 1. Technology Stack
- **Backend Core**: PHP 8.x + Slim Framework 4 (REST API & routing).
- **Database**: SQLite (stored in a writable `data/` directory).
- **Vector DB**: Qdrant Cloud (accessed via Qdrant REST API).
- **Frontend Admin UI**: PHP Native Templates + Tailwind CSS (via CDN or pre-compiled).
- **Frontend Widget**: Preact + Vite (compiled to a single `widget.js`).
- **AI Models**: Gemini (Embedding) + Gemini/Groq (LLM with Function Calling).

### 2. Database Schema (SQLite)

The SQLite database will act as the source of truth for the admin state and the mirror for Qdrant payloads.

- **`settings`**: Stores configuration keys (e.g., `llm_provider`, `groq_api_key`, `gemini_api_key`, `qdrant_url`, `qdrant_api_key`, `admin_email`, `escalation_message`, `enable_wp_login`, `wp_path`, `cron_last_run`, `product_feed_url`, `smtp_host`, `smtp_port`, `smtp_user`, `smtp_pass`, `smtp_encryption`).
- **`knowledge`**: `id`, `type` ('text' or 'qa'), `content` (for text/question), `answer` (for Q&A), `qdrant_id` (UUID reference).
- **`products`**: `id` (internal), `product_id` (feed ID), `sku`, `hash` (MD5 of content to detect changes), `qdrant_id` (UUID reference).

### 3. Data Ingestion & Synchronization Workflow

#### Text & Q&A Management
- The Admin UI will provide pages to **Create, Read, Update, and Delete (CRUD)** text snippets and Q&A pairs.
- Changes made in the UI will update both the SQLite database and the corresponding vectors in Qdrant.

#### Product Feed Synchronization
- **Trigger**: The pseudo-cron checks `cron_last_run`. If due, it fetches the XML feed.
- **Diff Logic**:
  1. Parse the XML feed into an array of products.
  2. For each product, strip HTML and create a combined string for embedding. Compute an MD5 hash of this string.
  3. Compare with the `products` table in SQLite based on `product_id`.
  4. **New**: If `product_id` doesn't exist, generate embedding, upload to Qdrant, insert into SQLite.
  5. **Update**: If `product_id` exists but the MD5 `hash` differs, generate new embedding, update Qdrant, update SQLite.
  6. **Delete**: If a `product_id` exists in SQLite but is missing from the XML feed, delete from Qdrant and delete from SQLite.

### 4. Double-LLM RAG & Escalation Workflow

1. **User Query**: User sends a message via the Preact widget.
2. **Intent Extraction (LLM 1)**: The backend sends the query to the LLM to extract an emotionless "Search Intent".
3. **Vector Search**: The intent is sent to Gemini Embedding API. The resulting vector queries Qdrant.
4. **Generation & Tool Calling (LLM 2)**: 
   - The backend packages the retrieved context, original query, and a strict System Prompt.
   - The LLM is provided with a tool: `contact_human(summary)`.
   - **Instruction**: "If the context lacks the answer, politely inform the user and ask if they would like you to contact the staff. If the user says 'Yes', use the `contact_human` tool with a summary of their issue."
5. **Handling the Tool**: 
   - If the LLM uses `contact_human`, PHP executes the tool by sending an email to `admin_email` containing the generated summary. The email will be sent securely via **PHPMailer** using the SMTP settings configured in the admin dashboard. It then returns the `escalation_message` (configured in Settings) to the chat widget.

### 5. Application Structure

```text
/
├── public/                 # Web root
│   ├── index.php           # Slim Framework entry point
│   ├── widget.js           # Compiled Preact widget
│   └── css/tailwind.css    
├── src/
│   ├── App/                # Routes, Dependencies, Pseudo-Cron Middleware
│   ├── Controllers/        # AdminController, ChatController
│   ├── PHPMailer/          # Core files (Exception.php, PHPMailer.php, SMTP.php)
│   ├── Services/           # LlmService, VectorService, SyncService, AuthService, EmailService
│   └── views/              # PHP Templates (login, settings, data lists)
├── data/
│   └── database.sqlite     # Writable SQLite file
├── widget/                 # Preact Source Code
│   ├── src/
│   ├── package.json
│   └── vite.config.js      # Configured with @preact/preset-vite
└── composer.json           # PHP Dependencies
```

## Verification Plan

### Automated/Unit Testing
- Use Postman/cURL to verify the REST API endpoints for chat and widget loading.
- Verify Qdrant API calls using dummy vectors to ensure payloads are formatted correctly.

### Manual Verification
- **Installation**: Load the application in a fresh environment, complete the init screen, and verify SQLite creation.
- **Data CRUD**: Upload Text, Q&A, and trigger a Product Feed sync. Verify data mirrors correctly between SQLite and Qdrant.
- **WordPress Auth**: Provide a path to a dummy WordPress installation, verify that valid WP admin credentials allow login, while invalid ones fail.
- **RAG & Escalation**: 
  - Ask a question covered in the data -> expect a context-based answer.
  - Ask an unknown question -> expect the LLM to ask for permission to escalate.
  - Reply "Yes" -> verify the fallback message is displayed and the summary email is "sent" (logged or actual email depending on config).
- **Pseudo-Cron**: Manually manipulate the `cron_last_run` time in SQLite to be older than 24 hours, hit the site, and verify the sync runs.
