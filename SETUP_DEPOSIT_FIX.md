# Setup Deposit Reconciliation (2 Layer Safety Net)

```
User bayar di SquadOnyx
         ↓
    ┌────────────────────────────────────────┐
    │ Layer 1: Webhook Callback (real-time)  │  ← 0-5 detik, paling cepat
    │ Kalau gagal → PG retry 3x              │
    └────────────────────────────────────────┘
         ↓ (kalau callback gagal)
    ┌────────────────────────────────────────┐
    │ Layer 2: Auto-reconcile on page load   │  ← user trigger
    │ User refresh/buka app → re-check SQX   │
    └────────────────────────────────────────┘
```

---

## 📦 Urutan Deploy

### Step 1: Upload full project ke hosting

Replace file lama dengan yang di zip.

### Step 2: Buka `setup.php` di browser

```
https://cuanvvipgg.xyz/setup.php
```

Ini bakal auto-bikin 4 index MySQL buat performance. Aman dijalanin berulang kali (pakai try-catch).

### Step 3: Paste snippet ke `layout.php`

Buka `layout.php`, cari `</body>`. Paste isi `reconcile_snippet.html` **SEBELUM** `</body>`.

Ini yang bikin Layer 2 aktif — tiap user buka/refresh halaman, auto-reconcile pending deposits dia.

### Step 4: (Opsional tapi recommended) Protect setup.php

Habis setup selesai, delete `setup.php` atau rename jadi random name, biar orang luar ga bisa trigger ulang.

### Step 5: Test

1. Buat 1 deposit test (nominal kecil)
2. Bayar di SquadOnyx
3. Cek `deposit_log.txt` — harus ada `[callback] SUCCESS credit`
4. Cek saldo user bertambah

---

## 🔍 Debug

### Cek log

```bash
tail -f callback_log.txt   # webhook SquadOnyx masuk
tail -f deposit_log.txt    # proses credit & error
```

### Kalau ada deposit nyangkut

Cara 1 — user refresh halaman deposit: auto-reconcile kejar.

Cara 2 — manual via URL (login dulu sebagai user):
```
https://cuanvvipgg.xyz/api/deposit.php?action=check&tx_id=LXY260417XXXXX
```

Cara 3 — paksa credit dari admin panel (harus login):
```
POST deposit.php action=confirm tx_id=LXY260417XXXXX
```

### Gejala & Solusi

| Gejala | Kemungkinan | Fix |
|--------|-------------|-----|
| Callback ga masuk sama sekali | URL callback salah di dashboard SquadOnyx | Hubungi PG, minta set ke `https://cuanvvipgg.xyz/api/deposit.php?action=callback` |
| Saldo ga nambah padahal paid | `pay_amount` mismatch >10 | Cek `deposit_log.txt` → cari `MISMATCH` |
| Deposit stuck pending lama | Callback + polling sama-sama gagal | User buka halaman deposit → auto-reconcile. Atau admin trigger manual |

---

## 🎯 Endpoint

| Endpoint | Siapa pake | Kapan |
|----------|-----------|-------|
| `POST deposit.php action=callback` | SquadOnyx | Layer 1 — real-time saat user bayar |
| `POST deposit.php action=check` | User | Layer 2 — saat polling halaman deposit |
| `POST deposit.php action=pending` | User | Layer 2 — saat load list pending |
| `POST deposit.php action=reconcile_mine` | User | Layer 2 — silent background check saat buka app |
| `POST deposit.php action=confirm` | Admin | Manual force-credit |
