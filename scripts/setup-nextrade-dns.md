# nextradeai.io DNS setup

The APK and site are on Lightsail at **35.168.213.207**. Downloads work at:

- https://www.nextradeai.io/admin/downloads/nextradeai.apk ✅ (www already resolves)

If Safari shows **“Can't Find the Server nextradeai.io”**, the **apex** domain has no A record yet.

## Fix in AWS Route 53

1. AWS Console → **Route 53** → **Hosted zones** → **nextradeai.io**
2. **Create record**:
   - **Record name:** leave blank (apex `@`)
   - **Type:** `A`
   - **Value:** `35.168.213.207`
   - **TTL:** 300
3. Save. Propagation usually takes 5–30 minutes.

Optional: ensure `www` also points to `35.168.213.207` (already configured).

## Verify

```bash
dig @8.8.8.8 nextradeai.io A +short
# should print: 35.168.213.207

curl -sI https://nextradeai.io/admin/downloads/nextradeai.apk | head -3
# should print: HTTP/1.1 200 OK
```
