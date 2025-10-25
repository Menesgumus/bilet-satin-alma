# 🚌 Bbilet - Otobüs Bileti Satın Alma Sistemi

Modern, kullanıcı dostu otobüs bileti satın alma platformu. PHP, SQLite ve Docker teknolojileri kullanılarak geliştirilmiştir.

## 🚀 Özellikler

### 👥 Kullanıcı Yönetimi
- **3 Farklı Rol**: Admin, Firma Admin, Normal Kullanıcı
- **Güvenli Giriş**: Şifre hashleme ile güvenli kimlik doğrulama
- **Profil Yönetimi**: Kullanıcı bilgileri ve bakiye yönetimi
- **Kayıt Sistemi**: Yeni kullanıcı kaydı

### 🏢 Firma Yönetimi
- **Firma Paneli**: Otobüs firmaları için özel yönetim paneli
- **Sefer Yönetimi**: Sefer oluşturma, düzenleme ve silme
- **Kupon Sistemi**: Firma özel indirim kuponları
- **Raporlama**: Satış raporları ve istatistikler

### 🎫 Bilet Sistemi
- **Sefer Arama**: Kalkış-varış şehir bazlı arama
- **Koltuk Seçimi**: İnteraktif koltuk seçim sistemi (2+2 ve 2+1 düzenleri)
- **Kupon Uygulama**: İndirim kuponu kullanımı
- **Bilet İptal**: 1 saat kuralı ile bilet iptali
- **PDF Bilet**: Otomatik bilet oluşturma
- **Bilet Arama**: Bilet numarası ile bilet sorgulama

### 🛡️ Admin Paneli
- **Kullanıcı Yönetimi**: Tüm kullanıcıları yönetme
- **Firma Yönetimi**: Otobüs firmalarını yönetme
- **Kupon Yönetimi**: Sistem geneli kupon oluşturma
- **Firma Admin Atama**: Firma yöneticilerini atama

## 🛠️ Teknolojiler

- **Backend**: PHP 8.2
- **Veritabanı**: SQLite
- **Frontend**: HTML5, CSS3, JavaScript
- **PDF**: Dompdf
- **Container**: Docker & Docker Compose
- **Web Server**: Nginx + PHP-FPM

## 📦 Kurulum

### 🐳 Docker ile Çalıştırma (Önerilen)

1. **Repository'yi klonlayın:**
```bash
git clone https://github.com/KULLANICI_ADINIZ/bilet-satin-alma.git
cd bilet-satin-alma
```

2. **Docker Compose ile başlatın:**
```bash
docker-compose up -d
```

3. **Veritabanını başlatın:**
```bash
docker exec bbilet-liet-satinalma php scripts/migrate.php
docker exec bbilet-liet-satinalma php scripts/seed.php
```

4. **Uygulamaya erişin:**
```
http://localhost:8080
```

### 🚀 Hızlı Başlangıç (PHP Built-in Server)

1. **Gereksinimler:**
- PHP 8.2+
- SQLite
- Composer

2. **Kurulum adımları:**
```bash
# Bağımlılıkları yükleyin
composer install

# Veritabanını oluşturun
php scripts/migrate.php

# Örnek verileri yükleyin
php scripts/seed.php

# Web sunucusunu başlatın
php -S localhost:8000 -t public
```

3. **Uygulamaya erişin:**
```
http://localhost:8000
```

### Docker ile Çalıştırma

1. **Docker Compose ile başlatın:**
```bash
docker-compose up -d
```

2. **Veritabanını başlatın:**
```bash
docker-compose exec bbilet-app php scripts/migrate.php
docker-compose exec bbilet-app php scripts/seed.php
```

3. **Uygulamaya erişin:**
```
http://localhost:8080
```

## 👤 Varsayılan Kullanıcılar

Sistem seed edildiğinde aşağıdaki kullanıcılar oluşturulur:

### 🔐 Sistem Yöneticisi
- **E-posta:** `admin@admin`
- **Şifre:** `admin`
- **Rol:** Admin
- **Yetkiler:** Tüm sistemi yönetme

### 🏢 Firma Yöneticileri
| E-posta | Şifre | Firma | Rol |
|---------|-------|-------|-----|
| firma1@firma | firma | HızlıTur | Firma Admin |
| firma2@firma | firma | Güvenli Yolculuk | Firma Admin |
| firma3@firma | firma | Mega Turzim | Firma Admin |
| firma4@firma | firma | Anadolu Express | Firma Admin |
| firma5@firma | firma | Şehirler Arası | Firma Admin |

### 👤 Normal Kullanıcılar
| E-posta | Şifre | Ad Soyad | Bakiye |
|---------|-------|----------|--------|
| user@user | user | Deneme Kullanıcı | 1000₺ |
| ahmet@test | user | Ahmet Yılmaz | 750₺ |
| fatma@test | user | Fatma Demir | 500₺ |
| mehmet@test | user | Mehmet Kaya | 300₺ |
| ayse@test | user | Ayşe Özkan | 1200₺ |

## 🎟️ Mevcut Kuponlar

| Kupon Kodu | İndirim | Kullanım Limiti | Kapsam |
|------------|---------|-----------------|--------|
| WELCOME10 | %10 | 100 | Genel |
| HIZLI20 | %20 | 100 | HızlıTur |
| GUVEN15 | %15 | 100 | Güvenli Yolculuk |
| MEGA25 | %25 | 100 | Mega Truzim |
| ANADOLU12 | %12 | 100 | Anadolu Express |
| SEHİR5 | %05 | 100 | Şehirler Arası |

## 🚌 Mevcut Seferler

Sistem 5 farklı firma için toplam 21 sefer içerir:

### Has Siber
- İstanbul → Ankara (08:00, 14:00)
- Ankara → İzmir (09:00)
- İzmir → İstanbul (07:30)
- İstanbul → Antalya (10:00)

### Siber Express
- İstanbul → Bursa (09:30)
- Bursa → İzmir (08:00)
- İzmir → Ankara (10:30)
- Ankara → İstanbul (07:00)

### Yeni Siber
- İstanbul → Trabzon (20:00)
- Trabzon → İstanbul (20:00)
- İstanbul → Erzurum (18:00)
- Erzurum → İstanbul (18:00)

### Zart Turzim
- İstanbul → Konya (11:00)
- Konya → İstanbul (12:00)
- İstanbul → Sivas (13:00)
- Sivas → İstanbul (14:00)

### Zort Turizm
- İstanbul → Çanakkale (15:00)
- Çanakkale → İstanbul (16:00)
- İstanbul → Edirne (17:00)
- Edirne → İstanbul (18:00)

## 🎯 Kullanım Kılavuzu

### Normal Kullanıcı
1. **Giriş:** `user@user` / `user` ile giriş yapın
2. **Sefer Arama:** Ana sayfadan kalkış-varış şehir seçin
3. **Koltuk Seçimi:** İstediğiniz koltuk numarasını seçin
4. **Kupon Uygulama:** Varsa kupon kodunuzu girin
5. **Bilet Satın Alma:** "Satın Al" butonuna tıklayın
6. **Bilet Görüntüleme:** Profil sayfasından biletlerinizi görüntüleyin

### Firma Yöneticisi
1. **Giriş:** `has@firma` / `firma` ile giriş yapın (Has Siber)
2. **Sefer Oluşturma:** Firma panelinden yeni sefer ekleyin
3. **Kupon Yönetimi:** Firma özel kuponlarınızı oluşturun
4. **Raporlama:** Satış raporlarınızı inceleyin

### Sistem Yöneticisi
1. **Giriş:** `admin@admin` / `admin` ile giriş yapın
2. **Kullanıcı Yönetimi:** Admin panelinden kullanıcıları yönetin
3. **Firma Yönetimi:** Firmaları ve firma adminlerini yönetin
4. **Kupon Yönetimi:** Sistem geneli kuponları oluşturun

## 📁 Proje Yapısı

```
Bbilet/
├── public/                 # Web erişilebilir dosyalar
│   ├── admin/             # Admin paneli
│   │   ├── index.php      # Admin ana sayfa
│   │   ├── users.php      # Kullanıcı yönetimi
│   │   ├── companies.php  # Firma yönetimi
│   │   ├── coupons.php    # Kupon yönetimi
│   │   └── assign_firma_admin.php # Firma admin atama
│   ├── company/           # Firma paneli
│   │   ├── index.php      # Firma ana sayfa
│   │   ├── trips.php      # Sefer yönetimi
│   │   ├── trip_new.php   # Yeni sefer
│   │   ├── trip_edit.php  # Sefer düzenleme
│   │   ├── coupons.php    # Kupon yönetimi
│   │   └── reports.php    # Raporlar
│   ├── api/               # API endpoints
│   │   └── get_cities.php # Şehir listesi API
│   ├── index.php          # Ana sayfa
│   ├── login.php          # Giriş sayfası
│   ├── register.php       # Kayıt sayfası
│   ├── purchase.php       # Bilet satın alma
│   ├── tickets.php        # Biletlerim
│   ├── ticket_lookup.php  # Bilet sorgulama
│   ├── ticket_cancel.php  # Bilet iptal
│   ├── ticket_pdf.php     # PDF bilet
│   ├── coupons.php        # Kupon listesi
│   └── style.css          # CSS stilleri
├── src/                   # Kaynak kodlar
│   ├── Auth.php           # Kimlik doğrulama
│   ├── Database.php       # Veritabanı bağlantısı
│   └── Util.php           # Yardımcı fonksiyonlar
├── scripts/               # Veritabanı scriptleri
│   ├── migrate.php        # Veritabanı migration
│   ├── reset_database.php # Veritabanı temizleme
│   └── seed.php           # Test verileri ekleme
├── storage/               # Veritabanı dosyası
│   └── database.sqlite    # SQLite veritabanı
├── docker/                # Docker konfigürasyonları
│   ├── nginx.conf         # Nginx konfigürasyonu
│   └── supervisord.conf   # Supervisor konfigürasyonu
├── config/                # Uygulama konfigürasyonu
│   └── config.php         # Ana konfigürasyon
├── docker-compose.yml     # Docker Compose dosyası
├── Dockerfile             # Docker image dosyası
├── composer.json          # PHP bağımlılıkları
└── README.md              # Bu dosya
```

## 🔧 Geliştirme

### Veritabanı Sıfırlama
```bash
# Veritabanını tamamen temizle
php scripts/reset_database.php

# Yeni verilerle doldur
php scripts/seed.php
```

### Yeni Kullanıcı Ekleme
Sistem admin panelinden veya `scripts/seed.php` dosyasını düzenleyerek yeni kullanıcılar ekleyebilirsiniz.

### Yeni Sefer Ekleme
Firma admin panelinden veya `scripts/seed.php` dosyasındaki `$tripData` dizisini düzenleyerek yeni seferler ekleyebilirsiniz.

## 🐛 Bilinen Sorunlar

- Firma admin panelinde sefer listesinde koltuk sayısı gösteriminde tip hatası düzeltildi
- Sistem PHP 8.2+ gerektirir

## 📞 Destek

Herhangi bir sorun yaşarsanız:
1. README dosyasını kontrol edin
2. Veritabanını sıfırlayıp yeniden seed edin
3. PHP hata loglarını kontrol edin

## 📄 Lisans


Bu proje eğitim amaçlı geliştirilmiştir.
