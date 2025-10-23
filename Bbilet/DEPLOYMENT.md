# 🚀 Deployment Talimatları

## GitHub'a Yükleme Adımları

### 1. Git Kurulumu
Git kurulu değilse, önce Git'i kurun:
- Windows: https://git-scm.com/download/win
- macOS: `brew install git`
- Linux: `sudo apt install git`

### 2. GitHub Repository Oluşturma

1. GitHub'a giriş yapın: https://github.com
2. "New Repository" butonuna tıklayın
3. Repository adını `bilet-satin-alma` olarak girin
4. "Public" seçeneğini işaretleyin
5. "Create repository" butonuna tıklayın

### 3. Projeyi GitHub'a Yükleme

Terminal/Command Prompt'ta şu komutları çalıştırın:

```bash
# Git repository'sini başlat
git init

# Tüm dosyaları ekle
git add .

# İlk commit'i yap
git commit -m "Initial commit: Bbilet otobüs bileti satın alma sistemi"

# GitHub repository'sini remote olarak ekle
git remote add origin https://github.com/KULLANICI_ADINIZ/bilet-satin-alma.git

# Ana branch'i main olarak ayarla
git branch -M main

# GitHub'a push yap
git push -u origin main
```

### 4. Docker ile Test Etme

Repository yüklendikten sonra, başka bir bilgisayarda test etmek için:

```bash
# Repository'yi klonla
git clone https://github.com/KULLANICI_ADINIZ/bilet-satin-alma.git
cd bilet-satin-alma

# Docker ile çalıştır
docker-compose up -d

# Veritabanını başlat
docker-compose exec bbilet-app php scripts/migrate.php
docker-compose exec bbilet-app php scripts/reset_database.php

# Uygulamaya eriş
# http://localhost:8080
```

## 🐳 Docker Container Detayları

### Container Özellikleri
- **Base Image**: PHP 8.2-FPM
- **Web Server**: Nginx
- **Database**: SQLite
- **Port**: 8080 (host) → 80 (container)

### Container İçindeki Servisler
- **PHP-FPM**: PHP kodlarını işler
- **Nginx**: Web sunucusu
- **Supervisor**: Servisleri yönetir

### Volume Mounts
- `./storage` → `/var/www/html/storage` (veritabanı)
- `./public` → `/var/www/html/public` (statik dosyalar)

## 📋 Production Checklist

### Güvenlik
- [ ] Varsayılan şifreleri değiştir
- [ ] SSL sertifikası ekle
- [ ] Güvenlik başlıklarını ayarla
- [ ] Rate limiting ekle

### Performans
- [ ] PHP OPcache aktifleştir
- [ ] Nginx gzip sıkıştırması
- [ ] Static dosya cache
- [ ] Database indexleme

### Monitoring
- [ ] Log dosyalarını yapılandır
- [ ] Error monitoring
- [ ] Performance monitoring
- [ ] Backup sistemi

## 🔧 Environment Variables

Production için aşağıdaki environment değişkenlerini ayarlayın:

```env
PHP_ENV=production
DB_PATH=/var/www/html/storage/database.sqlite
SESSION_NAME=bbilet_session
APP_NAME=Bbilet
```

## 📞 Destek

Herhangi bir sorun yaşarsanız:
1. GitHub Issues bölümünü kontrol edin
2. Yeni issue açın
3. Detaylı hata mesajlarını paylaşın
