# E-Commerce API

Laravel tabanlı modern E-ticaret REST API'si. Kullanıcı yönetimi, ürün kataloğu, sepet ve sipariş sistemi ile eksiksiz bir e-ticaret deneyimi sunar.

## Özellikler

- **Kimlik Doğrulama**: JWT tabanlı güvenli authentication sistemi
- **Kategori Yönetimi**: Hiyerarşik kategori yapısı
- **Ürün Yönetimi**: Detaylı ürün bilgileri ve stok takibi
- **Sepet Sistemi**: Gerçek zamanlı sepet yönetimi
- **Sipariş Yönetimi**: Otomatik stok düşürme ile sipariş sistemi
- **Validation**: Kapsamlı veri doğrulama
- **Error Handling**: Detaylı hata yönetimi
- **Pagination**: Sayfalama desteği

## Gereksinimler

- PHP 8.1+
- Composer
- Docker & Docker Compose
- PostgreSQL 15

## 🛠️ Kurulum

### 1. Projeyi İndirin
```bash  
git clone https://github.com/necipkoc/case-study-api
cd ecommerce-api
```

### 2. Environment Ayarları
```bash  
cp .env.example .env
```

**.env dosyasını düzenleyin:**
```bash  
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=ecommerce
DB_USERNAME=user
DB_PASSWORD=password

JWT_SECRET=your_jwt_secret_key
```
### 3. Docker ile Başlatın
```bash  
# Container'ları başlat
docker-compose up -d

# Bağımlılıkları yükle
docker-compose exec app composer install

# Application key oluştur
docker-compose exec app php artisan key:generate

# JWT secret oluştur
docker-compose exec app php artisan jwt:secret

# Veritabanını oluştur
docker-compose exec app php artisan migrate
```

### 4. Test Edin
```bash  
curl http://localhost:8000/api/categories
```

## API Dökümantasyonu

### Base URL
```bash  
http://localhost:8000/api
```

### Response Format
**Tüm API yanıtları aynı formatta döner:**
```bash  
{
    "success": true,
    "message": "İşlem başarılı",
    "data": {}
}
```

## Kimlik Doğrulama

### Kayıt Ol
```bash  
POST /api/register
Content-Type: application/json

{
    "name": "Turk Ticaret",
    "email": "turk@ticaret.com",
    "password": "password123",
    "password_confirmation": "password123"
}
```

### Giriş Yap
```bash  
POST /api/login
Content-Type: application/json

{
    "email": "turk@ticaret.com",
    "password": "password123"
}
```

**Response:**
```bash
{
    "success": true,
    "message": "Giriş başarılı",
    "data": {
        "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
        "user": {
            "id": 1,
            "name": "Turk Ticaret",
            "email": "turk@ticaret.com",
            "email_verified_at": null,
            "created_at": "2025-09-14T16:55:03.000000Z",
            "updated_at": "2025-09-14T17:10:29.000000Z",
            "role": "user"
        }
    }
}
```

### Profil Bilgisi
```bash  
GET /api/profile
Authorization: Bearer YOUR_TOKEN
```
### Çıkış Yap
```bash  
POST /api/logout
Authorization: Bearer YOUR_TOKEN
```

## Kategoriler

### Tüm Kategorileri Listele
```bash  
GET /api/categories
```

### Kategori Oluştur (Admin)
```bash  
POST /api/categories
Authorization: Bearer YOUR_TOKEN
Content-Type: application/json

{
    "name": "Elektronik",
    "description": "Elektronik ürünler kategorisi"
}
```

### Kategori Güncelle (Admin)
```bash  
PUT /api/categories/{id}
Authorization: Bearer YOUR_TOKEN
Content-Type: application/json

{
    "name": "Elektronik Güncel",
    "description": "Güncellenmiş açıklama"
}
```

### Kategori Sil (Admin)
```bash  
DELETE /api/categories/{id}
Authorization: Bearer YOUR_TOKEN
```

## Ürünler

### Tüm Ürünleri Listele
```bash  
GET /api/products
GET /api/products?category_id=1&search=laptop&page=2
```

### Ürün Oluştur (Admin)
```bash  
POST /api/products
Authorization: Bearer YOUR_TOKEN
Content-Type: application/json

{
    "name": "MacBook Pro",
    "description": "Apple MacBook Pro 13 inch",
    "price": 25999.99,
    "stock_quantity": 10,
    "category_id": 1
}
```

### Ürün Güncelle (Admin)
```bash  
PUT /api/products/{id}
Authorization: Bearer YOUR_TOKEN
Content-Type: application/json

{
    "name": "MacBook Pro M2",
    "price": 27999.99,
    "stock_quantity": 15
}
```

### Ürün Sil (Admin)
```bash  
DELETE /api/products/{id}
Authorization: Bearer YOUR_TOKEN
```

## Sepet

### Sepeti Görüntüle
```bash
GET /api/cart
Authorization: Bearer YOUR_TOKEN
```

### Sepeti Ürün Miktarı Güncelle
```bash
PUT /api/cart/update
Authorization: Bearer YOUR_TOKEN
Content-Type: application/json

{
    "product_id": 1,
    "quantity": 5
}
```

### Sepetten Ürün Çıkar
```bash
DELETE /api/cart/remove/{product_id}
Authorization: Bearer YOUR_TOKEN
```

### Sepeti Temizle
```bash
DELETE /api/cart/clear
Authorization: Bearer YOUR_TOKEN
```

## Siparişler

### Sipariş Oluştur

```bash
POST /api/orders
Authorization: Bearer YOUR_TOKEN
```
*Not: Sipariş sepetteki ürünlerden otomatik oluşturulur.*

### Siparişleri Listele

```bash
GET /api/orders
GET /api/orders?status=pending
Authorization: Bearer YOUR_TOKEN
```

### Sipariş Detayı
```bash
GET /api/orders/{id}
Authorization: Bearer YOUR_TOKEN
```

### Sipariş İstatistikleri
```bash
GET /api/orders-stats
Authorization: Bearer YOUR_TOKEN
```

## Veritabanı

### Tablolar

```bash
users
id, name, email, password, created_at, updated_at

categories
id, name, description, created_at, updated_at

products
id, category_id, name, description, price, stock_quantity, created_at, updated_at

carts
id, user_id, created_at, updated_at

cart_items
id, cart_id, product_id, quantity, created_at, updated_at

orders
id, user_id, total_amount, status, created_at, updated_at

order_items
id, order_id, product_id, quantity, price, created_at, updated_at
```
