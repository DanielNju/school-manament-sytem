# Deployment

## 1. Architecture

Production architecture:

```text
User
 │
 ▼
Frontend
 │
 ▼
HTTPS
 │
 ▼
Backend API
 │
 ├── PostgreSQL
 │
 ├── File Storage
 │
 └── External Services
```

---

# 2. Frontend

Recommended platform:

```text
Vercel
```

Build:

```bash
npm run build
```

Environment variables:

```text
VITE_API_URL=
```

---

# 3. Backend

Recommended platforms:

- Railway
- Render
- VPS
- Other production Node.js hosting

Environment variables:

```text
PORT=
DATABASE_URL=
JWT_SECRET=
JWT_REFRESH_SECRET=
CORS_ORIGIN=
```

Production secrets must never be committed to Git.

---

# 4. Database

Production database:

```text
PostgreSQL
```

Requirements:

- Automated backups
- Restricted access
- Strong credentials
- Connection encryption
- Migration management

---

# 5. File Storage

School documents should use dedicated object storage.

Examples:

- Cloudflare R2
- AWS S3
- Cloudinary

Do not store large uploaded files directly inside the PostgreSQL database.

---

# 6. Domains

Production may use:

```text
schoolmanagement.example
api.schoolmanagement.example
```

---

# 7. HTTPS

All production traffic must use HTTPS.

---

# 8. CI/CD

Recommended workflow:

```text
Developer
   ↓
Git
   ↓
Pull Request
   ↓
Tests
   ↓
Build
   ↓
Review
   ↓
Deployment
```

---

# 9. Environment Separation

Use separate environments:

```text
Development
Testing/Staging
Production
```

Never use production credentials during local development.

---

# 10. Monitoring

Production should monitor:

- API errors
- Server health
- Database health
- Response time
- Authentication failures
- Storage usage
- Application uptime

---

# 11. Backup Strategy

Backups should include:

- Database
- Important uploaded documents
- Configuration where necessary

Backups should be periodically tested through restoration.

---

# 12. Production Checklist

Before launch:

- [ ] HTTPS enabled
- [ ] Environment variables configured
- [ ] Database migrations complete
- [ ] Database backups enabled
- [ ] Authentication tested
- [ ] RBAC tested
- [ ] API security tested
- [ ] File uploads secured
- [ ] Error monitoring enabled
- [ ] Production build tested
- [ ] Mobile responsiveness tested
- [ ] User acceptance testing completed
