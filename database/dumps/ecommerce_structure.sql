-- public."cache" definition

-- Drop table

-- DROP TABLE public."cache";

CREATE TABLE public."cache" (
                                "key" varchar(255) NOT NULL,
                                value text NOT NULL,
                                expiration int4 NOT NULL,
                                CONSTRAINT cache_pkey PRIMARY KEY (key)
);


-- public.cache_locks definition

-- Drop table

-- DROP TABLE public.cache_locks;

CREATE TABLE public.cache_locks (
                                    "key" varchar(255) NOT NULL,
                                    "owner" varchar(255) NOT NULL,
                                    expiration int4 NOT NULL,
                                    CONSTRAINT cache_locks_pkey PRIMARY KEY (key)
);


-- public.categories definition

-- Drop table

-- DROP TABLE public.categories;

CREATE TABLE public.categories (
                                   id bigserial NOT NULL,
                                   "name" varchar(255) NOT NULL,
                                   description text NULL,
                                   created_at timestamp(0) NULL,
                                   updated_at timestamp(0) NULL,
                                   CONSTRAINT categories_name_unique UNIQUE (name),
                                   CONSTRAINT categories_pkey PRIMARY KEY (id)
);


-- public.failed_jobs definition

-- Drop table

-- DROP TABLE public.failed_jobs;

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


-- public.job_batches definition

-- Drop table

-- DROP TABLE public.job_batches;

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


-- public.jobs definition

-- Drop table

-- DROP TABLE public.jobs;

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


-- public.migrations definition

-- Drop table

-- DROP TABLE public.migrations;

CREATE TABLE public.migrations (
                                   id serial4 NOT NULL,
                                   migration varchar(255) NOT NULL,
                                   batch int4 NOT NULL,
                                   CONSTRAINT migrations_pkey PRIMARY KEY (id)
);


-- public.password_reset_tokens definition

-- Drop table

-- DROP TABLE public.password_reset_tokens;

CREATE TABLE public.password_reset_tokens (
                                              email varchar(255) NOT NULL,
                                              "token" varchar(255) NOT NULL,
                                              created_at timestamp(0) NULL,
                                              CONSTRAINT password_reset_tokens_pkey PRIMARY KEY (email)
);


-- public.personal_access_tokens definition

-- Drop table

-- DROP TABLE public.personal_access_tokens;

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


-- public.sessions definition

-- Drop table

-- DROP TABLE public.sessions;

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


-- public.users definition

-- Drop table

-- DROP TABLE public.users;

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


-- public.carts definition

-- Drop table

-- DROP TABLE public.carts;

CREATE TABLE public.carts (
                              id bigserial NOT NULL,
                              user_id int8 NOT NULL,
                              created_at timestamp(0) NULL,
                              updated_at timestamp(0) NULL,
                              CONSTRAINT carts_pkey PRIMARY KEY (id),
                              CONSTRAINT carts_user_id_unique UNIQUE (user_id),
                              CONSTRAINT carts_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE
);


-- public.orders definition

-- Drop table

-- DROP TABLE public.orders;

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


-- public.products definition

-- Drop table

-- DROP TABLE public.products;

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


-- public.cart_items definition

-- Drop table

-- DROP TABLE public.cart_items;

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


-- public.order_items definition

-- Drop table

-- DROP TABLE public.order_items;

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
