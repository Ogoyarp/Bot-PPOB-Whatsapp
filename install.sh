#!/bin/bash
echo "🚀 MEMULAI INSTALASI BOT PPOB & WEB ADMIN PANEL..."
apt update && apt upgrade -y
apt install -y curl wget git unzip sqlite3 apache2 php libapache2-mod-php php-sqlite3
systemctl enable apache2 && systemctl start apache2
curl -fsSL https://deb.nodesource.com/setup_18.x | bash -
apt install -y nodejs
npm install -g pm2
mkdir -p /var/www/html/panel
cp panel/admin.php /var/www/html/panel/
chmod -R 777 /var/www/html/panel
cd bot && npm install
echo "✅ INSTALASI SELESAI!"
