# 🤖 Bot WhatsApp PPOB Otomatis (Digiflazz & Tripay)

Bot WhatsApp PPOB yang sangat ringan dan cepat, terintegrasi otomatis dengan **Digiflazz** untuk produk digital dan **Tripay** untuk pembayaran QRIS otomatis. Projek ini dilengkapi dengan **Web Admin Panel** modern yang mendukung fitur *Drag & Drop*.

---

## ✨ Fitur Utama
- **Integrasi Penuh Digiflazz:** Transaksi pulsa, kuota, dan voucher game otomatis 24 jam.
- **Auto-Payment Tripay:** Konfirmasi pembayaran QRIS otomatis tanpa cek manual.
- **Web Admin Panel:** Kelola produk, pengguna, dan transaksi melalui browser dengan UI responsif.
- **Susunan Produk Drag & Drop:** Atur urutan kategori, laci, dan produk hanya dengan menggeser kotak (tahan 0.5 detik untuk memindah).
- **Sistem Saldo Member:** Pengguna bisa daftar dan top-up saldo sendiri secara otomatis.
- **Broadcast Message:** Kirim pesan promosi atau pengumuman ke seluruh member sekaligus.
- **Database SQLite:** Sangat ringan dan mudah dipindahkan tanpa setup MySQL yang rumit.

---

## 🛠️ Persyaratan Sistem
- **VPS Linux** (Ubuntu / Debian sangat disarankan).
- **Akses Root.**
- Koneksi internet stabil.

---

## 🚀 Cara Instalasi (Auto-Installer)

Saya sudah menyediakan skrip instalasi otomatis. Jalankan perintah berikut di terminal VPS kamu:

1. **Klon repositori ini:**
   ```bash
   git clone https://github.com/ogoyarp/Bot-PPOB-Whatsapp.git
   cd Bot-PPOB-Whatsapp

2. **Berikan izin dan jalankan installer:**
   ```bash
   chmod +x install.sh
   ./install.sh
   
  Installer ini akan menginstal Node.js, PHP, Apache2, dan semua modul yang dibutuhkan secara otomatis.

3. **Jalankan Bot WhatsApp:**
   ```bash
   cd bot
   pm2 start index.js --name bot-ppob
   pm2 logs bot-ppob
Scan kode QR atau masukkan Pairing Code yang muncul di log terminal menggunakan aplikasi WhatsApp kamu.

---

## 💻 Mengakses Panel Admin

Buka browser dan akses alamat berikut:
http://IP-VPS-KAMU/panel/admin.php

-Password Default: rahasia123
-Segera ubah password kamu di menu Pengaturan Server untuk keamanan.

---

## 📝 Konfigurasi API

Untuk memulai transaksi, masukkan API Key kamu di menu Pengaturan Server pada Panel Admin:

1. **Digiflazz**: Masukkan Username dan API Key (Production/Dev).
2. **Tripay**: Masukkan API Key, Private Key, dan Merchant Code kamu.
3. **WhatsApp Admin**: Masukkan nomor WhatsApp kamu (format 62xxx) untuk menerima notifikasi deposit/transaksi.

---

## 🤝 Kontribusi

Projek ini bersifat terbuka (open-source). Jika ingin menambah fitur atau melaporkan bug, silakan kirim Pull Request atau buka Issue.

## 📜 Lisensi

Projek ini di bawah lisensi MIT License.

---

Dibuat dengan ❤️ untuk komunitas PPOB Indonesia.
"# Bot-PPOB-Whatsapp" 
