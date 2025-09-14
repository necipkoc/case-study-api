-- =====================================================
-- E-COMMERCE DATABASE - FULL DUMP
-- =====================================================
-- Generated: 2024-09-14
-- Database: ecommerce (PostgreSQL)
-- Content: Complete database structure + sample data
-- Tables: 16 tables with real e-commerce data
--
-- Usage:
-- psql -U username -d database_name -f ecommerce_full_dump.sql
-- =====================================================

-- Disable foreign key checks during import
SET session_replication_role = replica;

-- =====================================================
-- DATABASE STRUCTURE (DDL)
-- =====================================================

-- Drop tables in correct order (reverse dependency)
DROP TABLE IF EXISTS public.order_items CASCADE;
DROP TABLE IF EXISTS public.cart_items CASCADE;
DROP TABLE IF EXISTS public.orders CASCADE;
DROP TABLE IF EXISTS public.carts CASCADE;
DROP TABLE IF EXISTS public.products CASCADE;
DROP TABLE IF EXISTS public.categories CASCADE;
DROP TABLE IF EXISTS public.users CASCADE;
DROP TABLE IF EXISTS public.sessions CASCADE;
DROP TABLE IF EXISTS public.personal_access_tokens CASCADE;
DROP TABLE IF EXISTS public.password_reset_tokens CASCADE;
DROP TABLE IF EXISTS public.migrations CASCADE;
DROP TABLE IF EXISTS public.jobs CASCADE;
DROP TABLE IF EXISTS public.job_batches CASCADE;
DROP TABLE IF EXISTS public.failed_jobs CASCADE;
DROP TABLE IF EXISTS public.cache_locks CASCADE;
DROP TABLE IF EXISTS public."cache" CASCADE;

-- Create tables in correct order

-- Cache table
CREATE TABLE public."cache" (
                                "key" varchar(255) NOT NULL,
                                value text NOT NULL,
                                expiration int4 NOT NULL,
                                CONSTRAINT cache_pkey PRIMARY KEY (key)
);

-- Cache locks table
CREATE TABLE public.cache_locks (
                                    "key" varchar(255) NOT NULL,
                                    "owner" varchar(255) NOT NULL,
                                    expiration int4 NOT NULL,
                                    CONSTRAINT cache_locks_pkey PRIMARY KEY (key)
);

-- Failed jobs table
CREATE TABLE public.failed_jobs (
                                    id bigserial NOT NULL,
                                    "uuid" varchar(255) NOT NULL,
                                    "connection" text NOT NULL,
                                    queue text NOT NULL,
                                    payload text NOT NULL,
                                    "exception" text NOT NULL,
                                    failed_at timestamp(0) DEFAULT CURRENT_TIMESTAMP NOT NULL,
                                    CONSTRAINT failed_jobs_pkey PRIMARY KEY (id),
                                    CONSTRAINT failed_jobs_uuid_unique UNIQUE (uuid)
);

-- Job batches table
CREATE TABLE public.job_batches (
                                    id varchar(255) NOT NULL,
                                    "name" varchar(255) NOT NULL,
                                    total_jobs int4 NOT NULL,
                                    pending_jobs int4 NOT NULL,
                                    failed_jobs int4 NOT NULL,
                                    failed_job_ids text NOT NULL,
                                    "options" text NULL,
                                    cancelled_at int4 NULL,
                                    created_at int4 NOT NULL,
                                    finished_at int4 NULL,
                                    CONSTRAINT job_batches_pkey PRIMARY KEY (id)
);

-- Jobs table
CREATE TABLE public.jobs (
                             id bigserial NOT NULL,
                             queue varchar(255) NOT NULL,
                             payload text NOT NULL,
                             attempts int2 NOT NULL,
                             reserved_at int4 NULL,
                             available_at int4 NOT NULL,
                             created_at int4 NOT NULL,
                             CONSTRAINT jobs_pkey PRIMARY KEY (id)
);
CREATE INDEX jobs_queue_index ON public.jobs USING btree (queue);

-- Migrations table
CREATE TABLE public.migrations (
                                   id serial4 NOT NULL,
                                   migration varchar(255) NOT NULL,
                                   batch int4 NOT NULL,
                                   CONSTRAINT migrations_pkey PRIMARY KEY (id)
);

-- Password reset tokens table
CREATE TABLE public.password_reset_tokens (
                                              email varchar(255) NOT NULL,
                                              "token" varchar(255) NOT NULL,
                                              created_at timestamp(0) NULL,
                                              CONSTRAINT password_reset_tokens_pkey PRIMARY KEY (email)
);

-- Personal access tokens table
CREATE TABLE public.personal_access_tokens (
                                               id bigserial NOT NULL,
                                               tokenable_type varchar(255) NOT NULL,
                                               tokenable_id int8 NOT NULL,
                                               "name" text NOT NULL,
                                               "token" varchar(64) NOT NULL,
                                               abilities text NULL,
                                               last_used_at timestamp(0) NULL,
                                               expires_at timestamp(0) NULL,
                                               created_at timestamp(0) NULL,
                                               updated_at timestamp(0) NULL,
                                               CONSTRAINT personal_access_tokens_pkey PRIMARY KEY (id),
                                               CONSTRAINT personal_access_tokens_token_unique UNIQUE (token)
);
CREATE INDEX personal_access_tokens_expires_at_index ON public.personal_access_tokens USING btree (expires_at);
CREATE INDEX personal_access_tokens_tokenable_type_tokenable_id_index ON public.personal_access_tokens USING btree (tokenable_type, tokenable_id);

-- Sessions table
CREATE TABLE public.sessions (
                                 id varchar(255) NOT NULL,
                                 user_id int8 NULL,
                                 ip_address varchar(45) NULL,
                                 user_agent text NULL,
                                 payload text NOT NULL,
                                 last_activity int4 NOT NULL,
                                 CONSTRAINT sessions_pkey PRIMARY KEY (id)
);
CREATE INDEX sessions_last_activity_index ON public.sessions USING btree (last_activity);
CREATE INDEX sessions_user_id_index ON public.sessions USING btree (user_id);

-- Users table (base table)
CREATE TABLE public.users (
                              id bigserial NOT NULL,
                              "name" varchar(255) NOT NULL,
                              email varchar(255) NOT NULL,
                              email_verified_at timestamp(0) NULL,
                              "password" varchar(255) NOT NULL,
                              remember_token varchar(100) NULL,
                              created_at timestamp(0) NULL,
                              updated_at timestamp(0) NULL,
                              "role" varchar(255) DEFAULT 'user'::character varying NOT NULL,
                              CONSTRAINT users_email_unique UNIQUE (email),
                              CONSTRAINT users_pkey PRIMARY KEY (id),
                              CONSTRAINT users_role_check CHECK (((role)::text = ANY ((ARRAY['admin'::character varying, 'user'::character varying])::text[])))
);

-- Categories table
CREATE TABLE public.categories (
                                   id bigserial NOT NULL,
                                   "name" varchar(255) NOT NULL,
                                   description text NULL,
                                   created_at timestamp(0) NULL,
                                   updated_at timestamp(0) NULL,
                                   CONSTRAINT categories_name_unique UNIQUE (name),
                                   CONSTRAINT categories_pkey PRIMARY KEY (id)
);

-- Products table (depends on categories)
CREATE TABLE public.products (
                                 id bigserial NOT NULL,
                                 "name" varchar(255) NOT NULL,
                                 description text NULL,
                                 price numeric(10, 2) NOT NULL,
                                 stock_quantity int4 DEFAULT 0 NOT NULL,
                                 category_id int8 NOT NULL,
                                 created_at timestamp(0) NULL,
                                 updated_at timestamp(0) NULL,
                                 CONSTRAINT products_pkey PRIMARY KEY (id),
                                 CONSTRAINT products_category_id_foreign FOREIGN KEY (category_id) REFERENCES public.categories(id) ON DELETE CASCADE
);
CREATE INDEX products_category_id_index ON public.products USING btree (category_id);
CREATE INDEX products_name_index ON public.products USING btree (name);
CREATE INDEX products_price_index ON public.products USING btree (price);

-- Carts table (depends on users)
CREATE TABLE public.carts (
                              id bigserial NOT NULL,
                              user_id int8 NOT NULL,
                              created_at timestamp(0) NULL,
                              updated_at timestamp(0) NULL,
                              CONSTRAINT carts_pkey PRIMARY KEY (id),
                              CONSTRAINT carts_user_id_unique UNIQUE (user_id),
                              CONSTRAINT carts_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE
);

-- Orders table (depends on users)
CREATE TABLE public.orders (
                               id bigserial NOT NULL,
                               user_id int8 NOT NULL,
                               total_amount numeric(10, 2) NOT NULL,
                               status varchar(255) DEFAULT 'pending'::character varying NOT NULL,
                               created_at timestamp(0) NULL,
                               updated_at timestamp(0) NULL,
                               CONSTRAINT orders_pkey PRIMARY KEY (id),
                               CONSTRAINT orders_status_check CHECK (((status)::text = ANY ((ARRAY['pending'::character varying, 'confirmed'::character varying, 'shipped'::character varying, 'delivered'::character varying, 'cancelled'::character varying])::text[]))),
    CONSTRAINT orders_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE
);
CREATE INDEX orders_created_at_index ON public.orders USING btree (created_at);
CREATE INDEX orders_status_index ON public.orders USING btree (status);
CREATE INDEX orders_user_id_index ON public.orders USING btree (user_id);

-- Cart items table (depends on carts and products)
CREATE TABLE public.cart_items (
                                   id bigserial NOT NULL,
                                   cart_id int8 NOT NULL,
                                   product_id int8 NOT NULL,
                                   quantity int4 DEFAULT 1 NOT NULL,
                                   created_at timestamp(0) NULL,
                                   updated_at timestamp(0) NULL,
                                   CONSTRAINT cart_items_cart_id_product_id_unique UNIQUE (cart_id, product_id),
                                   CONSTRAINT cart_items_pkey PRIMARY KEY (id),
                                   CONSTRAINT cart_items_cart_id_foreign FOREIGN KEY (cart_id) REFERENCES public.carts(id) ON DELETE CASCADE,
                                   CONSTRAINT cart_items_product_id_foreign FOREIGN KEY (product_id) REFERENCES public.products(id) ON DELETE CASCADE
);
CREATE INDEX cart_items_cart_id_index ON public.cart_items USING btree (cart_id);

-- Order items table (depends on orders and products)
CREATE TABLE public.order_items (
                                    id bigserial NOT NULL,
                                    order_id int8 NOT NULL,
                                    product_id int8 NOT NULL,
                                    quantity int4 NOT NULL,
                                    price numeric(10, 2) NOT NULL,
                                    created_at timestamp(0) NULL,
                                    updated_at timestamp(0) NULL,
                                    CONSTRAINT order_items_pkey PRIMARY KEY (id),
                                    CONSTRAINT order_items_order_id_foreign FOREIGN KEY (order_id) REFERENCES public.orders(id) ON DELETE CASCADE,
                                    CONSTRAINT order_items_product_id_foreign FOREIGN KEY (product_id) REFERENCES public.products(id) ON DELETE CASCADE
);
CREATE INDEX order_items_order_id_index ON public.order_items USING btree (order_id);
CREATE INDEX order_items_product_id_index ON public.order_items USING btree (product_id);

-- =====================================================
-- DATABASE DATA (DML)
-- =====================================================

-- Insert cache data
INSERT INTO public."cache" ("key", value, expiration) VALUES
                                                          ('laravel-cache-DUrBguEW1cUv6I6l', 's:7:"forever";', 2073223831),
                                                          ('laravel-cache-ca3mm49pt01uontf', 's:7:"forever";', 2073229928);

-- Insert migration history
INSERT INTO public.migrations (id, migration, batch) VALUES
                                                         (1, '0001_01_01_000000_create_users_table', 1),
                                                         (2, '0001_01_01_000001_create_cache_table', 1),
                                                         (3, '0001_01_01_000002_create_jobs_table', 1),
                                                         (4, '2025_09_14_132330_create_personal_access_tokens_table', 2),
                                                         (5, '2025_09_14_132529_add_role_to_users_table', 3),
                                                         (6, '2025_09_14_165038_create_categories_table', 4),
                                                         (7, '2025_09_14_173214_create_products_table', 5),
                                                         (8, '2025_09_14_181637_create_carts_table', 6),
                                                         (9, '2025_09_14_181657_create_cart_items_table', 6),
                                                         (10, '2025_09_14_184121_create_orders_table', 7),
                                                         (11, '2025_09_14_184316_create_order_items_table', 7);

-- Insert users (base data)
INSERT INTO public.users (id, "name", email, email_verified_at, "password", remember_token, created_at, updated_at, "role") VALUES
                                                                                                                                (1, 'Necip Koç', 'test@example.com', NULL, '$2y$12$q16nIjpbWikC71MG9rTPPOfPdrimli/4NJgl4mEAxU0Bk1c38zQHa', NULL, '2025-09-14 13:29:19', '2025-09-14 14:50:34', 'user'),
                                                                                                                                (2, 'Test User', 'user@test.com', NULL, '$2y$12$qe/EdrWDZGfj6gM7bLirKOh6j.poVsg3ifHHcwTrZeOCBB.0JjaXu', NULL, '2025-09-14 13:37:24', '2025-09-14 16:47:21', 'user'),
                                                                                                                                (3, 'Test Admin', 'admin@test.com', NULL, '$2y$12$fbKHKJ/J4KxLH1GYyGMG.uCQHFRRblOF6WjNC.j2SSZJ8i4JVGXLq', NULL, '2025-09-14 16:55:03', '2025-09-14 17:10:29', 'admin'),
                                                                                                                                (4, 'Test Kullanıcı', 'kullanici@test.com', NULL, '$2y$12$HEn0Kk3gCUeZe1AuRZxCk.qTDJJB6MsEG6NUnEeY2GQg1v6DKo/0e', NULL, '2025-09-14 19:08:46', '2025-09-14 19:08:46', 'user');

-- Insert categories
INSERT INTO public.categories (id, "name", description, created_at, updated_at) VALUES
                                                                                    (1, 'Elektronik Ürünler', 'Güncellenmiş elektronik ürünler kategorisi', '2025-09-14 16:57:23', '2025-09-14 17:11:10'),
                                                                                    (2, 'Ev ve Yaşam', 'Ev ve dekorasyona ait her şey', '2025-09-14 16:58:16', '2025-09-14 16:58:16'),
                                                                                    (3, 'Cep Telefonları', 'Tüm marka ve model cep telefonları', '2025-09-14 16:58:34', '2025-09-14 16:58:34');

-- Insert products
INSERT INTO public.products (id, "name", description, price, stock_quantity, category_id, created_at, updated_at) VALUES
                                                                                                                      (1, 'iPhone 17', 'Apple iPhone 17 128GB', 29999.99, 23, 3, '2025-09-14 17:40:00', '2025-09-14 18:46:35'),
                                                                                                                      (2, 'Galaxy S23', 'Samsung Galaxy S23 128GB', 49999.99, 10, 3, '2025-09-14 17:41:10', '2025-09-14 17:41:10'),
                                                                                                                      (3, 'X7 Pro', 'Poco X7 Pro', 19999.99, 24, 3, '2025-09-14 17:42:26', '2025-09-14 18:46:35'),
                                                                                                                      (4, 'Pixel 10 Pro XL', 'Google Pixel 10 Pro XL', 35000.00, 5, 3, '2025-09-14 17:43:14', '2025-09-14 17:43:14'),
                                                                                                                      (5, 'Xperia V1', 'Sony Xperia V1', 105000.00, 90, 3, '2025-09-14 17:45:12', '2025-09-14 17:45:12'),
                                                                                                                      (6, 'Lenovo LOQ 83GS00P5TR', 'Intel Core i5 12600HX 24GB 512GB SSD RTX3050 Freedos 15.6'''' IPS Taşınabilir Bilgisayar', 29998.99, 30, 1, '2025-09-14 17:46:52', '2025-09-14 17:46:52'),
                                                                                                                      (7, 'Epson L3266', 'Wi-Fi + Tarayıcı + Fotokopi Renkli Çok Fonksiyonlu Tanklı Mürekkep Püskürtmeli Yazıcı', 7999.00, 92, 1, '2025-09-14 17:48:14', '2025-09-14 17:48:14'),
                                                                                                                      (8, 'Bosch KDN55NWE0N', '453 Lt No-Frost Buzdolabı', 27256.06, 65, 1, '2025-09-14 17:48:51', '2025-09-14 17:48:51'),
                                                                                                                      (9, 'Roborock Q8 Max Pro', 'Akıllı Robot Süpürge', 17999.00, 25, 1, '2025-09-14 17:55:05', '2025-09-14 17:55:05'),
                                                                                                                      (10, 'GoPro HERO12 Black', 'Aksiyon Kamera', 183590.00, 55, 1, '2025-09-14 17:55:31', '2025-09-14 17:55:31'),
                                                                                                                      (11, 'Yükseklik Ayarlı Çalışma Masası', 'Ceviz (Tekerli) 80X40', 899.00, 20, 2, '2025-09-14 18:00:39', '2025-09-14 18:00:39'),
                                                                                                                      (12, '4 Katlı Galvaniz Çelik Raf', '1.00 MM-150CM', 1837.45, 50, 2, '2025-09-14 18:01:52', '2025-09-14 18:01:52'),
                                                                                                                      (13, 'Hawk Gaming Chair Fab V4', 'Oyuncu Koltuğu', 7499.00, 87, 2, '2025-09-14 18:02:22', '2025-09-14 18:02:22'),
                                                                                                                      (14, 'Philips Filtre Kahve Makinesi HD7459/20', '1,2 Lt Kapasite, Otomatik Kapanma, Cam Demlikli, Kolay Doldurma, Zamanlayıcı', 2499.00, 87, 2, '2025-09-14 18:02:57', '2025-09-14 18:02:57'),
                                                                                                                      (15, '4 Çekmeceli Mdf Şifonyer', 'Comiet Soho', 8998.95, 17, 2, '2025-09-14 18:03:58', '2025-09-14 18:03:58');

-- Insert carts
INSERT INTO public.carts (id, user_id, created_at, updated_at) VALUES
    (1, 2, '2025-09-14 18:23:15', '2025-09-14 18:23:15');

-- Insert orders
INSERT INTO public.orders (id, user_id, total_amount, status, created_at, updated_at) VALUES
    (1, 2, 829999.72, 'pending', '2025-09-14 18:46:35', '2025-09-14 18:46:35');

-- Insert order items
INSERT INTO public.order_items (id, order_id, product_id, quantity, price, created_at, updated_at) VALUES
                                                                                                       (1, 1, 1, 27, 29999.99, '2025-09-14 18:46:35', '2025-09-14 18:46:35'),
                                                                                                       (2, 1, 3, 1, 19999.99, '2025-09-14 18:46:35', '2025-09-14 18:46:35');

-- Insert sessions
INSERT INTO public.sessions (id, user_id, ip_address, user_agent, payload, last_activity) VALUES
    ('polhJtcsJlpcofnnOEXWfTBXUQ0wksqEQ1090V5q', NULL, '192.168.65.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiOGlzeFhrVk91aVc2TDRlRnJrRURkdDBaVjQzY0taVUdmRkE2b2ZxTSI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6MTk6Imh0dHA6Ly8wLjAuMC4wOjgwMDAiO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1757857444);

-- Update sequences to correct values
SELECT setval('users_id_seq', (SELECT MAX(id) FROM users));
SELECT setval('categories_id_seq', (SELECT MAX(id) FROM categories));
SELECT setval('products_id_seq', (SELECT MAX(id) FROM products));
SELECT setval('carts_id_seq', (SELECT MAX(id) FROM carts));
SELECT setval('orders_id_seq', (SELECT MAX(id) FROM orders));
SELECT setval('order_items_id_seq', (SELECT MAX(id) FROM order_items));
SELECT setval('migrations_id_seq', (SELECT MAX(id) FROM migrations));

-- Re-enable foreign key checks
SET session_replication_role = DEFAULT;
