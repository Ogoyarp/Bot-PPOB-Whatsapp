const { default: makeWASocket, useMultiFileAuthState, DisconnectReason, fetchLatestBaileysVersion, Browsers } = require('@whiskeysockets/baileys');
const pino = require('pino'); const axios = require('axios'); const crypto = require('crypto');
const express = require('express'); const Database = require('better-sqlite3'); const fs = require('fs');
const readline = require('readline'); 

const rl = readline.createInterface({ input: process.stdin, output: process.stdout });
const question = (text) => new Promise((resolve) => rl.question(text, resolve));

function tulisLog(pesan) {
    const waktu = new Date().toLocaleString('id-ID'); const teks = `[${waktu}] ${pesan}\n`;
    console.log(teks.trim()); fs.appendFileSync('/var/www/html/panel/bot.log', teks);
}

const db = new Database('/var/www/html/panel/bot_cepat.db'); 
db.exec(`
    CREATE TABLE IF NOT EXISTS users (nomor_wa TEXT PRIMARY KEY, nama TEXT, saldo INTEGER DEFAULT 0, status TEXT DEFAULT 'Aktif');
    CREATE TABLE IF NOT EXISTS produk (sku TEXT PRIMARY KEY, kategori TEXT, nama TEXT, harga INTEGER, harga_modal INTEGER DEFAULT 0, deskripsi TEXT, status TEXT DEFAULT 'Aktif');
    CREATE TABLE IF NOT EXISTS transaksi (id INTEGER PRIMARY KEY AUTOINCREMENT, nomor_wa TEXT, invoice TEXT, sku TEXT, nama_produk TEXT, target TEXT, harga INTEGER, profit INTEGER DEFAULT 0, status TEXT, tanggal DATETIME);
    CREATE TABLE IF NOT EXISTS kategori (kode TEXT PRIMARY KEY, nama TEXT);
    CREATE TABLE IF NOT EXISTS laci (kode TEXT PRIMARY KEY, kategori_kode TEXT, nama TEXT, tipe_validasi TEXT DEFAULT 'bebas');
    CREATE TABLE IF NOT EXISTS settings (kunci TEXT PRIMARY KEY, nilai TEXT);
`);

function getApi(kunci) { const r = db.prepare("SELECT nilai FROM settings WHERE kunci = ?").get(kunci); return r ? r.nilai : ''; }

const databaseTagihan = new Map(); const sesiPengguna = new Map();
const recentlyWarned = new Set(); 

async function prosesKeDigiflazz(sender, target, sku, invoice) {
    const produkDB = db.prepare('SELECT nama, harga, harga_modal FROM produk WHERE sku = ?').get(sku);
    const jual = produkDB?.harga || 0; const modal = produkDB?.harga_modal || 0; const profit = jual - modal;
    db.prepare(`INSERT INTO transaksi (nomor_wa, invoice, sku, nama_produk, target, harga, profit, status, tanggal) VALUES (?, ?, ?, ?, ?, ?, ?, ?, datetime('now', 'localtime'))`).run(sender.split('@')[0].split(':')[0], invoice, sku, produkDB?.nama || sku, target, jual, profit, 'Diproses');
    await sock.sendMessage(sender, { text: `✅ Lunas!\n🧾 INV: ${invoice}\n⏳ Diteruskan ke server...` });
    try {
        const signDigi = crypto.createHash('md5').update(getApi('DIGI_USERNAME') + getApi('DIGI_API_KEY') + invoice).digest('hex');
        const hasil = await axios.post('https://api.digiflazz.com/v1/transaction', { username: getApi('DIGI_USERNAME'), buyer_sku_code: sku, customer_no: target, ref_id: invoice, sign: signDigi });
        const status = hasil.data.data.status; db.prepare("UPDATE transaksi SET status = ? WHERE invoice = ?").run(status, invoice);
        if (status === 'Sukses') await sock.sendMessage(sender, { text: `🚀 *SUKSES!*\n🧾 INV: ${invoice}\nStatus: ✅ ${status}\nSN: ${hasil.data.data.sn || '-'}` });
    } catch (e) { tulisLog(`[ERROR] Digiflazz: ${e.message}`); }
}

const app = express(); app.use(express.json());
let sock; 

async function connectToWhatsApp () {
    const { state, saveCreds } = await useMultiFileAuthState('auth_info_baileys');
    const { version } = await fetchLatestBaileysVersion(); 
    
    console.log("⏳ Menyiapkan koneksi ke WhatsApp Server..."); 
    sock = makeWASocket({ version, auth: state, printQRInTerminal: false, logger: pino({ level: "silent" }), browser: Browsers.ubuntu('Chrome') });
    
    if (!sock.authState.creds.registered) {
        setTimeout(async () => {
            const noHp = await question("📱 Masukkan Nomor WhatsApp Bot (Gunakan awalan 62, contoh: 62822...): ");
            const code = await sock.requestPairingCode(noHp.replace(/\D/g, ''));
            console.log(`\n✨ KODE PENAUTAN KAMU: ${code}\n`);
            console.log("➡️  Buka WhatsApp > Titik Tiga > Perangkat Tertaut > Tautkan dengan Nomor Telepon.");
        }, 3000);
    }

    sock.ev.on('creds.update', saveCreds);
    sock.ev.on('connection.update', (u) => { 
        if (u.connection === 'close') connectToWhatsApp(); 
        if (u.connection === 'open') console.log("🚀 BOT WHATSAPP ONLINE!"); 
    });
    
    if (!app.locals.serverMenyala) { app.listen(3000); app.locals.serverMenyala = true; }
    app.get('/restart', (req, res) => { res.send('OK'); setTimeout(() => { process.exit(1); }, 1000); });

    sock.ev.on('messages.upsert', async m => {
        const msg = m.messages[0]; if(!msg.message || msg.key.fromMe) return;
        const text = msg.message.conversation || msg.message.extendedTextMessage?.text || "";
        const sender = msg.key.remoteJid; const nomorHp = sender.split('@')[0].split(':')[0]; 
        const teksKecil = text.toLowerCase().trim();

        if (text) tulisLog(`[CHAT] ${nomorHp} : ${text}`);

        let user = db.prepare('SELECT * FROM users WHERE nomor_wa = ?').get(nomorHp);
        if (user && user.status === 'Banned') return;

        const botStatus = getApi('BOT_STATUS') || 'online';
        if (botStatus === 'maintenance' && !teksKecil.includes('daftar')) {
            if (!recentlyWarned.has(sender)) {
                recentlyWarned.add(sender); setTimeout(() => recentlyWarned.delete(sender), 60000); 
                await sock.sendMessage(sender, { text: `⚠️ *MOHON MAAF*\n\nSaat ini sistem sedang Maintenance. 🙏` });
            } return; 
        }

        if (!user) {
            if (teksKecil === 'daftar') {
                const namaMember = msg.pushName || 'Kak';
                db.prepare('INSERT INTO users (nomor_wa, nama, status) VALUES (?, ?, ?)').run(nomorHp, namaMember, 'Aktif');
                try { const noAdmin = getApi('NOMOR_ADMIN'); if (noAdmin) await sock.sendMessage(noAdmin + '@s.whatsapp.net', { text: `🔔 *INFO ADMIN: MEMBER BARU*\n\nNama: ${namaMember}\nNomor: ${nomorHp}\nTotal Member: ${db.prepare('SELECT COUNT(*) AS total FROM users').get().total}` }); } catch(e) {}
                return await sock.sendMessage(sender, { text: `🎉 *Pendaftaran Berhasil!*\n\nSelamat datang kak *${namaMember}*! Terima kasih sudah bergabung.\n\nSekarang kakak sudah bisa melihat daftar produk dan melakukan transaksi.\n\nSilakan ketik *MENU* untuk menampilkan pilihan layanan kami. 😊` });
            } else {
                return await sock.sendMessage(sender, { text: `👋 Halo kak! Selamat datang di Layanan Bot PPOB kami.\n\nUntuk keamanan dan kenyamanan transaksi, mohon melakukan pendaftaran terlebih dahulu ya.\n\nSilakan ketik: *DAFTAR*` });
            }
        }

        const sesi = sesiPengguna.get(sender) || { langkah: 'IDLE' };
        const FOOTER = `_(Masukkan pilihan angka, Ketik MENU untuk ke awal, Ketik 0 untuk Kembali)_`;
        let stext = text;
        if (teksKecil === 'menu') { sesiPengguna.delete(sender); sesi.langkah = 'IDLE'; }
        else if (teksKecil === '0') { 
            if (sesi.langkah === 'PILIH_PRODUK') { sesi.langkah = 'PILIH_KATEGORI'; stext = sesi.kategoriPilihan; }
            else if (sesi.langkah === 'INPUT_TARGET') { sesi.langkah = 'PILIH_LACI'; stext = sesi.laciPilihan; }
            else sesi.langkah = 'IDLE';
        }

        const angkaKeEmoji = (n) => n.toString().split('').map(d => d + '️⃣').join('');

        if (sesi.langkah === 'IDLE') {
            // 🟢 BOT SEKARANG MEMBACA URUTAN KATEGORI
            const listKategori = db.prepare('SELECT * FROM kategori ORDER BY urutan ASC, nama ASC').all(); sesi.petaKategori = {};
            let teksMenu = `Halo *${user.nama}* 👋\n💰 Saldo: Rp${user.saldo.toLocaleString('id-ID')}\n====================\n*PILIH KATEGORI:*\n\n`;
            listKategori.forEach((k, i) => { const a = (i+1).toString(); teksMenu += `   ${angkaKeEmoji(a)} ${k.nama}\n`; sesi.petaKategori[a] = k; });
            teksMenu += `\n*AKUN:*\n   0️⃣1️⃣ Isi Saldo\n   0️⃣2️⃣ Riwayat`;
            sesi.langkah = 'PILIH_KATEGORI'; sesiPengguna.set(sender, sesi); return await sock.sendMessage(sender, { text: teksMenu.trim() + '\n\n' + FOOTER });
        }

        if (sesi.langkah === 'PILIH_KATEGORI') {
            if (stext === '01') { sesi.langkah = 'DEPOSIT'; sesiPengguna.set(sender, sesi); return await sock.sendMessage(sender, { text: `Ketik nominal Deposit (Min 10rb):` }); }
            if (stext === '02') {
                const riwayat = db.prepare(`SELECT * FROM transaksi WHERE nomor_wa = ? ORDER BY id DESC LIMIT 5`).all(nomorHp);
                if (riwayat.length === 0) return await sock.sendMessage(sender, { text: `Belum ada transaksi.` });
                let t = `📋 *RIWAYAT TRANSAKSI (7 Hari Terakhir)*\n\n`; 
                riwayat.forEach((r, i) => { t += `${i+1}. *${r.nama_produk}*\n🧾 Invoice: ${r.invoice}\n📅 ${r.tanggal}\n🎯 Target: ${r.target}\n📊 Status: *${r.status}*\n\n`; });
                return await sock.sendMessage(sender, { text: t.trim() + '\n\n' + FOOTER });
            }
            const kat = sesi.petaKategori?.[stext]; if (!kat) return await sock.sendMessage(sender, { text: `⚠️ Salah.` });
            sesi.kategoriPilihan = stext; 
            // 🟢 BOT SEKARANG MEMBACA URUTAN LACI
            const listLaci = db.prepare('SELECT * FROM laci WHERE kategori_kode = ? ORDER BY urutan ASC, nama ASC').all(kat.kode);
            sesi.petaLaci = {}; let tl = `📶 *Pilih ${kat.nama}:*\n\n`;
            listLaci.forEach((l, i) => { const a = (i+1).toString(); tl += `   ${angkaKeEmoji(a)} ${l.nama}\n`; sesi.petaLaci[a] = l; });
            sesi.langkah = 'PILIH_LACI'; sesiPengguna.set(sender, sesi); return await sock.sendMessage(sender, { text: tl.trim() + '\n\n' + FOOTER });
        }

        if (sesi.langkah === 'PILIH_LACI') {
            const laci = sesi.petaLaci?.[stext]; if (!laci) return await sock.sendMessage(sender, { text: `⚠️ Salah.` });
            sesi.laciPilihan = stext; sesi.laciData = laci;
            // 🟢 BOT SEKARANG MEMBACA URUTAN PRODUK
            const prods = db.prepare('SELECT * FROM produk WHERE kategori = ? ORDER BY urutan ASC, harga ASC').all(laci.kode);
            sesi.petaProduk = {}; let tp = `🛍️ *PRODUK ${laci.nama}:*\n\n`;
            
            prods.forEach((p, i) => { 
                const a = (i+1).toString(); 
                const labelStatus = p.status === 'Gangguan' ? ' 🚫 *(GANGGUAN)*' : '';
                
                tp += `${angkaKeEmoji(a)} *${p.nama}* - Rp${p.harga.toLocaleString('id-ID')}${labelStatus}\n`; 
                if (p.deskripsi && p.deskripsi !== '-') {
                    tp += `> ${p.deskripsi}\n`;
                }
                tp += `\n`; 
                
                sesi.petaProduk[a] = p; 
            });
            
            sesi.langkah = 'PILIH_PRODUK'; sesiPengguna.set(sender, sesi); return await sock.sendMessage(sender, { text: tp.trim() + '\n\n' + FOOTER });
        }

        if (sesi.langkah === 'PILIH_PRODUK') {
            const prod = sesi.petaProduk?.[stext]; if (!prod) return await sock.sendMessage(sender, { text: `⚠️ Salah.` });
            sesi.produkData = prod; sesi.langkah = 'INPUT_TARGET'; sesiPengguna.set(sender, sesi);
            
            let teksTarget = "Masukkan Nomor Tujuan / ID Game:";
            if (sesi.laciData.tipe_validasi === 'id_ml') {
                teksTarget = "Silakan masukkan User ID + Zone ID Mobile Legend Anda untuk menyelesaikan transaksi. Contoh : 12345678(1234).";
            } else if (sesi.laciData.tipe_validasi === 'nomor_hp') {
                teksTarget = "Masukkan Nomor Tujuan. (Contoh: 081234567890):";
            }
            
            return await sock.sendMessage(sender, { text: teksTarget.trim() + '\n\n' + FOOTER });
        }

        if (sesi.langkah === 'INPUT_TARGET') {
            let target = stext.trim(); const validasi = sesi.laciData.tipe_validasi; let namaNick = "";
            if (validasi === 'nomor_hp') { target = target.replace(/\D/g, ''); if(target.startsWith('62')) target = '0'+target.substring(2); if (target.length < 10 || !target.startsWith('08')) return await sock.sendMessage(sender, { text: `⚠️ Format HP salah!` }); } 
            else if (validasi === 'id_ml') { if (!/^\d{5,12}\(\d{4,6}\)$/.test(target)) return await sock.sendMessage(sender, { text: `⚠️ Format ML salah! Wajib kurung.` }); await sock.sendMessage(sender, { text: `⏳ Cek ID...` }); try { const r = await axios.get(`https://api.isan.eu.org/nickname/ml?id=${target.split('(')[0]}&zone=${target.split('(')[1].replace(')','')}`); namaNick = r.data.success ? r.data.name : "Valid"; } catch(e) { namaNick = "Valid"; } }
            else if (validasi === 'id_ff') { if (!/^\d{8,12}$/.test(target)) return await sock.sendMessage(sender, { text: `⚠️ Format FF salah!` }); await sock.sendMessage(sender, { text: `⏳ Cek ID...` }); try { const r = await axios.get(`https://api.isan.eu.org/nickname/ff?id=${target}`); namaNick = r.data.success ? r.data.name : "Valid"; } catch(e) { namaNick = "Valid"; } }
            
            sesi.target = target; sesi.langkah = 'BAYAR'; sesiPengguna.set(sender, sesi);
            
            let labelTarget = validasi === 'nomor_hp' ? 'Nomer Tujuan' : 'Target';
            
            let rekap = `📝 *REKAP PESANAN*\n   ▪️ Item: ${sesi.produkData.nama}\n   ▪️ ${labelTarget}: ${target}\n`; 
            if (namaNick) rekap += `   ▪️ Nick: *${namaNick}*\n`;
            rekap += `   ▪️ Total: Rp${sesi.produkData.harga.toLocaleString('id-ID')}\n\n*BAYAR DENGAN:*\n   1️⃣ Potong Saldo (Rp${user.saldo.toLocaleString('id-ID')})\n   2️⃣ QRIS`;
            return await sock.sendMessage(sender, { text: rekap.trim() + '\n\n' + FOOTER });
        }

        if (sesi.langkah === 'BAYAR') {
            const hrg = sesi.produkData.harga;
            if (stext === '1') {
                if (user.saldo < hrg) return await sock.sendMessage(sender, { text: `❌ Saldo kurang.` });
                db.prepare('UPDATE users SET saldo = saldo - ? WHERE nomor_wa = ?').run(hrg, nomorHp);
                await prosesKeDigiflazz(sender, sesi.target, sesi.produkData.sku, 'SALDO-' + Date.now());
            } else if (stext === '2') {
                await sock.sendMessage(sender, { text: `⏳ Menyiapkan QRIS...` });
                const inv = 'INV-' + Date.now();
                const profit = hrg - (sesi.produkData.harga_modal || 0);
                
                db.prepare(`INSERT INTO transaksi (nomor_wa, invoice, sku, nama_produk, target, harga, profit, status, tanggal) VALUES (?, ?, ?, ?, ?, ?, ?, ?, datetime('now', 'localtime'))`).run(nomorHp, inv, sesi.produkData.sku, sesi.produkData.nama, sesi.target, hrg, profit, 'Menunggu Pembayaran');
                databaseTagihan.set(inv, { jenis: 'PPOB', sender: sender, sku: sesi.produkData.sku, target: sesi.target });
                try {
                    const sig = crypto.createHmac('sha256', getApi('TRIPAY_PRIVATE_KEY')).update(getApi('TRIPAY_MERCHANT_CODE') + inv + hrg).digest('hex');
                    const res = await axios.post('https://tripay.co.id/api-sandbox/transaction/create', { 'method': 'QRIS', 'merchant_ref': inv, 'amount': hrg, 'customer_name': user.nama, 'customer_email': `${nomorHp}@pembeli.com`, 'customer_phone': nomorHp, 'order_items': [{ 'sku': sesi.produkData.sku, 'name': sesi.produkData.nama, 'price': hrg, 'quantity': 1 }], 'signature': sig }, { headers: { 'Authorization': 'Bearer ' + getApi('TRIPAY_API_KEY') } });
                    await sock.sendMessage(sender, { image: { url: res.data.data.qr_url }, caption: `🧾 *INV:* ${inv}\n✅ Scan QRIS ini untuk bayar Rp${hrg.toLocaleString('id-ID')}` });
                } catch (e) { await sock.sendMessage(sender, { text: `❌ Gagal memuat QRIS.\n⚠️ Alasan: ${e.response?.data?.message || e.message}` }); }
            }
            sesiPengguna.delete(sender);
        }

        if (sesi.langkah === 'DEPOSIT') {
            const nominal = parseInt(stext); if (isNaN(nominal) || nominal < 10000) return await sock.sendMessage(sender, { text: `⚠️ Minimal deposit Rp10.000.` });
            await sock.sendMessage(sender, { text: `⏳ Membuat QRIS Deposit...` });
            const inv = 'DEP-' + Date.now();
            db.prepare(`INSERT INTO transaksi (nomor_wa, invoice, sku, nama_produk, target, harga, profit, status, tanggal) VALUES (?, ?, ?, ?, ?, ?, ?, ?, datetime('now', 'localtime'))`).run(nomorHp, inv, 'DEPO', 'Isi Saldo', nomorHp, nominal, 0, 'Menunggu Pembayaran');
            databaseTagihan.set(inv, { jenis: 'DEPOSIT', sender: sender, nomor_wa: nomorHp, nominal: nominal });
            try {
                const sig = crypto.createHmac('sha256', getApi('TRIPAY_PRIVATE_KEY')).update(getApi('TRIPAY_MERCHANT_CODE') + inv + nominal).digest('hex');
                const res = await axios.post('https://tripay.co.id/api-sandbox/transaction/create', { 'method': 'QRIS', 'merchant_ref': inv, 'amount': nominal, 'customer_name': user.nama, 'customer_email': `${nomorHp}@pembeli.com`, 'customer_phone': nomorHp, 'order_items': [{ 'sku': 'DEPO', 'name': 'Deposit Saldo', 'price': nominal, 'quantity': 1 }], 'signature': sig }, { headers: { 'Authorization': 'Bearer ' + getApi('TRIPAY_API_KEY') } });
                await sock.sendMessage(sender, { image: { url: res.data.data.qr_url }, caption: `🧾 *INV:* ${inv}\n✅ Scan QRIS deposit Rp${nominal.toLocaleString('id-ID')}` });
            } catch (e) { await sock.sendMessage(sender, { text: `❌ Gagal memuat QRIS Deposit.\n⚠️ Alasan: ${e.message}` }); }
            sesiPengguna.delete(sender);
        }
    });

    app.post('/callback', async (req, res) => {
        if (crypto.createHmac('sha256', getApi('TRIPAY_PRIVATE_KEY')).update(req.rawBody).digest('hex') !== req.headers['x-callback-signature']) return res.status(400).send('Invalid');
        if (req.body.status === 'PAID') {
            const inv = req.body.merchant_ref; const p = databaseTagihan.get(inv);
            if (p?.jenis === 'DEPOSIT') {
                db.prepare('UPDATE users SET saldo = saldo + ? WHERE nomor_wa = ?').run(p.nominal, p.nomor_wa);
                db.prepare(`UPDATE transaksi SET status = 'Sukses' WHERE invoice = ?`).run(inv);
                await sock.sendMessage(p.sender, { text: `🎉 *DEPOSIT BERHASIL!*\n🧾 INV: ${inv}\nSaldo bertambah Rp${p.nominal.toLocaleString('id-ID')}` });
                try { const noAdmin = getApi('NOMOR_ADMIN'); if (noAdmin) await sock.sendMessage(noAdmin + '@s.whatsapp.net', { text: `💰 *INFO ADMIN: DEPOSIT MASUK*\n\nDari: ${p.nomor_wa}\nNominal: Rp${p.nominal.toLocaleString('id-ID')}\nInvoice: ${inv}` }); } catch(e) {}
            } else if (p?.jenis === 'PPOB') { await prosesKeDigiflazz(p.sender, p.target, p.sku, inv); }
            databaseTagihan.delete(inv);
        } res.json({ success: true });
    });

}
connectToWhatsApp();
