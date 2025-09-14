INSERT INTO public."cache" ("key",value,expiration) VALUES
                                                        ('laravel-cache-DUrBguEW1cUv6I6l','s:7:"forever";',2073223831),
                                                        ('laravel-cache-ca3mm49pt01uontf','s:7:"forever";',2073229928);
INSERT INTO public.carts (id,user_id,created_at,updated_at) VALUES
    (1,2,'2025-09-14 18:23:15','2025-09-14 18:23:15');
INSERT INTO public.categories (id,"name",description,created_at,updated_at) VALUES
                                                                                (1,'Elektronik Ürünler','Güncellenmiş elektronik ürünler kategorisi','2025-09-14 16:57:23','2025-09-14 17:11:10'),
                                                                                (3,'Cep Telefonları','Tüm marka ve model cep telefonları','2025-09-14 16:58:34','2025-09-14 16:58:34'),
                                                                                (2,'Ev ve Yaşam','Ev ve dekorasyona ait her şey','2025-09-14 16:58:16','2025-09-14 16:58:16');
INSERT INTO public.migrations (id,migration,batch) VALUES
                                                       (1,'0001_01_01_000000_create_users_table',1),
                                                       (2,'0001_01_01_000001_create_cache_table',1),
                                                       (3,'0001_01_01_000002_create_jobs_table',1),
                                                       (4,'2025_09_14_132330_create_personal_access_tokens_table',2),
                                                       (5,'2025_09_14_132529_add_role_to_users_table',3),
                                                       (6,'2025_09_14_165038_create_categories_table',4),
                                                       (7,'2025_09_14_173214_create_products_table',5),
                                                       (8,'2025_09_14_181637_create_carts_table',6),
                                                       (9,'2025_09_14_181657_create_cart_items_table',6),
                                                       (10,'2025_09_14_184121_create_orders_table',7);
INSERT INTO public.migrations (id,migration,batch) VALUES
    (11,'2025_09_14_184316_create_order_items_table',7);
INSERT INTO public.order_items (id,order_id,product_id,quantity,price,created_at,updated_at) VALUES
                                                                                                 (1,1,1,27,29999.99,'2025-09-14 18:46:35','2025-09-14 18:46:35'),
                                                                                                 (2,1,3,1,19999.99,'2025-09-14 18:46:35','2025-09-14 18:46:35');
INSERT INTO public.orders (id,user_id,total_amount,status,created_at,updated_at) VALUES
    (1,2,829999.72,'pending','2025-09-14 18:46:35','2025-09-14 18:46:35');
INSERT INTO public.products (id,"name",description,price,stock_quantity,category_id,created_at,updated_at) VALUES
                                                                                                               (4,'Pixel 10 Pro XL','Google Pixel 10 Pro XL',35000.00,5,3,'2025-09-14 17:43:14','2025-09-14 17:43:14'),
                                                                                                               (2,'Galaxy S23','Samsung Galaxy S23 128GB',49999.99,10,3,'2025-09-14 17:41:10','2025-09-14 17:41:10'),
                                                                                                               (5,'Xperia V1','Sony Xperia V1',105000.00,90,3,'2025-09-14 17:45:12','2025-09-14 17:45:12'),
                                                                                                               (6,'Lenovo LOQ 83GS00P5TR','Intel Core i5 12600HX 24GB 512GB SSD RTX3050 Freedos 15.6'''' IPS Taşınabilir Bilgisayar',29998.99,30,1,'2025-09-14 17:46:52','2025-09-14 17:46:52'),
                                                                                                               (7,'Epson L3266','Wi-Fi + Tarayıcı + Fotokopi Renkli Çok Fonksiyonlu Tanklı Mürekkep Püskürtmeli Yazıcı',7999.00,92,1,'2025-09-14 17:48:14','2025-09-14 17:48:14'),
                                                                                                               (8,'Bosch KDN55NWE0N','453 Lt No-Frost Buzdolabı',27256.06,65,1,'2025-09-14 17:48:51','2025-09-14 17:48:51'),
                                                                                                               (9,'Roborock Q8 Max Pro','Akıllı Robot Süpürge',17999.00,25,1,'2025-09-14 17:55:05','2025-09-14 17:55:05'),
                                                                                                               (10,'GoPro HERO12 Black','Aksiyon Kamera',183590.00,55,1,'2025-09-14 17:55:31','2025-09-14 17:55:31'),
                                                                                                               (11,'Yükseklik Ayarlı Çalışma Masası','Ceviz (Tekerli) 80X40',899.00,20,2,'2025-09-14 18:00:39','2025-09-14 18:00:39'),
                                                                                                               (12,'4 Katlı Galvaniz Çelik Raf','1.00 MM-150CM',1837.45,50,2,'2025-09-14 18:01:52','2025-09-14 18:01:52');
INSERT INTO public.products (id,"name",description,price,stock_quantity,category_id,created_at,updated_at) VALUES
                                                                                                               (13,'Hawk Gaming Chair Fab V4','Oyuncu Koltuğu',7499.00,87,2,'2025-09-14 18:02:22','2025-09-14 18:02:22'),
                                                                                                               (14,'Philips Filtre Kahve Makinesi HD7459/20','1,2 Lt Kapasite, Otomatik Kapanma, Cam Demlikli, Kolay Doldurma, Zamanlayıcı',2499.00,87,2,'2025-09-14 18:02:57','2025-09-14 18:02:57'),
                                                                                                               (15,'4 Çekmeceli Mdf Şifonyer','Comiet Soho',8998.95,17,2,'2025-09-14 18:03:58','2025-09-14 18:03:58'),
                                                                                                               (1,'iPhone 17','Apple iPhone 17 128GB',29999.99,23,3,'2025-09-14 17:40:00','2025-09-14 18:46:35'),
                                                                                                               (3,'X7 Pro','Poco X7 Pro',19999.99,24,3,'2025-09-14 17:42:26','2025-09-14 18:46:35');
INSERT INTO public.sessions (id,user_id,ip_address,user_agent,payload,last_activity) VALUES
    ('polhJtcsJlpcofnnOEXWfTBXUQ0wksqEQ1090V5q',NULL,'192.168.65.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36','YTozOntzOjY6Il90b2tlbiI7czo0MDoiOGlzeFhrVk91aVc2TDRlRnJrRURkdDBaVjQzY0taVUdmRkE2b2ZxTSI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6MTk6Imh0dHA6Ly8wLjAuMC4wOjgwMDAiO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19',1757857444);
INSERT INTO public.users (id,"name",email,email_verified_at,"password",remember_token,created_at,updated_at,"role") VALUES
                                                                                                                        (1,'Necip Koç','test@example.com',NULL,'$2y$12$q16nIjpbWikC71MG9rTPPOfPdrimli/4NJgl4mEAxU0Bk1c38zQHa',NULL,'2025-09-14 13:29:19','2025-09-14 14:50:34','user'),
                                                                                                                        (2,'Test User','user@test.com',NULL,'$2y$12$qe/EdrWDZGfj6gM7bLirKOh6j.poVsg3ifHHcwTrZeOCBB.0JjaXu',NULL,'2025-09-14 13:37:24','2025-09-14 16:47:21','user'),
                                                                                                                        (3,'Test Admin','admin@test.com',NULL,'$2y$12$fbKHKJ/J4KxLH1GYyGMG.uCQHFRRblOF6WjNC.j2SSZJ8i4JVGXLq',NULL,'2025-09-14 16:55:03','2025-09-14 17:10:29','admin'),
                                                                                                                        (4,'Test Kullanıcı','kullanici@test.com',NULL,'$2y$12$HEn0Kk3gCUeZe1AuRZxCk.qTDJJB6MsEG6NUnEeY2GQg1v6DKo/0e',NULL,'2025-09-14 19:08:46','2025-09-14 19:08:46','user');
